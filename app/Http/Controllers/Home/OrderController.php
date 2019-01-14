<?php

namespace App\Http\Controllers\Home;

use App\Services\AccountService;
use App\Services\AddressService;
use App\Services\GoodsService;
use App\Services\OfferService;
use App\Services\AddressLocateService;
use App\Services\OrderService;;
use App\Http\Controllers\Admin\CommonController;

use function GuzzleHttp\Psr7\str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Services\MqttService;
use Illuminate\Support\Facades\Storage;

use App\Order;
use App\GoodsCategory;
use App\GoodsOfferPattern;
use App\Account;
use App\AccountBusiness;


class OrderController extends BaseController
{
    
    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Account;
    protected $Validator;
    protected $Address;
    protected $Goods;
    protected $Offer;
    protected $Locate;
    protected $Order;
    protected $OfferController;
    protected $Common;
    protected $Mqtt;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AccountService $Account,
        Validator $validator,
        AddressService $address,
        GoodsService $goods,
        AddressLocateService $Locate,
        OfferService $offer,
        OrderService $order,
        OfferController $offerController,
        CommonController $common,
        MqttService $mqtt
    )
    {
        parent::__construct($request, $log, $redis);
        $this->Account = $Account;
        $this->Validator = $validator;
        $this->Address = $address;
        $this->Goods = $goods;
        $this->Offer = $offer;
        $this->Locate = $Locate;
        $this->Order = $order;
        $this->OfferController = $offerController;
        $this->Common = $common;
        $this->Mqtt = $mqtt;
    }


    /**
     * 提交订单
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitOrder()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $buyer_account = $this->Account::getAccountById($employee->super_id);
            if(!$buyer_account){
                return apiReturn(-30007, '账户异常，无法购买！');
            }
            $account_buyer = $buyer_account->AccountBuyer;
            $buyer_business = $buyer_account->AccountInfo;
        }else{
            $account_buyer = $user->AccountBuyer;
            $buyer_business = $user->AccountInfo;
        }


        //判断是否为买家
        if(empty($account_buyer)){
            return apiReturn(-40017, '您不是买家，无法购买！');
        }

        //是否通过审核
//        if($buyer_business->review_status != AccountBusiness::REVIEW_STATUS['passed']){
//            return apiReturn(-30004, '您还未通过审核，无法购买商品！');
//        }

        $validator = $this->Validator::make($this->Input, [
            'offer_id' => 'required',
            'discount_price' => 'numeric',
            'id_card' => '',
            'num' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'offer_id' => '报价id',
            'id_card' => '身份证号',
            'num' => '数量',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

//        if(isset($this->Input['id_card'])){
//            if(!isChinaId($this->Input['id_card'])){
//                return apiReturn(-40050, '身份证号不正确');
//            }
//        }

        $goods_offer = $this->Offer::getGoodsOfferById($this->Input['offer_id']);

        if(empty($goods_offer)){
            return apiReturn(-40001, '报价不存在');
        }

        //获取品类交易时间
        $goods = $goods_offer->Goods;
        $goods_category = $goods->GoodsCategory;
        $goods_category_trade = $this->Goods::getGoodsCategoryTradeByCategoryId($goods->category_id);

        //是否在报价有效期内
        if( (time() < strtotime($goods_offer->offer_starttime)) or (time() > strtotime($goods_offer->offer_endtime))){
            return apiReturn(-40011, '不在交易日内');
        }

        if( (time()) > (strtotime($goods_offer->delivery_endtime)) ){
            return apiReturn(-40011, '不在交易日内');
        }

        if(!is_null($goods_category_trade)){

            //判断是否在交易年
            if($goods_category_trade->start_time and $goods_category_trade->end_time){
                $start_time = strtotime($goods_category_trade->start_time);
                $end_time = strtotime($goods_category_trade->end_time);

                if( (time() < $start_time) or (time() > $end_time)){
                    return apiReturn(-40010, '不在交易年限内');
                }
            }

            //判断是否在交易日内
            if($goods_category_trade->trading_day){
                if(strpos($goods_category_trade->trading_day,date('w')) === false){
                    return apiReturn(-40011, '不在交易日内');
                }
            }

            //判断是否在交易时间段
            if(!empty($goods_category_trade->time_slot)){
                $i = 0;
                foreach (json_decode($goods_category_trade->time_slot,true) as $k=>$v){
                    if( ( strtotime(date('H:i:s',time())) >= strtotime($v['start_time']) ) and (strtotime(date('H:i:s',time())) <= strtotime($v['end_time']))){
                        $i +=1;
                    }
                }

                if($i ==0){
                    return apiReturn(-40012, '不在交易时间段内');
                }
            }

        }

        if($this->Input['num'] <= 0){
            return apiReturn(-40013, '购买数量必须大于等于1');
        }

        $overplus_stock_key = 'goods_offer_overplus_stock';
        try {
            $overplus_stock = $this->Redis::get($overplus_stock_key);
        } catch (\Exception $exception) {
        }

        //判断系统库存
        if(isset($overplus_stock)){
            if($overplus_stock){
                if($overplus_stock < $this->Input['num']){

                    $msg_data['data'] = array(
                        'data' => array (
                            'first'    => array('value' => "系统通知"),
                            'keyword1' => array('value' => "平台库存不足,剩余".$overplus_stock.'吨'),
                            'keyword2' => array('value' => "报价"),
                            'keyword3' => array('value' => ''),
                            'remark'   => array('value' => "\n请及时处理！")
                        )
                    );
                    $msg_data['action'] = "productChange";
                    $this->Common->socketMessage($msg_data);
                    return apiReturn(-40014, '平台库存不足');
                }
            }
        }

        //判断是否满足购买条件
        if($goods_offer->single_number != -1){
            if($this->Input['num'] > $goods_offer->single_number){
                return apiReturn(-40015, '购买数量超过限购量'.$goods_offer->single_number);
            }
        }

        //是否满足每单的量
        if($goods_offer->moq_number != -1){
            if($this->Input['num'] < $goods_offer->moq_number){
                return apiReturn(-40016, '购买数量必须大于'.$goods_offer->moq_number);
            }
        }

        //单笔限购
        if($this->Input['num'] > config('ext.single_num')){
            return apiReturn(-40014, '每单限购'.config('ext.single_num').'吨！');
        }

        //下单时间间隔
        $last_order = $this->Order::getLastOrderByBuyerBunsinessId($buyer_business->id);
        if(count($last_order)){
            if(time() - (strtotime($last_order->created_at)) < config('ext.time_interval')){
                return apiReturn(-40014, '请勿频繁下单！');
            }
        }

        //拼装参数
        $seller = $this->Account::getAccountSellerById($goods_offer->seller_id);
        $seller_account = $seller->Account;
        $seller_business = $seller_account->AccountInfo;
        $goods_offer_pattern = $goods_offer->goodsOfferPattern;

        //判断库存
        if( ($goods_offer->stock) <   $this->Input['num']){
            $this->Mqtt->sendCommonMsg('offer','您的报价库存不足，请及时处理!',$goods_offer->id,$seller_account->id);
            return apiReturn(-40014, '库存不足');
        }

        if($goods->faces){
            $data['image'] = explode(',',$goods->faces)[0];
        }

        $data['discount_price'] = 0;
        if(isset($this->Input['discount_price'])){
            $data['discount_price'] = $this->Input['discount_price'];
        }

        if($employee){
            $data['account_employee_id'] = $employee->id;
        }

        $data['order_number'] = generateNumber('D');
        $data['goods_name'] = $goods['name'];
        $data['goods_offer_id'] = $this->Input['offer_id'];
        $data['account_buyer_id'] = $account_buyer['id'];
        $data['buyer_name'] = $buyer_business['name'];
        $data['category_name'] = $goods_category['name'];
        $data['account_seller_id'] = $seller['id'];
        $data['seller_name'] = $seller_business['name'];
        $data['offer_name'] = $goods_offer_pattern['name'];
        $data['order_unit'] = $goods_offer['order_unit'];
        $data['price'] = $goods_offer['price'];
        $data['address_details'] = $goods_offer['address_details'];
        $data['lng'] = $goods_offer['lng'];
        $data['lat'] = $goods_offer['lat'];
        $data['delivery_starttime'] = $goods_offer['delivery_starttime'];
        $data['delivery_endtime'] = $goods_offer['delivery_endtime'];
        $data['offer_info'] = $goods_offer['offer_info'];
        if(empty($data['offer_info'])){
            $data['offer_info'] = '[]';
        }
        $data['goods_info'] = $goods['goods_attrs'];
        $data['num'] = $this->Input['num'];
        $data['total_price'] = $data['price'] * $data['num'] - $data['discount_price'];
        $data['source'] = $this->Request->header('system');

        if(isset($this->Input['id_card'])){
            $data['id_card'] = $this->Input['id_card'];
        }

        $data['buyer_business_id'] = $buyer_business->id;
        $data['goods_id'] = $goods->id;
        $offer_data['lock_number'] = $goods_offer['lock_number'] + $this->Input['num'];
        $offer_data['stock'] = $goods_offer['stock'] - $this->Input['num'];

        $offer_params = $this->OfferController->abuttedParam($data['goods_info']);
        DB::beginTransaction();

        $order_result = $this->Order::create($data);
        //更新锁定量
        $goods_offer_result = $this->Offer::updateOfferById($this->Input['offer_id'],$offer_data);

        if ($order_result && $goods_offer_result) {

            //更新系统库存
            if(isset($overplus_stock)){
                $overplus_stock -= $this->Input['num'];
                $this->Redis::set($overplus_stock_key, $overplus_stock);
            }

            DB::commit();
            $return_data['order_number'] = $data['order_number'];

            //发送微信通知
            $msg_data['data'] = array(
                'data' => array (
                    'first'    => array('value' => "来自应用：%1\$s\n提交时间：".date('Y-m-d H:i:s',time())."\n卖家信息：".$data['seller_name']."，".$seller_account->phone.""),
                    'keyword1' => array('value' => $data['order_number']),
                    'keyword2' => array('value' => "".$offer_params."：".$data['address_details'].""),
                    'keyword3' => array('value' => $data['price'].','.$data['num'].$data['order_unit']),
                    'keyword4' => array('value' => $data['offer_name']),
                    'keyword5' => array('value' => $buyer_business->name.','.$user->phone),
                    'remark'   => array('value' => "\n请及时进行处理！")
                )
            );
            $msg_data['action'] = "orderCreate";
            $this->Common->socketMessage($msg_data);

            //记录身份证号
            if(isset($data['id_card'])){
                $business_data['legal_cn_id'] = $data['id_card'];
                $this->Account::updateAccountInfoById($buyer_business->id,$business_data);
            }

            $this->Mqtt->sendCommonMsg('order','您有一笔新的订单，请及时处理!',$order_result->id,$seller_account->id);
            return apiReturn(0, '请求成功 !',$return_data);
        } else {
            DB::rollBack();
        }

        return apiReturn(-40019, '下单失败');
    }
    

    /**
     * 订单列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderList()
    {

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

        $page_size = Order::PAGE_NUM;

        if($employee){
            $order_lists = $this->Order::getOrderByAccountEmployeeIdAndType($employee->id,$page_size);
        }else{
            $order_lists = $this->Order::getOrderByBuyerIdAndType($account_buyer->id,$page_size);
        }

        $data['order_list'] = array();
        if($order_lists){
            foreach ($order_lists as $order_list_key=>$order_list_val){
                $data['order_list'][$order_list_key] = $order_list_val;
                $data['order_list'][$order_list_key]['updated_time'] = date('Y-m-d H:i',strtotime($order_list_val->updated_at));
                //获取图片
                if(!empty($order_list_val->image)){
                    $data['order_list'][$order_list_key]['image'] = getImgUrl(explode(',',$order_list_val->image)[0],'goods_imgs','');
                }

                //商品参数
                $data['order_list'][$order_list_key]['goods_params'] = $this->OfferController->abuttedParam($order_list_val->goods_info);

                //报价参数
                $data['order_list'][$order_list_key]['offer_params'] = $this->OfferController->abuttedParam($order_list_val->offer_info);

                //豆粕 商品名显示
                $goods_attr_list = $this->OfferController->paramList($order_list_val->goods_info);
                $offer_attr_list = $this->OfferController->paramList($order_list_val->offer_info);

                //豆粕
                $data['order_list'][$order_list_key]['goods_name'] = $order_list_val->goods_name;
                if($order_list_val->category_name == GoodsCategory::NAME['soybean_meal']){
                    $data['order_list'][$order_list_key]['goods_name'] = $this->OfferController->isSoybeanMeal($goods_attr_list).$order_list_val->order_unit;
                }

                //基差
                if($order_list_val->offer_name == GoodsOfferPattern::OFFER_PATTERN['basis_price']){
                    $data['order_list'][$order_list_key]['price'] = $this->OfferController->isBasisPrice($offer_attr_list).'+'.$order_list_val->price;
                }else{
                    $data['order_list'][$order_list_key]['price'] = '¥'.$order_list_val->price;
                }

                unset($data['order_list'][$order_list_key]['offer_info']);
                unset($data['order_list'][$order_list_key]['goods_info']);
                unset($data['order_list'][$order_list_key]['created_at']);
                unset($data['order_list'][$order_list_key]['updated_at']);
            }
        }

        $data['paginate'] = pageing($order_lists);
        return apiReturn(0, '请求成功 !',$data);
    }


    /**
     * 取消订单
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelOrder()
    {

        $validator = $this->Validator::make($this->Input, [
            'order_id' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'order_id' => '订单号',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        if($user->account_type == Account::ACCOUNT_TYPE['buyer']){
            //判断是否为员工账户
            $employee = $user->accountEmployee;

            if(!empty($employee)){
                $buyer_account = $this->Account::getAccountById($employee->super_id);
                $account_buyer = $buyer_account->AccountBuyer;
            }else{
                $account_buyer = $user->AccountBuyer;
            }

            //根据身份获取订单
            if($employee){
                $order = $this->Order::getOrderByIdAndEmployeeId($this->Input['order_id'],$employee->id);
            }else{
                $order = $this->Order::getOrderByIdAndBuyerId($this->Input['order_id'],$account_buyer->id);
            }

            $reson = '买家取消';
        }


        if($user->account_type == Account::ACCOUNT_TYPE['seller']){
            $employee = $user->accountEmployee;
            if(!empty($employee)){
                $seller_account = $this->Account::getAccountById($employee->super_id);
                $account_seller = $seller_account->AccountSeller;
            }else{
                $account_seller = $user->AccountSeller;
            }

            if($employee){
                $order = $this->Order::getOrderByIdAndEmployeeId($this->Input['order_id'],$employee->id);
            }else{
                $order = $this->Order::getOrderByIdAndSellerId($this->Input['order_id'],$account_seller->id);
            }

            $reson = '卖家取消';
        }


        if(empty($order)){
            return apiReturn(-40018, '订单不存在！');
        }

        if($order->order_status >= Order::ORDER_STATUS['finished']){
            return apiReturn(-50003, '订单无法作废！');
        }

        if($order->operation_status >= Order::OPERATION_STATUS['cashing']){
            return apiReturn(-50003, '订单无法作废！');
        }

        if($user->account_type == Account::ACCOUNT_TYPE['buyer']){
            if(time() - strtotime($order->created_at)  > (config('ext.order_cancel_time'))){
                return apiReturn(-50003, '订单无法作废！');
            }
        }


        $offer_data['lock_number'] = $order->goodsOffer->lock_number - $order->num;
        if($offer_data['lock_number'] < 0){
            $offer_data['lock_number'] = 0;
        }
        if ($order->goodsOffer->stock > 0) {
            $offer_data['stock'] = $order->goodsOffer->stock + $order->num;
        }
        $order_data['order_status'] = Order::ORDER_STATUS['disable'];

        $order_seller = $this->Account::getSellerByid($order->account_seller_id);
        $order_seller_business = $this->Account::getBusinessById($order_seller->account_business_id);
        $order_buyer_business = $this->Account::getBusinessById($order->buyer_business_id);

        $overplus_stock_key = 'goods_offer_overplus_stock';
        try {
            $overplus_stock = $this->Redis::get($overplus_stock_key);
        } catch (\Exception $exception) {
        }

        //取消锁定量 删除订单
        DB::beginTransaction();

        //更新订单
        $order_result = $this->Order::updateOrder($this->Input['order_id'],$order_data);
        //更新锁定量
        $goods_offer_result = $this->Offer::updateOfferById($order->goods_offer_id,$offer_data);

        if ($order_result && $goods_offer_result) {

            //更新系统库存
            if(isset($overplus_stock)){
                if($overplus_stock){
                    $overplus_stock += $order->num;
                    $this->Redis::set($overplus_stock_key, $overplus_stock);
                }
            }

            DB::commit();

            $offer_params = $this->OfferController->abuttedParam($order->goods_info);
            //发送微信通知
            $msg_data['data'] = array(
                'data' => array (
                    'first'    => array('value' => "卖家信息：".$order_seller_business->name."，".$order_seller_business->contact_phone."\n买家信息：".$order_buyer_business->name."，".$order_buyer_business->contact_phone),
                    'keyword1' => array('value' => $order->order_number."\n,".$order->price.','.$order->num.$order->order_unit),
                    'keyword2' => array('value' => date('Y-m-d H:i:s',strtotime($order->created_at))),
                    'keyword3' => array('value' => date('Y-m-d H:i:s',time())),
                    'keyword4' => array('value' => $reson."\n商品参数:".$offer_params),
                    'remark'   => array('value' => "\n请中断处理！")
                )
            );

            $msg_data['action'] = "orderCancel";
            $this->Common->socketMessage($msg_data);

            return apiReturn(0, '请求成功 !');
        } else {
            DB::rollBack();
        }

        return apiReturn(-40021, '订单取消失败！');
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
     * 订单详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderDetail()
    {

        $validator = $this->Validator::make($this->Input, [
            'order_id' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'order_id' => '订单号',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $order = $this->Order::getOrderById($this->Input['order_id']);

        if(empty($order)){
            return apiReturn(-40018, '订单不存在！');
        }

        $data = array();
        $order_data = $order;
        $order_data['updated_time'] = date('Y-m-d H:i',strtotime($order->updated_at));
        $order_data['delivery_starttime'] = $order->delivery_starttime;
        $order_data['delivery_endtime'] = $order->delivery_endtime;

        //获取图片
        if(!empty($order->image)){
            $order_data['image'] = getImgUrl(explode(',',$order->image)[0],'goods_imgs','');
        }

        //商品参数
        $order_data['goods_params'] = $this->OfferController->abuttedParam($order->goods_info);

        //报价参数
        $order_data['offer_params'] = $this->OfferController->abuttedParam($order->offer_info);

        //豆粕 商品名显示
        $goods_attr_list = $this->OfferController->paramList($order->goods_info);
        $offer_attr_list = $this->OfferController->paramList($order->offer_info);

        //豆粕
        $order_data['goods_name'] = $order->goods_name;
        if($order->category_name == GoodsCategory::NAME['soybean_meal']){
            $order_data['goods_name'] = $this->OfferController->isSoybeanMeal($goods_attr_list).$order->order_unit;
        }

        //基差
        if($order->offer_name == GoodsOfferPattern::OFFER_PATTERN['basis_price']){
            $order_data['price'] = $this->OfferController->isBasisPrice($offer_attr_list).'+'.$order->price;
        }else{
            $order_data['price'] = '¥'.$order->price;
        }

        $data['order_detail'] = $order_data;
        unset($data['order_detail']['offer_info']);
        unset($data['order_detail']['goods_info']);
        return apiReturn(0, '请求成功 !',$data);
    }


    /**
     * 待接单订单列表（卖家）
     * @return \Illuminate\Http\JsonResponse
     */
    public function receiptOrderList()
    {

        $validator = $this->Validator::make($this->Input, [
            'order_status' => 'required | numeric',
            'page' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'order_status' => '订单狀態',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-40011, '您不是卖家！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $seller_account = $this->Account::getAccountById($employee->super_id);
            $account_seller = $seller_account->AccountSeller;
        }else{
            $account_seller = $user->AccountSeller;
        }

        $page_size = Order::PAGE_NUM;
        $order_lists = $this->Order::getOrderByAccountSellerId($account_seller->id,$page_size,$this->Input['order_status']);

        $data['order_list'] = array();
        if($order_lists){
            foreach ($order_lists as $order_list_key=>$order_list_val){
                $data['order_list'][$order_list_key] = $order_list_val;
                $data['order_list'][$order_list_key]['updated_time'] = date('Y-m-d H:i',strtotime($order_list_val->updated_at));

                //获取图片
                if(!empty($order_list_val->image)){
                    $data['order_list'][$order_list_key]['image'] = getImgUrl(explode(',',$order_list_val->image)[0],'goods_imgs','');
                }

                //商品参数
                $data['order_list'][$order_list_key]['goods_params'] = $this->OfferController->abuttedParam($order_list_val->goods_info);

                //报价参数
                $data['order_list'][$order_list_key]['offer_params'] = $this->OfferController->abuttedParam($order_list_val->offer_info);

                //豆粕 商品名显示
                $goods_attr_list = $this->OfferController->paramList($order_list_val->goods_info);
                $offer_attr_list = $this->OfferController->paramList($order_list_val->offer_info);

                //豆粕
                $data['order_list'][$order_list_key]['goods_name'] = $order_list_val->goods_name;
                //TODO 去掉类型转换
                $data['order_list'][$order_list_key]['num'] = (int)$order_list_val->num;
                if($order_list_val->category_name == GoodsCategory::NAME['soybean_meal']){
                    $data['order_list'][$order_list_key]['goods_name'] = $this->OfferController->isSoybeanMeal($goods_attr_list).$order_list_val->order_unit;
                }

                //基差
                if($order_list_val->offer_name == GoodsOfferPattern::OFFER_PATTERN['basis_price']){
                    $data['order_list'][$order_list_key]['price'] = $this->OfferController->isBasisPrice($offer_attr_list).'+'.$order_list_val->price;
                }else{
                    $data['order_list'][$order_list_key]['price'] = '¥'.$order_list_val->price;
                }

                unset($data['order_list'][$order_list_key]['offer_info']);
                unset($data['order_list'][$order_list_key]['goods_info']);
                unset($data['order_list'][$order_list_key]['created_at']);
                unset($data['order_list'][$order_list_key]['updated_at']);
            }
        }

        $data['paginate'] = pageing($order_lists);
        return apiReturn(0, '请求成功 !',$data);
    }


}
