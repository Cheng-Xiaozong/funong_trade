<?php
/**
 * Created by PhpStorm.
 * User: alen
 * Date: 2017/11/1
 * Time: 16:36
 */

namespace App\Http\Controllers\Dealers;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Admin\CommonController;
use App\Http\Controllers\Home\OfferController;
use function GuzzleHttp\Promise\is_settled;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\OrderService;;
use App\Services\OfferService;
use App\Services\AccountService;
use App\Services\SendMsgService;
use App\Services\GoodsService;
use App\Services\AddressService;
use App\Services\MqttService;
use App\Services\AreaInfoService;
use App\Services\AddressLocateService;
use App\Order;
use App\Account;
use App\GoodsOffer;
use GuzzleHttp\Client;
use Tymon\JWTAuth\Facades\JWTAuth;

class ContractController extends BaseController
{
    protected $AppVersion;
    protected $Validator;
    protected $Order;
    protected $Offer;
    protected $Account;
    protected $Goods;
    protected $Address;
    protected $Mqtt;
    protected $Area;
    protected $Locate;
    protected $url;
    protected $Common;
    protected $OfferController;
    protected $Msg;
    protected $getContractListByBuyerId = '/trade/contract/api?act=getContractList';
    protected $getContractSellerList = '/trade/contract/api?act=getContractSellerList';
    protected $getContractDetailByOrderCode = '/trade/contract/api?act=getContractDetailByContractCode';
    protected $postContractApply = '/trade/contract/api?act=postContractApply';
    protected $getContractApplyList = '/trade/contract/api?act=getContractApplyList';
    protected $getContractListByGoodsId = '/trade/contract/api?act=getContractListByGoodsId';
    protected $addPickApply = '/trade/delivery/api?act=addPickApply';
    protected $getAllDeliveryApply = '/trade/delivery/api?act=getAllDeliveryApply';
    protected $getDeliveryApplyDetail = '/trade/delivery/api?act=getDeliveryApplyDetail';
    protected $getAllDeliveryOrder = '/trade/delivery/api?act=getAllDeliveryOrder';
    protected $getDeliveryOrderDetail = '/trade/delivery/api?act=getDeliveryOrderDetail';
    protected $paymentOrder = '/trade/money/api?act=createRecord';
    protected $getAllBill = '/trade/money/api?act=getAllBill';
    protected $getBillInfo = '/trade/money/api?act=getBillInfo';
    protected $getAllBlotter = '/trade/money/api?act=getAllBlotter';
    protected $getBlotterInfo = '/trade/money/api?act=getBlotterInfo';
    protected $getBillListByContractId = '/trade/money/api?act=getBillListByContractId';
    protected $cancelDeliveryApply = '/trade/delivery/api?act=cancelPickApply';
    protected $changeDeliveryApply = '/trade/delivery/api?act=changeDeliveryApply';
    protected $changeDriverApply = '/trade/delivery/api?act=changeDriverApply';
    protected $getContractStat = '/trade/contract/api?act=getContractStat';
    protected $getContractDeliveryRecord = '/trade/contract/api?act=getContractDeliveryRecord';
    protected $countContract = '/trade/contract/api?act=countContract';
    protected $countDeposit = '/trade/contract/api?act=countDeposit';
    protected $countSellOrder = '/trade/contract/api?act=countSellOrder';
    protected $countProfitAndLoss = '/trade/contract/api?act=countProfitAndLoss';
    protected $countPurchaseOrder = '/trade/contract/api?act=countPurchaseOrder';
    protected $getAllBillByDeliveryOrderId = '/trade/delivery/api?act=getAllBillByDeliveryOrderId';
    protected $getWeiContractList = '/trade/contract/api?act=getWeiContractList';
    protected $getTradeCustomerMoney = '/trade/money/api?act=getTradeCustomerMoney';
    protected $getTradeCustomerCollectionBills = '/trade/money/api?act=getTradeCustomerCollectionBills';
    protected $getCountPurchaseSale = '/trade/contract/api?act=countPurchaseSale';
    protected $getCountChangeContract = '/trade/contract/api?act=countChangeContract';
    protected $searchDriver = '/trade/delivery/api?act=searchDriver';
    protected $searchContract = '/trade/contract/api?act=searchContract';
    protected $getUserTags = '/trade/account/api?act=getUserTags';

    /**
     * AppVersionController constructor.
     * @param Request $request
     * @param Log $log
     * @param Redis $redis
     * @param Validator $validator
     */
    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        Validator $validator,
        OrderService $order,
        OfferService $offer,
        AccountService $Account,
        GoodsService $goods,
        AddressService $address,
        AreaInfoService $area,
        AddressLocateService $Locate,
        OfferController $offerController,
        MqttService $mqtt,
        CommonController $common,
        SendMsgService $msg
    )
    {
        parent::__construct($request, $log, $redis);
        $this->Validator = $validator;
        $this->Order = $order;
        $this->Offer = $offer;
        $this->Account = $Account;
        $this->Goods = $goods;
        $this->Address = $address;
        $this->Mqtt = $mqtt;
        $this->Area = $area;
        $this->Locate = $Locate;
        $this->Common = $common;
        $this->OfferController = $offerController;
        $this->Msg = $msg;
        $this->url = config('ext.funong_dealers_url');
    }


/*------------订单-----------------*/

    /**
     * 更改订单状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeOrderStatus()
   {

       $validator = $this->Validator::make($this->Input, [
           'order_number' => 'required',
           'order_status' => '',
           'operation_status' => 'required | numeric',
       ], [
           'required' => ':attribute为必填项',
           'numeric' => ':attribute为数字',
       ], [
           'order_number' => '订单编号',
           'order_status' => '订单状态',
           'operation_status' => '操作状态',
       ]);

       if ($validator->fails()) {
           $error['errors'] = $validator->errors();
           return apiReturn(-104, '数据验证失败', $error);
       }

       $order = $this->Order::getOrderByOrderNumber($this->Input['order_number']);

       if(empty($order)){
           return apiReturn(-40018, '订单不存在！');
       }

       if(!isset($this->Input['order_status'])){
           $this->Input['order_status'] = $order->order_status;
       }

       //作废订单
       if($this->Input['order_status'] == Order::ORDER_STATUS['disable']){
           $data['order_status'] = Order::ORDER_STATUS['disable'];
       }

       //处理中订单
       if( ($this->Input['operation_status'] != Order::OPERATION_STATUS['ending'])
       and ($this->Input['order_status'] != Order::ORDER_STATUS['disable'])
       ){
           $data['order_status'] = Order::ORDER_STATUS['unfinished'];
       }

       //已完成订单
       if($this->Input['operation_status'] == Order::OPERATION_STATUS['ending']){
           $data['order_status'] = Order::ORDER_STATUS['finished'];
       }

       $data['operation_status'] = $this->Input['operation_status'];
       $return_data['order_number'] = $this->Input['order_number'];

       if(isset($this->Input['order_status'])){

           if(count($order->goodsOffer)){
               $offer_data['lock_number'] = $order->goodsOffer->lock_number - $order->num;
               if($offer_data['lock_number'] < 0){
                   $offer_data['lock_number'] = 0;
               }
               $offer_data['updated_at'] = $order->goodsOffer->updated_at;
               //更新锁定量
               $this->Offer::updateOfferById($order->goods_offer_id,$offer_data);
           }

           //更新订单
           $order_result = $this->Order::updateOrderByOrderNumber($this->Input['order_number'],$data);

           if ($order_result) {
               DB::commit();
               return apiReturn(0, '请求成功 !',$return_data);
           } else {
               DB::rollBack();
           }
       }else{
           $order_result = $this->Order::updateOrderByOrderNumber($this->Input['order_number'],$data);

           if($order_result){
               return apiReturn(0, '请求成功 !',$return_data);
           }
       }

       return apiReturn(-40022, '订单更新失败！');
   }


    /**
     * 接单
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmOrder()
    {

        $validator = $this->Validator::make($this->Input, [
            'id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }

        $order = $this->Order::getOrderById($this->Input['id']);

        if(is_null($order)){
            return apiReturn(-40018, '订单不存在！');
        }

        if($order->order_status != Order::ORDER_STATUS['waiting']){
            return apiReturn(-40019, '已确认订单无需再次确认！');
        }

        $buyer = $this->Account::getBuyerBuId($order->account_buyer_id);
        $buyer_account = $buyer->Account;
        $update_data['order_status'] = Order::ORDER_STATUS['unfinished'];
        $goods_offer = $order->goodsOffer;
        $offer_data['lock_number'] = $goods_offer->lock_number - $order->num;
        if($offer_data['lock_number'] < 0){
            $offer_data['lock_number'] = 0;
        }

        DB::beginTransaction();
        $order_result = $this->Order::updateOrder($this->Input['id'],$update_data);
        $offer_result = $this->Offer::updateOfferById($goods_offer->id,$offer_data);
        if ($order_result && $offer_result) {
            DB::commit();
            $this->Mqtt->sendCommonMsg('order','商家已接单!',$order->id,$buyer_account->id);
            return apiReturn(0, '接单成功 !');
        } else {
            DB::rollBack();
        }

        return apiReturn(-40020, '接单失败！');
    }


    /**
     * 订单列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderList()
    {

        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'account_seller_id' => 'required',
            'page' => 'required | integer',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $result=$this->Order::getOrderListByBusinessId($this->Input['account_seller_id'],$this->Input['page_size']);

        if(!is_null($result)){
            foreach ($result as $k=>$v){
                $v->image = getImgUrl($v->image,'goods_imgs','');
            }
        }
        $data['order_list']=$result;
        return apiReturn(0,'获取订单列表成功',$data);
    }


    /**
     * 作废订单
     * @return \Illuminate\Http\JsonResponse
     */
    public  function disableOrderById()
    {
        $validator = $this->Validator::make($this->Input, [
            'id' => 'required | integer',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $order = $this->Order::getOrderById($this->Input['id']);

        if(is_null($order)){
            return apiReturn(-50002, '订单不存在！');
        }

        if($order->order_status >= Order::ORDER_STATUS['finished']){
            return apiReturn(-50003, '订单无法作废！');
        }

        if($order->operation_status >= Order::OPERATION_STATUS['cashing']){
            return apiReturn(-50003, '订单无法作废！');
        }
        $order_data['order_status'] = Order::ORDER_STATUS['disable'];
        $offer_data['lock_number'] = $order->goodsOffer->lock_number - $order->num;
        if($offer_data['lock_number'] < 0){
            $offer_data['lock_number'] = 0;
        }

        $offer_data['stock'] = $order->goodsOffer->stock + $order->num;
        $offer_data['updated_at'] = $order->goodsOffer->updated_at;

        $overplus_stock_key = 'goods_offer_overplus_stock';
        try {
            $overplus_stock = $this->Redis::get($overplus_stock_key);
        } catch (\Exception $exception) {
        }

        DB::beginTransaction();

        //更新订单
        $order_result = $this->Order::updateOrder($this->Input['id'],$order_data);
        //更新锁定量
        $goods_offer_result = $this->Offer::updateOfferById($order->goods_offer_id,$offer_data);
        if ($order_result) {

            //更新系统库存
            if(isset($overplus_stock)){
                if($overplus_stock){
                    $overplus_stock += $order->num;
                    $this->Redis::set($overplus_stock_key, $overplus_stock);
                }
            }

            DB::commit();

            $offer_params = $this->OfferController->abuttedParam($order->goods_info);
            $order_seller = $this->Account::getSellerByid($order->account_seller_id);
            $order_seller_business = $this->Account::getBusinessById($order_seller->account_business_id);
            $order_buyer_business = $this->Account::getBusinessById($order->buyer_business_id);
            //发送微信通知
            $msg_data['data'] = array(
                'data' => array (
                    'first'    => array('value' => "卖家信息：".$order_seller_business->name."，".$order_seller_business->contact_phone."\n买家信息：".$order_buyer_business->name."，".$order_buyer_business->contact_phone),
                    'keyword1' => array('value' => $order->order_number."\n,".$order->price.','.$order->num.$order->order_unit),
                    'keyword2' => array('value' => date('Y-m-d H:i:s',strtotime($order->created_at))),
                    'keyword3' => array('value' => date('Y-m-d H:i:s',time())),
                    'keyword4' => array('value' => '平台取消'."\n商品参数:".$offer_params),
                    'remark'   => array('value' => "\n请中断处理！")
                )
            );
            $msg_data['action'] = "orderCancel";
            $this->Common->socketMessage($msg_data);

            return apiReturn(0, '请求成功 !');
        } else {
            DB::rollBack();
        }

        return apiReturn(-50001, '作废失败！');
    }


    /**
     * 搜索订单
     * @return \Illuminate\Http\JsonResponse
     */
    public function SearchOrderList()
    {

        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'account_seller_id' => 'required',
            'where' => 'array|required',
            'where.seller_name' => 'string',
            'where.buyer_name' => 'string',
            'where.goods_name' => 'string',
            'where.order_status' => 'int',
            'where.operation_status' => 'int',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为整数',
            'array' => ':attribute不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->Order::SearchOrderListByBusinessId($this->Input['account_seller_id'],$this->Input['where'],$this->Input['page_size']);
        $data['order_list']=$result;
        return apiReturn(0,'获取订单列表成功',$data);
    }


    /**
     * 订单详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderById()
    {
        $validator = $this->Validator::make($this->Input, [
            'id' => 'required | integer',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为整数',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $result=$this->Order::getOrderById($this->Input['id']);

        if(!is_null($result)){
            $result->image = getImgUrl($result->image,'goods_imgs','');
        }

        return apiReturn(0,'获取成功',$result);
    }


    /**
     * 统计订单
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function countOrder()
    {

        $validator = $this->Validator::make($this->Input, [
            'account_seller_id' => 'required',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $result = $this->Order::countOrder($this->Input['account_seller_id']);
        return $result;
    }


    /*------------erp合同-----------------*/
    /**
     * 通过卖家获取意向合同
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContractListBySeller()
   {

       $validator = $this->Validator::make($this->Input, [
           'seller_id' => 'required',
           'page_size' => 'required',
       ], [
           'required' => ':attribute为必填项',
       ], [
           'seller_id' => '卖家id',
           'page_size' => '每页显示条数',
       ]);

       if ($validator->fails()) {
           $error['errors'] = $validator->errors();
           return apiReturn(-104, '数据验证失败', $error);
       }

       $page_size = $this->Input['page_size'];
       $data['order_list'] = array();
       $data['order_list'] = $this->Order::getOrderByAccountSellerIds($this->Input['seller_id'],$page_size);

       return apiReturn(0, '请求成功 !',$data);
   }


    /**
     * 正式合同列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContractList()
    {

        $validator = $this->Validator::make($this->Input, [
            'contract_status' => 'required | numeric',
            'page' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'contract_status' => '合同状态',
            'page' => '第几页',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        $param['page'] = $this->Input['page'];
        $param['page_size'] = 10;
        $param['contract_status'] = $this->Input['contract_status'];
        $param['type'] = 1;

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if($user->account_type == Account::ACCOUNT_TYPE['buyer']){
            if(!empty($employee)){
                $buyer_account = $this->Account::getAccountById($employee->super_id);
                $account_buyer = $buyer_account->AccountBuyer;
            }else{
                $account_buyer = $user->AccountBuyer;
            }
            $param['trade_buyer_id'] = $account_buyer->id;
        }else{
            if(!empty($employee)){
                $account = $this->Account::getAccountById($employee->super_id);
                $seller = $account->AccountSeller;
            }else{
                $seller = $user->AccountSeller;
            }
            $param['trade_seller_id'] = $seller->id;
        }


        try{
            $client = new Client();
            if($user->account_type == Account::ACCOUNT_TYPE['buyer']){
                $result = $client->request('post', $this->url.$this->getContractListByBuyerId, ['json'=>$param])->getBody()->getContents();
            }else{
                return apiReturn(-40023, '合同获取失败！');
                $result = $client->request('post', $this->url.$this->getContractSellerList, ['json'=>$param])->getBody()->getContents();
            }

            $result = json_decode($result,true);
            $data['contract_list'] = array();

            if(!isset($result['data'])){
                return apiReturn(-40023, '合同获取失败！');
            }

            foreach ($result['data']['contract_list']['data'] as $k=>$v){
                $data['contract_list'][$k] = $this->formatResult($v);
            }

            $data['paginate'] = $result['data']['contract_list'];
            unset($data['paginate']['data']);
            return apiReturn(0, '请求成功 !',$data);
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 正式合同列表(商品)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContractListAsGoods()
    {

        $validator = $this->Validator::make($this->Input, [
            'contract_status' => 'required | numeric',
            'page' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'contract_status' => '合同状态',
            'page' => '第几页',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;

        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            $account_buyer = $buyer_account->AccountBuyer;
        }else{
            $account_buyer = $user->AccountBuyer;
        }

        if(empty($account_buyer)){
            return apiReturn(-40022, '您不是买家！');
        }

        $param['page'] = $this->Input['page'];
        $param['page_size'] = 10;
        $param['trade_buyer_id'] = $account_buyer->id;
        $param['contract_status'] = $this->Input['contract_status'];
        $param['type'] = 2;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getContractListByBuyerId, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            $data['goods_list'] = array();

            if(!isset($result['data'])){
                return apiReturn(-40023, '合同获取失败！');
            }

            foreach ($result['data']['contract_list']['data'] as $k=>$v){
                $data['goods_list'][$k] = $this->formatGoodsResult($v);
            }

            $data['paginate'] = $result['data']['contract_list'];
            unset($data['paginate']['data']);

            return apiReturn(0, '请求成功 !',$data);
        }catch (\Exception $exception){


            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 通过商品信息获取合同
     * @return \Illuminate\Http\JsonResponse
     */
    public function getContractListByGoodsId()
    {

        $validator = $this->Validator::make($this->Input, [
            'good_id' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;

        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            $account_buyer = $buyer_account->AccountBuyer;
        }else{
            $account_buyer = $user->AccountBuyer;
        }

        if(empty($account_buyer)){
            return apiReturn(-40022, '您不是买家！');
        }

        $param['trade_buyer_id'] = $account_buyer->id;
        $param['good_id'] = $this->Input['good_id'];

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getContractListByGoodsId, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            $data['contract_list'] = array();

            if(!isset($result['data'])){
                return apiReturn(-40023, '合同获取失败！');
            }

            foreach ($result['data']['contract_list'] as $k=>$v){
                $data['contract_list'][$k] = $this->formatGoodsResult($v);
            }

            return apiReturn(0, '请求成功 !',$data);
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 查看正式合同详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFormalContract()
    {

        $validator = $this->Validator::make($this->Input, [
            'contract_code' => 'required',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'contract_code' => '合同编号',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $param['contract_code'] = $this->Input['contract_code'];

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getContractDetailByOrderCode, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);

            if((!isset($result['data'])) or (empty($result['data']))){
                return apiReturn(-40023, '合同获取失败！');
            }

            $data['contract_detail'] = array();
            $data['contract_detail'] = $this->formatResult($result);
            return apiReturn(0, '请求成功 !',$data);
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 申请点价
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function applyPricing()
    {

        $validator = $this->Validator::make($this->Input, [
            'contract_type' => 'required | integer',
            'contract_code' => 'required',
            'contract_amount' => 'required',
            'contract_money' => 'required',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'contract_code' => '合同编号',
            'contract_type' => '申请类型',
            'contract_amount' => '数量',
            'contract_money' => '金额',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;

        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            $account_buyer = $buyer_account->AccountBuyer;
        }else{
            $account_buyer = $user->AccountBuyer;
        }

        if(empty($account_buyer)){
            return apiReturn(-40022, '您不是买家！');
        }

        $business = $account_buyer->AccountBusiness;
        $param = $this->Input;
        $param['buyer_info'] =$business->contact_phone;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->postContractApply, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            if($result['code'] == 0){
                return apiReturn(0, '申请成功 !');
            }
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }

        return apiReturn(-10055, '申请失败！');

    }


    /**
     * 合同记录
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getContractApplyList()
    {

        $validator = $this->Validator::make($this->Input, [
            'type' => 'required | integer',
            'contract_code' => 'required',
            'page' => 'required | integer',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;
        $data['page_size'] = 10;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getContractApplyList, ['json'=>$data])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;

        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 获取合同数量
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function countContract()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if($user->account_type == Account::ACCOUNT_TYPE['buyer']){
            if(!empty($employee)){
                $buyer_account = $this->Account::getAccountById($employee->super_id);
                $account_buyer = $buyer_account->AccountBuyer;
            }else{
                $account_buyer = $user->AccountBuyer;
            }
            $param['type'] = 1;
            $param['trade_user_id'] = $account_buyer->id;
            $order_number = $this->Order::countOrderByBuyerId($param['trade_user_id']);
        }else{
            if(!empty($employee)){
                $account = $this->Account::getAccountById($employee->super_id);
                $seller = $account->AccountSeller;
            }else{
                $seller = $user->AccountSeller;
            }
            $param['type'] = 2;
            $param['trade_user_id'] = $seller->id;
            $order_number = $this->Order::countOrderBySellerId($param['trade_user_id']);
        }


        //获取合同数量
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getContractStat, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }

        if(isset($result['data'])){
            $result['data']['order_num'] = $order_number;
            return $result;
        }else{
            $result['order_num'] = $order_number;
            return apiReturn(0, '请求成功 !',$result);
        }
    }


/*------------erp提货-----------------*/
    /**
     * 申请提货
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function addPickApply()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $account_buyer = $buyer_account->AccountBuyer;
            $buyer_business = $buyer_account->AccountInfo;
        }else{
            $account_buyer = $user->AccountBuyer;
            $buyer_business = $user->AccountInfo;
        }

        if(empty($account_buyer)){
            return apiReturn(-40022, '您不是买家！');
        }

        if(config('app.debug')==false){
            //开单时间
            $ga = date("w");
            $nine = strtotime(date("Y-m-d",time())) + 3600*9;
            $twelve = strtotime(date("Y-m-d",time())) + 3600*12;
            $sixteen = strtotime(date("Y-m-d",time())) + 3600*16;

            $sunday = false;
            switch( $ga ) {
                case 0 :
                    $sunday = true;
                    break;
            }

            if($sunday){
                if((time() <$nine) or (time() > $twelve)){
                    $this->Msg::sendDeliveryMsg($buyer_business->contact_phone);
                }
            }else{
                //开单时间
                if((time() <$nine) or (time() > $sixteen)){
                    $this->Msg::sendDeliveryMsg($buyer_business->contact_phone);
                }
            }
        }

        $param = $this->Input;
        $param['trade_buyer_id'] = $account_buyer->id;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->addPickApply, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);

            if($result['code'] == 0){

                //组装数据
                $num = 0;
                foreach ($this->Input['contract_info'] as $value){
                    $num += $value['contract_number'];
                }
                //发送提货通知
                $msg_data['data'] = array(
                    'data' => array (
                        'first'    => array('value' => "来自应用：%1\$s\n"."买家发起申请"."\n买家信息：".$buyer_business->name."，".$user->phone.""),
                        'keyword1' => array('value' => date('Y-m-d H:i:s',time())),
                        'keyword2' => array('value' => $this->Input['good_name']),
                        'keyword3' => array('value' => "自提，富农代办,".$this->Input['delivery_total_number']."吨"),
                        'remark'   => array('value' => "\n请及时进行处理！")
                    )
                );
                $msg_data['action'] = "orderDelivery";
                $this->Common->socketMessage($msg_data);
            }
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 修改提货申请
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function changeDeliveryApply()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $account_buyer = $buyer_account->AccountBuyer;
        }else{
            $account_buyer = $user->AccountBuyer;
        }

        if(empty($account_buyer)){
            return apiReturn(-40022, '您不是买家！');
        }

        $param = $this->Input;
        $param['trade_buyer_id'] = $account_buyer->id;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->changeDeliveryApply, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 更改司机申请 （提货中）
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function changeDriverApply()
    {

        $param = $this->Input;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->changeDriverApply, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 提货申请列表
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getAllDeliveryApply()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $account_buyer = $buyer_account->AccountBuyer;
        }else{
            $account_buyer = $user->AccountBuyer;
        }

        if(empty($account_buyer)){
            return apiReturn(-40022, '您不是买家！');
        }

        $param = $this->Input;
        $param['trade_buyer_id'] = $account_buyer->id;
        $param['page_size'] = 10;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getAllDeliveryApply, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 买家申请提货详情
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getDeliveryApplyDetail()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $account_buyer = $buyer_account->AccountBuyer;
        }else{
            $account_buyer = $user->AccountBuyer;
        }

        if(empty($account_buyer)){
            return apiReturn(-40022, '您不是买家！');
        }

        $param = $this->Input;
        $param['trade_buyer_id'] = $account_buyer->id;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getDeliveryApplyDetail, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 取消提货申请
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelDeliveryApply()
    {

        $validator = $this->Validator::make($this->Input, [
            'id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        try{
            $data = $this->Input;
            $token = $this->Request->header('token');
            $client = new Client();
            $result = $client->request('post', $this->url.$this->cancelDeliveryApply, ['json'=>$data,'headers'=>['token'=>$token]])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 买家提货单列表
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getAllDeliveryOrder()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $account_buyer = $buyer_account->AccountBuyer;
        }else{
            $account_buyer = $user->AccountBuyer;
        }

        if(empty($account_buyer)){
            return apiReturn(-40022, '您不是买家！');
        }

        $param = $this->Input;
        $param['trade_buyer_id'] = $account_buyer->id;
        $param['page_size'] = 10;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getAllDeliveryOrder, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 买家提货单详情
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getDeliveryOrderDetail()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $account_buyer = $buyer_account->AccountBuyer;
        }else{
            $account_buyer = $user->AccountBuyer;
        }

        if(empty($account_buyer)){
            return apiReturn(-40022, '您不是买家！');
        }

        $param = $this->Input;
        $param['trade_buyer_id'] = $account_buyer->id;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getDeliveryOrderDetail, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 提货单账单列表
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getDeliveryBill()
    {

        $param = $this->Input;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getAllBillByDeliveryOrderId, ['json'=>$param])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-10054, '数据获取失败！');
        }
    }


    /**
     * 格式化数据
     * @param $result
     * @return mixed
     */
    public function formatResult($result)
    {
        if(!empty($result)){
            if(isset($result['data']['contract_detail'])){
                $father_contract = $result['data']['contract_detail'];
            }else{
                $father_contract = $result;
            }

            $contract['contract_type'] = $father_contract['type'];
            $contract['id'] = $father_contract['id'];
            $contract['good_id'] = $father_contract['good_id'];
            $contract['dominant_contract'] = $father_contract['dominant_contract'];
            $contract['contract_code'] = $father_contract['contract_code'];
            $contract['contract_status'] = $father_contract['contract_status'];
            $contract['order_code'] = $father_contract['order_code'];
            $contract['sign_time'] = $father_contract['sign_time'];
            $contract['cash_money'] = $father_contract['cash_money'];
            $contract['cash_time'] = $father_contract['cash_time'];
            $contract['cash_type'] = Order::OPERATION_STATUS_DESCRIBE[$father_contract['contract_status']];
            $contract['seller_name'] = $father_contract['seller_name'];
            $contract['good_name'] = $father_contract['good_name'];
            $contract['good_unit'] = $father_contract['good_unit'];
            $contract['good_price'] = $father_contract['good_price'];
            $contract['goods_info'] = $father_contract['field_attr'];
            $contract['contract_number'] = $father_contract['good_num'];
            $contract['contract_price'] = $father_contract['contract_total_price'];
            $contract['delivery_start_time'] = $father_contract['delivery_start_time'];
            $contract['delivery_end_time'] = $father_contract['delivery_end_time'];
            $contract['delivery_address'] = $father_contract['delivery_address'];
            $contract['contract_remark'] = $father_contract['contract_remark'];
            $contract['contract_attachment'] = $father_contract['contract_attachment'];
            $contract['image'] = $father_contract['good_image'];
            $contract['delivery_num'] = $father_contract['delivery_num'];
            $contract['remain_nume'] = $father_contract['remain_num'];
            $contract['account_business_id'] = $father_contract['account_business_id'];

            if(isset($father_contract['child_contract_apply'])){
                $contract['child_contract_apply'] = $father_contract['child_contract_apply'];
            }

            $contract['offer_info'] = array();
            if(isset($father_contract['offer_info']) and !is_null(json_decode($father_contract['offer_info']))){
                $contract['offer_info'] = json_decode($father_contract['offer_info'],true);
            }

            $contract['delivery_record']  = array();
            if(isset($father_contract['delivery_record'])){
                $contract['delivery_record'] = $father_contract['delivery_record'];
            }

            return $contract;
        }
    }


    /**
     * 处理数据
     * @param $result
     * @return mixed
     */
    public function formatGoodsResult($result)
    {
        if(!empty($result)){
            if(isset($result['data']['contract_detail'])){
                $father_contract = $result['data']['contract_detail'];
            }else{
                $father_contract = $result;
            }

            $contract['contract_type'] = $father_contract['type'];
            $contract['id'] = $father_contract['id'];
            $contract['good_id'] = $father_contract['good_id'];
            $contract['dominant_contract'] = $father_contract['dominant_contract'];
            $contract['contract_code'] = $father_contract['contract_code'];
            $contract['contract_status'] = $father_contract['contract_status'];
            $contract['order_code'] = $father_contract['order_code'];
            $contract['sign_time'] = $father_contract['sign_time'];
            $contract['seller_name'] = $father_contract['seller_name'];
            $contract['good_name'] = $father_contract['good_name'];
            $contract['good_unit'] = $father_contract['good_unit'];
            $contract['good_price'] = $father_contract['good_price'];
            $contract['goods_info'] = $father_contract['field_attr'];
            $contract['contract_number'] = $father_contract['good_num'];
            $contract['contract_price'] = $father_contract['contract_total_price'];
            $contract['delivery_address'] = $father_contract['delivery_address'];
            $contract['contract_remark'] = $father_contract['contract_remark'];
            $contract['contract_attachment'] = $father_contract['contract_attachment'];
            $contract['image'] = $father_contract['good_image'];
            $contract['delivery_num'] = $father_contract['delivery_num'];
            $contract['remain_nume'] = $father_contract['remain_num'];
            $contract['good_id'] = $father_contract['good_id'];
            $contract['trade_buyer_id'] = $father_contract['trade_buyer_id'];
            $contract['is_delivery'] = $father_contract['is_delivery'];
            $contract['delivery_start_time'] = $father_contract['delivery_start_time'];
            $contract['delivery_end_time'] = $father_contract['delivery_end_time'];
            $contract['account_business_id'] = $father_contract['account_business_id'];

            $contract['offer_info'] = array();
            if(isset($father_contract['offer_info']) and !is_null(json_decode($father_contract['offer_info']))){
                $contract['offer_info'] = json_decode($father_contract['offer_info'],true);
            }
            return $contract;
        }
    }


    /**
     * 提交付款信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentOrder()
    {

        $content = $this->Request->all();

        if(isset($content['payment_info'])){
            $content = json_decode($content['payment_info'],true);
            $validator = $this->Validator::make($content, [
                'contract_code' => 'required',
                'bank_name' => 'required',
                'bank_account' => 'required',
                'money' => 'required',
                'file' => '',
            ], [
                'required' => ':attribute为必填项',
                'numeric' => ':attribute为数字',
            ]);

            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-104, '数据验证失败', $error);
            }
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $buyer_business = $buyer_account->AccountInfo;
        }else{
            $buyer_business = $user->AccountInfo;
        }

        //是否为买家
        if($user->account_type != Account::ACCOUNT_TYPE['buyer']){
            return apiReturn(-40021, '您不是买家！');
        }

        if(!empty($this->Request->file('file'))){
            $data['file'] = $this->uploadImages($this->Request->file('file'));
        }

        $post_data['trade_business_id'] = $buyer_business->id;
        $post_data['amount'] = $content['money'];
        $post_data['money_account_name'] = $content['bank_name'];
        $post_data['account_num'] = $content['bank_account'];

        $i = 0;
        $param[$i]['name'] = 'form_data';
        $param[$i]['contents'] = json_encode($post_data);
        $i++;

        if(isset($this->Request->all()['file'])){
            $param[$i]['name'] = 'accessory';
            $param[$i]['contents'] = fopen($this->Request->file()['file'], 'r');
            $param[$i]['filename'] = $this->Request->file()['file']->getClientOriginalName();
        }

        try{
            $client = new Client();
            $result = $client->request('POST', $this->url.$this->paymentOrder, [
                'multipart' => $param
            ])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-40022, '付款信息上传失败 !');
        }
    }


    /**
     * 获取所有账单
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getAllBill()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //是否为买家
        if($user->account_type != Account::ACCOUNT_TYPE['buyer']){
            return apiReturn(-40021, '您不是买家！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $buyer_business = $buyer_account->AccountInfo;
        }else{
            $buyer_business = $user->AccountInfo;
        }

        $data = $this->Input;
        $data['page_size'] = 10;
        $data['trade_business_id'] = $buyer_business->id;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getAllBill, ['json'=>$data,'headers'=>['token'=>$token]])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 获取客户应付账单列表
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getTradeCustomerCollectionBills()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //是否为买家
        if($user->account_type != Account::ACCOUNT_TYPE['buyer']){
            return apiReturn(-40021, '您不是买家！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $buyer_business = $buyer_account->AccountInfo;
        }else{
            $buyer_business = $user->AccountInfo;
        }

        $data = $this->Input;
        $data['page_size'] = 10;
        $data['trade_business_id'] = $buyer_business->id;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getTradeCustomerCollectionBills, ['json'=>$data,'headers'=>['token'=>$token]])->getBody()->getContents();
            $result = json_decode($result,true);
            return apiReturn(0, '获取成功 !',$result);
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 获取商贸通客户资金
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getCustomerMoney()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //是否为买家
        if($user->account_type != Account::ACCOUNT_TYPE['buyer']){
            return apiReturn(-40021, '您不是买家！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $buyer_business = $buyer_account->AccountInfo;
        }else{
            $buyer_business = $user->AccountInfo;
        }

        $data = $this->Input;
        $data['trade_business_id'] = $buyer_business->id;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getTradeCustomerMoney, ['json'=>$data,'headers'=>['token'=>$token]])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 获取账单详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBillInfo()
    {

        $validator = $this->Validator::make($this->Input, [
            'customer_id' => 'required | integer',
            'bill_id' => 'required | integer',
            'account_business_id' => 'required | integer',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
            'integer' => ':attribute整型',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $data = $this->Input;
        $data['page_size'] = 10;
        $data['trade_business_id'] = $this->Input['customer_id'];
        unset($data['customer_id']);

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getBillInfo, ['json'=>$data,'headers'=>['token'=>$token]])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 获取所有流水
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getAllBlotter()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //是否为买家
        if($user->account_type != Account::ACCOUNT_TYPE['buyer']){
            return apiReturn(-40021, '您不是买家！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常！');
            }
            $buyer_business = $buyer_account->AccountInfo;
        }else{
            $buyer_business = $user->AccountInfo;
        }

        $data = $this->Input;
        $data['page_size'] = 10;
        $data['trade_business_id'] = $buyer_business->id;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getAllBlotter, ['json'=>$data,'headers'=>['token'=>$token]])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 获取流水详情
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getBlotterInfo()
    {

        $token = $this->Request->header('token');
        $data = $this->Input;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getBlotterInfo, ['json'=>$data,'headers'=>['token'=>$token]])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 获取合同账单
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getBillListByContractId()
    {

        $token = $this->Request->header('token');
        $data = $this->Input;
        $data['page_size'] = 100;
        $data['page'] = 1;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getBillListByContractId, ['json'=>$data,'headers'=>['token'=>$token]])->getBody()->getContents();
            $result = json_decode($result,true);
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 上传图片
     * @param $image
     * @return string
     */
    public function uploadImages($image)
    {

        $realPath = $image->getRealPath();
        $ext = $image->getClientOriginalExtension();
        $toDir = date('Y-m-d');
        Storage::disk('payment_imgs')->makeDirectory($toDir);
        $file = date('Y-m-d-H-i-s') . '-' . uniqid();
        $filename = $file . '.' . $ext;
        Storage::disk('payment_imgs')->put($toDir . '/' . $file . '.' . $ext, file_get_contents($realPath));

        return $filename;
    }


/*------------erp账户-----------------*/
    /**
     * 关联账户
     * @return \Illuminate\Http\JsonResponse
     */
    public function relationAccount()
    {

        $validator = $this->Validator::make($this->Input, [
            'account_number' => 'required | min:2 | max:11',
        ], [
            'required' => ':attribute为必填项',
            'min' => ':attribute最短2位',
            'max' => ':attribute最长10位',
        ], [
            'account_number' => '账号',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        //验证是否注册
        $user = $this->Account::loginAccount(htmlspecialchars($this->Input['account_number']));

        if(is_null($user) || ($user->register_status < Account::REGISTER_STATUS['inforamtion'])){
            return apiReturn(-10003, '用户不存在！');
        }

        //验证密码
        if(!isset($this->Input['password'])){
            $error['errors'] = '密码不能为空';
            return apiReturn(-104, '数据验证失败', $error);
        }

        if(!(\Hash::check($this->Input['password'],$user->password))){
            return apiReturn(-10009, '密码不正确！');
        }

        //是否冻结
        if($user->status == Account::STATUS['disable']){
            return apiReturn(-10012, '账号已被禁用！');
        }

        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-10051, '该账号不是卖家！');
        }

        $seller = $user->AccountSeller;
        $business = $user->AccountInfo;
        $data['code'] = '0';
        $data['funong_trade_seller_id'] = $seller['id'];
        $data['funong_trade_business_id'] = $business['id'];
        $data['token'] = $this->Account::createToken($user);

        return $data;
    }


    /**
     * 关联卖家列表
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function relationAccountList()
    {

        $validator = $this->Validator::make($this->Input, [
            'seller_id' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $seller = $this->Account::getAccountSellerByIds($this->Input['seller_id'],$this->Input['page_size']);
        if(!is_null($seller)){
            $data = array();
            foreach ($seller as $k=>$v){
                $data['account'][] = $v->Account;
            }
        }

        return $seller;
    }


    /**
     * 搜索关联卖家
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchRelationAccountList()
    {

        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
            'where' => 'array|required',
            'where.status' => 'integer',
            'where.account_number' => 'string',
            'where.phone' => 'string',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为整数',
            'array' => ':attribute不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $seller = $this->Account::searchAccountSeller($this->Input['seller_id'],$this->Input['where'],$this->Input['page_size']);
        return $seller;
    }



/*------------erp商品-----------------*/
    /**
     * erp获取商品
     * @return \Illuminate\Http\JsonResponse
     */
    public function goodsList()
    {

        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'business_id' => 'required',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $goods = $this->Goods::getGoodsByBusinessId($this->Input['business_id'],$this->Input['page_size']);

        $data = array();
        if(!is_null($goods)){
            foreach ($goods as $k=>$v){
                $v->faces = getImgUrl(explode(',',$v->faces)[0],'goods_imgs','');
                $v->GoodsCategory;
            }
            $data = $goods;
        }

        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 搜索商品
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchGoods()
    {

        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'business_id' => 'required',
            'where' => 'array|required',
            'where.status' => 'integer',
            'where.review_status' => 'integer',
            'where.category_id' => 'integer',
            'where.seller_id' => 'integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $goods = $this->Goods::searchTradeGoods($this->Input['where'],$this->Input['page_size'],$this->Input['business_id']);
        $data = array();
        if(!is_null($goods)){
            foreach ($goods as $k=>$v){
                $v->faces = getImgUrl(explode(',',$v->faces)[0],'goods_imgs','');
            }
            $data = $goods;
        }
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 商品详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGoodsById()
    {
        $validator = $this->Validator::make($this->Input, [
            'goods_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $goods=$this->Goods::getGoodsById($this->Input['goods_id']);
        if(is_null($goods))
        {
            return apiReturn(-30000, '商品不存在！');
        }
        $data['goods']=$goods;
        //商品图片
        if(!empty($data['goods']['faces']))
        {
            $data['goods']['faces'] = getImgUrl(explode(',',$data['goods']['faces']),'goods_imgs','');
        }
        //商品视频
        if(!empty($data['goods']['vedios']))
        {
            $data['goods']['vedios'] = getImgUrl(explode(',',$data['goods']['vedios']),'goods_vedios','');
        }
        //商品提货地址
        if(!empty($data['goods']['delivery_address_id']))
        {
            $ids=explode(',',$data['goods']['delivery_address_id']);
            $data['goods']['delivery_address'] = $this->Address::getAddressByIds($ids);
        }
        $data['goods']['review_log']=json_decode($data['goods']['review_log']);
        $data['goods']['goods_attrs']=json_decode($data['goods']['goods_attrs']);
        $data['goods']['review_details']=json_decode($data['goods']['review_details']);
        return apiReturn(0,'获取成功',$data);
    }


/*------------erp报价-----------------*/

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function offerPatternList()
    {
        $result = $this->Offer::searchDistinctParam();
        return apiReturn(0,'获取成功',$result);
    }



    /**
     * 报价列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function offerList()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'business_id' => 'required',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->Offer::getOfferListById($this->Input['business_id'],$this->Input['page_size']);
        $data['offer_list']=$result;
        return apiReturn(0,'获取报价列表成功',$data);
    }


    /**
     * 搜索报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchOffer()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'business_id' => 'required',
            'where' => 'array|required',
            'where.status' => 'integer',
            'where.review_status' => 'integer',
            'where.seller_id' => 'integer',
            'where.offer_pattern_id' => 'integer',
            'where.delivery_address_id' => 'integer',
            'price_start' => '',
            'price_end' => '',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data['offer_list']=$this->Offer::searchOfferByBusinessId($this->Input['business_id'],$this->Input['where'],$this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 报价详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function goodsOfferDetail()
    {

        $validator = $this->Validator::make($this->Input, [
            'offer_id' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'offer_id' => '报价id',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $goods_offer = $this->Offer::getGoodsOfferById($this->Input['offer_id']);

        if(!is_null($goods_offer)){
            $goods_offer->good->faces = getImgUrl($goods_offer->good->faces,'goods_imgs','');;

        }
        $data['offer_detail'] = $goods_offer;

        if(!count($data['offer_detail'])){
            return apiReturn(-40010, '报价不存在！');
        }
        return apiReturn(0, '请求成功！',$data);
    }


    /**
     * 获取提货地址
     * @return \Illuminate\Http\JsonResponse
     */
    public function offerAddress()
    {
        $validator = $this->Validator::make($this->Input, [
            'goods_id' => 'required | integer',
        ], [
            'required' => '为必填项',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $goods = $this->Goods::getGoodsById($this->Input['goods_id']);
        if(is_null($goods)){
            return apiReturn(-40002, '商品不存在！');
        }

        $address = $this->Address::getAddressByIds($goods->delivery_address_id);
        $data['address_list'] = $address;
        return apiReturn(0, '请求成功！', $data);
    }


    /**
     * 获取报价模式
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOfferPattern()
    {

        $validator = $this->Validator::make($this->Input, [
            'category_id' => 'required | integer',
        ], [
            'required' => '为必填项',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $category = $this->Goods::getCategoryById($this->Input['category_id']);
        if(is_null($category)){
            return apiReturn(-40003, '品类不存在！');
        }

        $offer_pattern = $this->Offer::getOfferPatternByOfferId($category->offer_type);
        foreach ($offer_pattern as $k=>$v){
            $v->attribute;
        }
        return apiReturn(0, '请求成功！', $offer_pattern);
    }


    /**
     * 删除报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteGoodsOffer()
    {
        $validator = $this->Validator::make($this->Input, [
            'offer_id' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'number' => ':attribute为数字',
        ], [
            'offer_id' => '报价id',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        if($this->Offer::softDeleteGoodsOfferById($this->Input['offer_id'])){
            return apiReturn(0, '删除成功！');
        }

        return apiReturn(-40004, '报价删除失败！');
    }


    /**
     * 修改报价个别信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function modifyGoodsOffer()
    {

        $validator = $this->Validator::make($this->Input, [
            'action_type' => 'required',
            'offer_id' => 'array',
            'is_select_all' => 'required | integer',
            'delivery_starttime' => '',
            'delivery_endtime' => '',
        ], [
            'required' => ':attribute为必填项',
            'array' => ':attribute为数组',
            'integer' => ':attribute为整数',
        ], [
            'action_type' => '报价操作类型',
            'offer_id' => '报价id',
            'is_select_all' => '是否全选',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $action_type = $this->Input['action_type'];

        //添加库存
        if($action_type == 'ADD_STOCK'){
            $validator = $this->Validator::make($this->Input, [
                'stock' => 'required | numeric',
            ], [
                'required' => ':attribute为必填项',
                'numeric' => ':attribute为数字',
                'min' => ':attribute最小1件',
            ], [
                'stock' => '添加的库存数量',
            ]);

            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-104, '数据验证失败', $error);
            }
        }

        //修改价格
        if($action_type == 'EDIT_PRICE'){
            $validator = $this->Validator::make($this->Input, [
                'price' => 'required | numeric',
            ], [
                'required' => ':attribute为必填项',
                'numeric' => ':attribute为数字',
            ], [
                'price' => '价格',
            ]);
            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-104, '数据验证失败', $error);
            }
        }


        $total = 0;
        $fail = 0;
        //提货日期
        if($action_type == 'SET_TIME'){
            $validator = $this->Validator::make($this->Input, [
                'delivery_starttime' => 'required',
                'delivery_endtime' => 'required',
            ], [
                'required' => ':attribute为必填项',
                'numeric' => ':attribute为数字',
                'min' => ':attribute最小1件',
            ], [
                'delivery_starttime' => '提货日期',
                'delivery_endtime' => '提货日期',
            ]);

            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-104, '数据验证失败', $error);
            }

            $offers = $this->Offer::getAllOffer();
            foreach ($offers as $k=>$offer){
                $total++;
                $update_data['delivery_starttime'] = $this->Input['delivery_starttime'];
                $update_data['delivery_endtime'] = $this->Input['delivery_endtime'];
                $update_data['updated_at'] = $offer['updated_at'];
                if(!$this->Offer::updateOfferById($offer['id'],$update_data)){
                    $fail++;
                }
            }
            $data['total'] = $total;
            $data['fail'] = $fail;
            return apiReturn(0, '操作成功！',$data);
        }


        //验证操作类型
        if(!in_array($action_type,array('ON_SHELVES','OFF_SHELVES','ADD_STOCK','EDIT_PRICE','SOLD_OUT','SET_TIME'))){
            return apiReturn(-40004, '报价操作类型不正确');
        }

        if($this->Input['is_select_all'] == 1){
            $beginToday = date('Y-m-d H:i:s',mktime(0,0,0,date('m'),date('d'),date('Y')));
            $endToday = date('Y-m-d H:i:s',(mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1));
            $offers = $this->Offer::getOfferByBusinessId($this->Input['businesses_id'],$beginToday,$endToday);
        }else{
            if(!is_array($this->Input['offer_id'])){
                $this->Input['offer_id'] = explode(',',$this->Input['offer_id']);
            }
            $offers = $this->Offer::getGoodsOfferById($this->Input['offer_id']);
        }

        if(is_null($offers)){
            return apiReturn(-40001, '报价不存在！');
        }

        $data['offer_starttime'] = date('Y-m-d',time());
        $data['offer_endtime'] = date('Y-m-d H:i:s',strtotime(date('Y-m-d',strtotime('+1 day'))) - 23400);

        foreach ($offers as $k=>$offer){
            $data['delivery_starttime'] = $offer->delivery_starttime;
            $data['delivery_endtime'] = $offer->delivery_endtime;
            $total++;
            $content['msg_type'] = $this->Mqtt->msg_type['offer'];
            $content['price_msg_content'] = array();
            $price_content['id'] = $offer->id;

            if(($action_type == 'OFF_SHELVES') or ($action_type == 'SOLD_OUT')){
                $price_content['is_effective'] = 0;
            }else{
                $price_content['is_effective'] = 1;
            }

            $price_content['price'] = $offer->price;
            $price_content['stock'] = $offer->stock - $offer->lock_num;
            $content['price_msg_content']['price_content'] = $price_content;
            $content['price_msg_content']['price_msg_type'] = 2;

            //上架
            if($action_type == 'ON_SHELVES'){
                $data['status'] = GoodsOffer::STATUS['enable'];
                if(!$this->Offer::updateOfferById($offer->id,$data)){
                    $fail++;
                }
            }

            //下架
            if($action_type == 'OFF_SHELVES'){
                $data['status'] = GoodsOffer::STATUS['disable'];
                if($this->Offer::updateOfferById($offer->id,$data)){
                    $this->Mqtt->broadcast(json_encode($content));
                }else{
                    $fail++;
                }
            }

            //添加库存
            if($action_type == 'ADD_STOCK'){
                $data['stock'] = $offer->stock + $this->Input['stock'];
                if($data['stock'] < 0){
                    $fail++;
                }else{
                    if($this->Offer::updateOfferById($offer->id,$data)){
                        $data['stock'] -= $offer->lock_number;
                        $content['price_msg_content']['price_content']['stock'] = $data['stock'] ;
                        $this->Mqtt->broadcast(json_encode($content));
                    }else{
                        $fail++;
                    }
                }
            }


            //售完
            if($action_type == 'SOLD_OUT'){
                $data['stock'] = 0;
                if($this->Offer::updateOfferById($offer->id,$data)){
                    $content['price_msg_content']['price_content']['stock'] = $data['stock'] ;
                    $this->Mqtt->broadcast(json_encode($content));
                }else{
                    $fail++;
                }
            }

            //修改价格
            if($action_type == 'EDIT_PRICE'){
                $data['price'] = $offer->price + $this->Input['price'];
                if($data['price'] < 0){
                    $fail++;
                }else{
                    if($this->Offer::updateOfferById($offer->id,$data)){
                        $content['price_msg_content']['price_content']['price'] = $data['price'] ;
                        $this->Mqtt->broadcast(json_encode($content));
                    }else{
                        $fail++;
                    }
                }
            }
        }

        $data['total'] = $total;
        $data['fail'] = $fail;
        return apiReturn(0, '操作成功！',$data);
    }


    /**
     * 获取买家详情
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function getBuyerDetail()
    {

        $validator = $this->Validator::make($this->Input, [
            'id' => 'required | integer',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $buyer = $this->Account::getBuyerBuId($this->Input['id']);

        if(is_null($buyer)){
            return apiReturn(-10052, '买家不存在！');
        }

        $buyer->AccountBusiness;
        return $buyer;
    }


    /**
     * 获得卖家提货地址
     * @return \Illuminate\Http\JsonResponse
     */
    public function deliveryAddressList()
    {

        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'seller_id' => 'required | array',
            'page' => 'required | integer',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为整数',
            'array' => ':attribute必须为数组',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->Address::getAddressListBySellerId($this->Input['page_size'],$this->Input['seller_id']);
        $data['address_lists']=$result;
        return apiReturn(0,'获取地址列表成功',$data);
    }


    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function deliveryAddressListBySellerId()
    {

        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'seller_id' => 'required | integer',
            'page' => 'required | integer',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为整数',
            'array' => ':attribute必须为数组',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $result=$this->Address::getAddressesBySellerId($this->Input['page_size'],$this->Input['seller_id']);
        $data['address_lists']=$result;
        return apiReturn(0,'获取地址列表成功',$data);
    }


    /**
     * 搜索提货地址
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchDeliveryAddress()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'seller_id' => 'required | array',
            'page' => 'required | integer',
            'where' => 'array|required',
            'where.name' => 'string',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['address_list']=$this->Address::searchDeliveryAddressBysellerId($this->Input['where'],$this->Input['page_size'],$this->Input['seller_id']);
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 添加提货地址
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function addDeliveryAddress()
    {
        $validator = $this->Validator::make($this->Input, [
            'province' => 'required | integer',
            'seller_id' => 'required | array',
            'city' => 'required | integer',
            'name' => 'required',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ], [
            'province' => '省',
            'city' => '市',
            'name' => '提货地址描述',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        //组装参数
        $data = $this->Input;
        //获取完整地址
        $city = $this->Area->getFullAddress($this->Input);
        $full_address = $city.$this->Input['name'];
        $full_address = str_replace(' ','',$full_address);
        $data['address_details'] = $full_address;
        $locate = $this->Locate->addressLngLat($full_address);

        if(!$locate){
            $locate = $this->Locate->addressLngLat(str_replace(' ','',$city));
        }

        if($locate){
            $data['lng'] = $locate['lng'];
            $data['lat'] = $locate['lat'];
        }

        $sellers = $this->Account::getSellerByids($this->Input['seller_id']);

        if(is_null($sellers)){
            return apiReturn(-40020, '卖家不存在！');
        }

        foreach ($sellers as $seller){
            $business = $seller->Account->AccountInfo;
            $data['seller_id'] = $seller->id;
            $data['account_businesses_id'] = $business->id;
            $this->Address::create((array)$data);
        }
        return apiReturn(0, '添加商品地址成功！');
    }


    /**
     * 商品列表
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function goodsLists()
    {

        $validator = $this->Validator::make($this->Input, [
            'page' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $goods_lists = $this->Goods::getAllPassedGoods($this->Input['page']);
        return apiReturn(0, '请求成功！',$goods_lists);
    }


    /**
     * 微信合同列表
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function getWeiContractList()
    {
        $validator = $this->Validator::make($this->Input, [
            'page' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;
        $data['page_size'] = 20;

        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getWeiContractList, ['json'=>$data])->getBody()->getContents();
            $result = json_decode($result,true);

            if(!isset($result['data'])){
                return apiReturn(-40023, '合同获取失败！');
            }

            foreach ($result['data']['contract_list']['data'] as $k=>$v){
                $data['contract_list'][$k] = $this->formatResult($v);
            }

            $data['paginate'] = $result['data']['contract_list'];
            unset($data['paginate']['data']);
            return apiReturn(0, '请求成功 !',$data);

        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 统计用户
     * @return \Illuminate\Http\JsonResponse
     */
    public function countAccount()
    {

        $validator = $this->Validator::make($this->Input, [
            'days' => 'required | integer',
            'end_time' => '',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $where['start'] = date("Y-m-d",time());
        $where['end'] = date("Y-m-d",strtotime("+1 day",time()));
        $all = $this->Account::countAccount($where);

        //筛选的起始日期
        if(!isset($this->Input['end_time'])){
            $start_time = date("Y-m-d",strtotime("-".$this->Input['days']." day",time()));
        }else{
            $start_time = date("Y-m-d",strtotime("-".$this->Input['days']." day",$this->Input['end_time']));
            $where['end'] = date("Y-m-d",strtotime("+1 day",$this->Input['end_time']));
        }

        $len = (strtotime($where['end']) - strtotime($start_time)) / (60 * 60 *24);

        for ($i = 1; $i < $len; $i++) {
            $time = date("Y-m-d",strtotime("+".$i." day",strtotime($start_time)));
            $filter['start'] = $time;
            $filter['end'] = date("Y-m-d",strtotime("+1 day",strtotime($time)));
            $result[$i]['time'] = $time;
            $result[$i]['daily_add'] = $this->Account::filterCountAccount($filter);
            $result[$i]['dailt_sum'] = $this->Account::dailyAccountSum($filter['end']);
        }

        sort($result);
        $data['count'] = $all;
        $data['daily_count'] = $result;

        return apiReturn(0, '请求成功 !',$data);
    }

    /**
     * 统计订单状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function countOrderStatus()
    {
        $validator = $this->Validator::make($this->Input, [
            'limit_date' => 'required'
        ], [
            'required' => '为必填项'
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data = $this->Input;
        $temp_date_arry = explode('-', $data['limit_date']);
        $start_time = date('Y-m-d 00:00:00', strtotime($temp_date_arry[0]));
        $end_time = date('Y-m-d 00:00:00', strtotime($temp_date_arry[1] . ' +1 day'));
        //查询
        $resource = $this->Order::getOrderStatus($start_time, $end_time);
        $new_data = [];
        foreach ($resource as $k => $v) {
            $v = json_decode(json_encode($v), true);
            $goods_info = json_decode($v['goods_info'], true);
            $new_goods_info = [];
            foreach ($goods_info as $kk => $vv) {
                $english_name = isset($vv['english_name']) ? $vv['english_name'] : 'null';
                $new_goods_info[$english_name] = isset($vv['default_value']) ? $vv['default_value'] : 'null';
            }
            $v['goods_info'] = (isset($new_goods_info['product_area']) ? $new_goods_info['product_area'] : '')
                            . (isset($new_goods_info['brand']) ? $new_goods_info['brand'] : '');
            $v['created_at'] = date('Ymd', strtotime($v['created_at']));
            if ($v['order_status'] == 1) {
                $v['order_status'] = 2;
            }
            if (!isset($new_data[$v['created_at']][$v['goods_info']][$v['order_status']]['order_count'])) {
                $new_data[$v['created_at']][$v['goods_info']][$v['order_status']]['order_count'] = 1;
            } else {
                $new_data[$v['created_at']][$v['goods_info']][$v['order_status']]['order_count'] += 1;
            }
            if (!isset($new_data[$v['created_at']][$v['goods_info']][$v['order_status']]['goods_count'])) {
                $new_data[$v['created_at']][$v['goods_info']][$v['order_status']]['goods_count'] = $v['num'];
            } else {
                $new_data[$v['created_at']][$v['goods_info']][$v['order_status']]['goods_count'] += $v['num'];
            }
        }
        $return_new_data = [];
        foreach ($new_data as $k => $v) {
            $return_new_data[$k]['date'] = date('Y-m-d', strtotime($k));
            foreach ($v as $kk => $vv) {
                $return_new_data[$k]['list'][$kk]['name'] = $kk;
                $index_list = [0 => 'pending', 2 => 'processed', 3 => 'cancel'];
                foreach ($index_list as $tk => $tv) {
                    if (isset($vv[$tk])) {
                        $return_new_data[$k]['list'][$kk][$tv] = $vv[$tk];
                    } else {
                        $return_new_data[$k]['list'][$kk][$tv] = [
                            'order_count' => 0,
                            'goods_count' => 0
                        ];
                    }
                }
            }
        }
        foreach ($return_new_data as $k => $v) {
            $return_new_data[$k]['list'] = array_values($v['list']);
        }
        return apiReturn(0, '请求成功 !', array_values($return_new_data));
    }

    /**
     * 采购销售平衡统计
     * @return \Illuminate\Http\JsonResponse
     */
    public function countContractNum()
    {

        $validator = $this->Validator::make($this->Input, [
            'days' => 'required | integer',
            'end_time' => '',
            'is_export' => '',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->countContract, ['json'=>$data])->getBody()->getContents();
            $result = json_decode($result,true);

            if(!isset($result['data'])){
                return apiReturn(-40023, '获取失败！');
            }

            return $result;

        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 采购销售平衡统计
     * @return \Illuminate\Http\JsonResponse
     */
    public function countDeposit()
    {

        $validator = $this->Validator::make($this->Input, [
            'days' => 'required | integer',
            'end_time' => '',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->countDeposit, ['json'=>$data])->getBody()->getContents();
            $result = json_decode($result,true);

            if(!isset($result['data'])){
                return apiReturn(-40023, '获取失败！');
            }

            return $result;

        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 销售开单统计
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function countSellOrder()
    {

        $validator = $this->Validator::make($this->Input, [
            'days' => 'required | integer',
            'end_time' => '',
            'kind' => '',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->countSellOrder, ['json'=>$data])->getBody()->getContents();
            $result = json_decode($result,true);

            if(!isset($result['data'])){
                return apiReturn(-40023, '获取失败！');
            }

            return $result;

        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 统计盈亏
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function countProfitAndLoss()
    {

        $validator = $this->Validator::make($this->Input, [
            'type' => 'required',
            'start' => '',
            'end' => '',
        ], [
            'required' => '为必填项',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->countProfitAndLoss, ['json'=>$data])->getBody()->getContents();
            $result = json_decode($result,true);

            if(!isset($result['data'])){
                return apiReturn(-40023, '获取失败！');
            }

            return $result;

        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 获取用户unionid
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomerUnionId()
    {
        $validator = $this->Validator::make($this->Input, [
            'id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $business = $this->Account::getBusinessById($this->Input['id']);

        if(!count($business)){
            return apiReturn(-51000, '数据获取失败！');
        }

        $account = $business->Account;
        if(!count($account)){
            return apiReturn(-51000, '数据获取失败！');
        }

        return apiReturn(0, '获取成功！',$account->unionid);
    }


    /**
     * 销售开单统计
     * @return \Illuminate\Http\JsonResponse|mixed|string
     */
    public function countPurchaseOrder()
    {

        $validator = $this->Validator::make($this->Input, [
            'days' => 'required | integer',
            'end_time' => '',
            'kind' => '',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->countPurchaseOrder, ['json'=>$data])->getBody()->getContents();
            $result = json_decode($result,true);

            if(!isset($result['data'])){
                return apiReturn(-40023, '获取失败！');
            }

            return $result;

        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }

    /**
     * 基差采销平衡表
     */
    public function countPurchaseSale()
    {
        $validator = $this->Validator::make($this->Input, [
            'days' => 'required | integer',
            'end_time' => '',
            'type' => 'required',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data = $this->Input;
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getCountPurchaseSale, ['json'=>$data])->getBody()->getContents();
            $result = json_decode($result,true);
            if(!isset($result['data'])){
                return apiReturn(-40023, '获取失败！');
            }
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }

    /**
     * 合同变更统计表
     */
    public function countChangeContract()
    {
        $validator = $this->Validator::make($this->Input, [
            'limit_date' => 'required',
            'type' => 'required',
        ], [
            'required' => '为必填项'
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data = $this->Input;
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->getCountChangeContract, ['json'=>$data])->getBody()->getContents();
            $result = json_decode($result,true);
            if(!isset($result['data'])) {
                return apiReturn(-40023, '获取失败！');
            }
            return $result;
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 高级搜索司机列表
     * @return JsonResponse|mixed|string
     */
    public function getDriverInfo()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);
        $business = $this->Account::getAccountBusinessByAccountId($user->id);
        $data['trade_business_id'] = $business->id;
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->searchDriver, ['json'=>$data])->getBody()->getContents();
            $return_data['driver_list'] = json_decode($result,true);
            return apiReturn(0,'获取成功',$return_data);
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }


    /**
     * 高级搜索合同
     * @return JsonResponse|mixed|string
     */
    public function searchContract()
    {
        $validator = $this->Validator::make($this->Input, [
            'verify_type' => 'required',
            'time_start' => '',
            'time_end' => '',
            'type' => '',
            'id' => '',
        ], [
            'required' => '为必填项'
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data = $this->Input;
        try{
            $client = new Client();
            $result = $client->request('post', $this->url.$this->searchContract, ['json'=>$data])->getBody()->getContents();
            $return_data['contract'] = json_decode($result,true);
            return apiReturn(0,'获取成功',$return_data);
        }catch (\Exception $exception){
            return apiReturn(-51000, '数据获取失败！');
        }
    }



    /**
     * 刷新用户token
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        $data = $this->Input;
        $business = $this->Account::getBusinessById($data['funong_trade_business_id']);
        $account = $business->Account;
        $data['token'] = $this->Account::createToken($account);
        return apiReturn(0, '刷新成功', $data);
    }



    public function addTest()
    {

        $result = $this->Account::getAllTemp();

        $i = 0;
        foreach ($result as $k=>$v){

            DB::beginTransaction();
            //添加account数据
            $data['id'] = $v->id;
            if(!empty($v->account_number)){
                $data['account_number'] = $v->account_number;
            }

            $data['phone'] = $v->phone;
            $data['password'] = bcrypt($v->password);
            $data['account_type'] = $v->identity;
            $data['status'] = $v->status;
            $data['register_status'] = Account::REGISTER_STATUS['inforamtion'];
            $data['role'] = 0;
            $data['nickname'] = $v->name;
            $data['public_openid'] = $v->openid;
            $result = $this->Account::createAccount($data);

            ////添加account_iunfo数据
            $info_data['id'] = $result->id;
            $info_data['account_id'] =$result->id;
            $info_data['type'] =0;
            $info_data['name'] = $v->name;
            $info_data['contact_name'] =$v->name;
            $info_data['contact_phone'] =$v->phone;
            $info_data['review_status'] =1;
            $info_data['address'] = $v->address;
            $info_data['address_details'] = $v->address;
            $info_result = $this->Account::createAccountInfo($info_data);
//            $info_data['province'] = $this->Input['province'];
//            $info_data['city'] = $this->Input['city'];
//            $info_data['county'] = $this->Input['county'];


            $identity_info['account_id'] = $data['id'];
            $identity_info['account_business_id'] = $info_result->id;
            if($data['account_type'] ==Account::ACCOUNT_TYPE['buyer']){

                if(!is_null($this->Account::getBuyerByAccountId($data['id']))){
                    $identity_result = $this->Account::updateBuyerByAccountId($data['id'],$identity_info);
                }else{
                    $identity_result = $this->Account::createByuer($identity_info);
                }
            }

            if($data['account_type'] ==Account::ACCOUNT_TYPE['seller']){

                $identity_info['release_type'] = $v->is_self;
                $identity_info['quote_type'] = $v->is_self;
                if(!is_null($this->Account::getSellerByAccountId($data['id']))){
                    $identity_result = $this->Account::updateSellerByAccountId($data['id'],$identity_info);
                }else{
                    $identity_result = $this->Account::createSeller($identity_info);
                }
            }


            if ($result && $info_result && $identity_result) {
                DB::commit();
            } else {
                DB::rollBack();
                return apiReturn(-9999, '操作异常');
            }

            $i++;
            echo '成功'.$i.'条';
        }
    }


    public function getUnionid()
    {

        $result = $this->Account::getAllAccounts();
        $i = 0;
        foreach ($result as $k=>$v){
            if($v->id >= 4872){
                if(!empty($v->public_openid)){
//                return $v->public_openid;
                    $url = "http://redis.efunong.com:8000";

                    $data = array(
                        "act"=>'getUserInfo',
                        "appId"=>'wx275c7b7becf5e41a',
                        "openid"=>$v->public_openid   #改这里就可以了
//                    "openid"=>'oPJay0wPcDoQIVc_aSGmj6nfMpxo'   #改这里就可以了
                    );

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL,$url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
                    if(!empty($data)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $temp = curl_exec($ch);
                    curl_close($ch);

                    $info = json_decode($temp,true);
                    $update_data['unionid'] = $info["unionid"];
                    $update_data['public_appid'] = 'wx275c7b7becf5e41a';
                    $this->Account::updateAccountById($v->id,$update_data);
                    $i++;
                }
            }



        }
        return $i;
    }


    public function getAllSelfTemp()
    {
        $result = $this->Account::getAllSelfTemp();

        $ids = array();
        foreach ($result as $k=>$v){
            $ids[] = $v->id;
        }

        $accounts = $this->Account::getAccountsByIds($ids);
        $address = $this->Address::getAllAddress();

        foreach ($accounts as $k=>$v){
            if($v->id != 43){
                $seller = $v->AccountSeller;
                foreach ($address as $key=>$val){
                    $val = objectToArray($val);
                    $insert_data = $val;
                    $insert_data['seller_id'] = $seller->id;
                    $insert_data['account_businesses_id'] = $seller->account_business_id;
                    $this->Address::create($insert_data);
                }
            }
        }

//        $goods = $this->Goods::getGoodsListsBySellerId(61);

//        foreach ($accounts as $k=>$v){
//            if($v->id != 43){
//                $seller = $v->AccountSeller;
//                foreach ($goods as $key=>$val){
//                    $val = objectToArray($val);
//                    $insert_data = $val;
//                    $insert_data['seller_id'] = $seller->id;
//                    $insert_data['account_business_id'] = $seller->account_business_id;
//                    $this->Goods::createGoods($insert_data);
//                }
//            }
//        }

        return 1;
    }


    public function offerTeach()
    {
        return view('external.offerTeach');
    }


    /**
     * 买家变卖家
     * @return \Illuminate\Http\JsonResponse|int
     */
    public function transSellerToBuyer()
    {
        $result = $this->Account::getAllSelfTemp();

        $i = 0;
        foreach ($result as $k=>$v){

            $account = $this->Account::getAccountById($v->id);
            if($account->account_type == 1){
                DB::beginTransaction();
                $account_data['account_type'] = 0;
                $account_data['nickname'] = $v->name;
                $account_result = $this->Account::updateAccountById($v->id,$account_data);

                $business_data['name'] = $v->name;
                $business_data['contact_name'] = $v->name;
                $business_data['contact_phone'] = $v->phone;
                $business_data['legal_cn_id'] = $v->id_card;
                $business_result = $this->Account::updateAccountInfoById($v->id,$business_data);

                $seller = $this->Account::getSellerByAccountId($v->id);
                $seller_result = $this->Account::delSeller($seller->id);

                $identity_info['account_id'] = $v->id;
                $identity_info['account_business_id'] = $v->id;
                $buyer_result = $this->Account::createByuer($identity_info);

                if ($account_result && $seller_result && $buyer_result &&$business_result) {
                    DB::commit();
                } else {
                    DB::rollBack();
                    return apiReturn(-9999, '操作异常');
                }
                $i++;
            }
        }

        return $i;
    }


    public function addNames()
    {

        $result = $this->Account::getAllSelfTemp();
        $i = 0;
        foreach ($result as $k=>$v){
            $account = $this->Account::getAccountsByPhone($v->phone);
            if(count($account)){
                $i++;
                $account = $account[0];
                $business = $this->Account::getAccountBusinessByAccountId($account->id);

                $account_data['nickname'] = $v->name;
                $business_data['name'] = $v->name;
                $business_data['contact_name'] = $v->name;

//                DB::beginTransaction();
                $account_result = $this->Account::updateAccountById($account->id,$account_data);
                $business_result = $this->Account::updateAccountInfoById($business->id,$business_data);
//                if ($account_result && $business_result) {
//                    DB::commit();
//                } else {
//                    DB::rollBack();
//                    return apiReturn(-9999, '操作异常');
//                }
                echo $i;
            }

        }

        return $i;
    }

    /**
     * 获取用户标签
     */
    public function getUserTags()
    {
        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);
        //是否为买家
        if($user->account_type == Account::ACCOUNT_TYPE['buyer']){
            $param = [
                'user_id' => $user->id
            ];
            try{
                $client = new Client();
                $result = $client->request('post', $this->url.$this->getUserTags, ['json'=>$param])->getBody()->getContents();
                $result = json_decode($result,true);
                return $result;
            }catch (\Exception $exception){
                return [];
            }
        }
        return [];
    }

}
