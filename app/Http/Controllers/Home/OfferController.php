<?php

namespace App\Http\Controllers\Home;

use App\Services\AccountService;
use App\Services\AddressService;
use App\Services\GoodsService;
use App\Services\OfferService;
use App\Services\AreaInfoService;
use App\Services\AddressLocateService;
use App\Services\MqttService;
use App\Http\Controllers\Admin\CommonController;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

use App\Account;
use App\GoodsOfferAttribute;
use App\GoodsOffer;
use App\AccountBusiness;
use App\AccountSeller;
use App\GoodsOfferPattern;
use App\GoodsCategory;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Mockery\Exception;
use Illuminate\Support\Facades\DB;

use GuzzleHttp\Client;


class OfferController extends BaseController
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
    protected $Mqtt;
    protected $Common;
    protected $Area;
    protected $url;
    protected $getUserTags = '/trade/account/api?act=getUserTags';

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
        MqttService $mqtt,
        AreaInfoService $area,
        CommonController $common
    )
    {
        parent::__construct($request, $log, $redis);
        $this->Account = $Account;
        $this->Validator = $validator;
        $this->Address = $address;
        $this->Goods = $goods;
        $this->Offer = $offer;
        $this->Locate = $Locate;
        $this->Mqtt = $mqtt;
        $this->Common = $common;
        $this->Area = $area;
        $this->url = config('ext.funong_dealers_url');
    }


    /**
     * 获取报价模式
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOfferPattern()
    {

        $data['offer_patterns'] = array();
        $data['offer_patterns'] =$this->Offer::getOfferPattern();
        return apiReturn(0, '请求成功 !',$data);
    }


    /**
     * 报价模板
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function offerTemplate()
    {

        $validator = $this->Validator::make($this->Input, [
            'offer_pattern_id' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'number' => ':attribute为数字',
        ], [
            'offer_pattern_id' => '报价模式id',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data['template_lists'] = array();
        $templates = $this->Offer::getOfferAttrsByPatternId($this->Input['offer_pattern_id']);

        //解析json
        foreach ($templates as $template_key => $template_val){
            if(($template_val->control_type == GoodsOfferAttribute::CONTROL_TYPE['select'])
                or
                $template_val->control_type == GoodsOfferAttribute::CONTROL_TYPE['input_select']
            ){
                $template_val->avaliable_value = json_decode($template_val->avaliable_value,true);
            }
        }

        $data['template_lists'] = $templates;
        return apiReturn(0, '请求成功！', $data);
    }


    /**
     * 提交报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function goodsOffer()
    {

        $validator = $this->Validator::make($this->Input, [
            'goods_id' => 'required',
            'offer_pattern_id' => 'required',
            'delivery_address_id' => 'required',
            'price' => 'required | numeric',
            'order_unit' => 'required',
            'describe' => '',
            'offer_info' => '',
            'stock' => 'required | numeric',
            'single_number' => 'required | numeric',
            'moq_number' => 'required | numeric',
            'offer_starttime' => 'required',
            'offer_endtime' => 'required',
            'delivery_starttime' => 'required',
            'delivery_endtime' => 'required',
        ], [
            'required' => ':attribute为必填项',
            'min' => ':attribute最短2位',
            'max' => ':attribute最长10位',
        ], [
            'goods_id' => '商品id',
            'offer_pattern_id' => '报价模式id',
            'delivery_address_id' => '提货地址id',
            'price' => '价格',
            'order_unit' => '定价单位',
            'describe' => '价格提示',
            'stock' => '总量',
            'single_number' => '单个用户量',
            'moq_number' => '每单量',
            'offer_starttime' => '报价开始日期',
            'offer_endtime' => '报价结束日期',
            'delivery_starttime' => '提货开始日期',
            'delivery_endtime' => '提货结束日期',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为买家
        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-40018, '您不是卖家，无法发布报价！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常,无法发布报价！');
            }
            $seller = $account->AccountSeller;
            $business = $account->AccountInfo;
        }else{
            $seller = $user->AccountSeller;
            $business = $user->AccountInfo;
        }

        //是否通过审核
        if($business->review_status != AccountBusiness::REVIEW_STATUS['passed']){
            return apiReturn(-40009, '您还未通过审核，无法发布报价！');
        }

        $data = $this->Input;
        if(isset($this->Input['offer_info'])){
            //获取属性参数
            $param = array();
            foreach ($this->Input['offer_info'] as $k=>$v){
                $param[$v['english_name']] = $v['english_name'];
            }

            //获取报价属性
            $templates = $this->Offer::getOfferAttrsByPatternId($this->Input['offer_pattern_id']);
            foreach($templates as $template_key => $template_val){

                if($template_val->is_necessary == GoodsOfferAttribute::IS_NECESSARY['yes']){

                    if(!isset($param[$template_val->english_name])){
                        $error['errors'][$template_val->english_name] = '为必传项!';
                        return apiReturn(-105, '表单验证失败', $error);
                    }
                }
            }

            $data['offer_info'] = json_encode($this->Input['offer_info'],JSON_UNESCAPED_UNICODE);
        }

        //组装数据
        $data['seller_id'] = $seller->id;
        if($employee){
            $data['account_employee_id'] = $employee->id;
        }

        //报价审核方式
        if($seller->quote_type == AccountSeller::QUOTE_TYPE['system']){
            $data['review_status'] = GoodsOffer::REVIEW_STATUS['passed'];
        }

        //搜索关键字
        $goods = $this->Goods::getGoodsById($this->Input['goods_id']);
        //品类
        $category = $goods->GoodsCategory;
        $data['category_name'] = $category->name;
        //品牌,产地
        if(!empty($goods->goods_attrs)){
            foreach(json_decode($goods->goods_attrs,true) as $k=>$v){
                if(($v['english_name'] == 'brand')){
                    $data['brand_name'] = $v['default_value'];
                }

                if(($v['english_name'] == 'product_area')){
                    $data['product_area'] = $v['default_value'];
                }
            }
        }

        //报价模式
        $offer_pattern = $this->Offer::getOfferPatternById($this->Input['offer_pattern_id']);
        $delivery_address = $this->Address::getAddressById($this->Input['delivery_address_id']);
        $data['offer_pattern_name'] = $offer_pattern['name'];
        $data['name'] = $delivery_address['name'];
        $data['goods_name'] = $goods->name;
        $data['province'] = $delivery_address['province'];
        $data['city'] = $delivery_address['city'];
        $data['county'] = $delivery_address['county'];
        $data['address'] = $delivery_address['address'];
        $data['address_details'] = $delivery_address['address_details'];
        $data['lng'] = $delivery_address['lng'];
        $data['lat'] = $delivery_address['lat'];
        $data['account_businesses_id'] = $business->id;

        if($this->Offer::createOffer($data)->id){

            if(!empty($data['offer_info'])){
                $offer_params = $this->abuttedParam($data['offer_info']);
            }else{
                $offer_params = '';
            }

            //发送微信通知
            $msg_data['data'] = array(
                'data' => array (
                    'first'    => array('value' => "来自应用：%1\$s\n提交时间：".date('Y-m-d H:i:s',time())."\n卖家信息：".$business->name."，".$user->phone.""),
                    'keyword1' => array('value' => "".$offer_params."：".$data['address_details'].""),
                    'keyword2' => array('value' => "报价"),
                    'keyword3' => array('value' => $data['address_details'].','.$this->Input['price']),
                    'remark'   => array('value' => "\n请及时进行审核！")
                )
            );
            $msg_data['action'] = "productChange";
            $this->Common->socketMessage($msg_data);

            return apiReturn(0, '请求成功！');
        }

        return apiReturn(-40000, '报价失败！');
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
        $goods = $this->Offer::getGoodsOfferById($this->Input['offer_id'])->Goods;
        $data['offer_detail'] = array();
        $data['offer_detail'] = $goods_offer;

        if(!count($data['offer_detail'])){
            return apiReturn(-40001, '报价不存在！');
        }

        $data['offer_detail']['faces'] = null;
        $data['offer_detail']['vedios'] = null;
        $category_status = $this->isEffective();

        $data['offer_detail']['is_effective'] = 1;
        if(!$category_status[$goods->category_id]){
            $data['offer_detail']['is_effective'] = 0;
        }

        $data['offer_detail']['offer_start'] = strtotime($goods_offer->offer_starttime);
        $data['offer_detail']['offer_end'] = strtotime($goods_offer->offer_endtime);

        //图片
        if(!empty($goods->faces)){
            $data['offer_detail']['faces'] = getImgUrl(explode(',',$goods->faces)[0],'goods_imgs','');
        }

        //视频
        if(!empty($goods->vedios)){
            $data['offer_detail']['vedios'] = getImgUrl(explode(',',$goods->vedios)[0],'goods_vedios','');
        }

        //豆粕 商品名显示
        $goods_attr_list = $this->paramList($goods->goods_attrs);
        $offer_attr_list = $this->paramList($goods_offer->offer_info);
        $data['offer_detail']['offer_info'] = $offer_attr_list;
        $goods_category = $goods->GoodsCategory;
        $offer_pattern = $goods_offer->GoodsOfferPattern;

        //豆粕
        $data['offer_detail']['goods_name'] = $goods->name;
        if($goods_category->name == GoodsCategory::NAME['soybean_meal']){
            $data['offer_detail']['goods_name'] = $this->isSoybeanMeal($goods_attr_list);
        }

        //所属卖家
        $seller = $this->Account::getAccountSellerById($goods_offer->seller_id);
        $account = $this->Account::getAccountBusinessByAccountId($seller->account_id);
        $data['offer_detail']['business_name'] = $account['name'];

        //基差
        $data['offer_detail']['price_type'] = GoodsOfferPattern::PRICE_TYPE['change'];
        if($offer_pattern->name == GoodsOfferPattern::OFFER_PATTERN['basis_price']){
            $data['offer_detail']['price_type'] = GoodsOfferPattern::PRICE_TYPE['unchange'];
            if($goods_offer->price < 0){
                $data['offer_detail']['sp_price'] = $this->isBasisPrice($offer_attr_list).$goods_offer->price;
            }else{
                $data['offer_detail']['sp_price'] = $this->isBasisPrice($offer_attr_list).'+'.$goods_offer->price;
            }
        }else{
            $data['offer_detail']['sp_price'] = '¥'.$goods_offer->price;
        }

        //格式化时间
        $data['offer_detail']['updated_time'] = $goods_offer->updated_at;

        $update_time = date('Y-m-d',strtotime($goods_offer->updated_at));
        $today_time = date('Y-m-d',time());
        if($update_time == $today_time){
            $data['offer_detail']['updated_time'] = date('H:i',strtotime($goods_offer->updated_at)).'发布';
        }else{
            $data['offer_detail']['updated_time'] = date('m/d',strtotime($goods_offer->updated_at)).'发布';
        }

        //拼接商品自定义属性
        $data['offer_detail']['goods_params'] = null;
        if(!empty($goods->goods_attrs) and (!is_null(json_decode($goods->goods_attrs)))){
            foreach (json_decode($goods->goods_attrs,true) as $k=>$v){
                $data['offer_detail']['goods_params'] .= $v['name'].$v['default_value'].'，';
            }
            $data['offer_detail']['goods_params'] = rtrim($data['offer_detail']['goods_params'],'，');
        }

        if(!is_null($this->Request->header('token'))){
            try{
                $token = $this->Request->header('token');
                $user = $this->Account::getUserByToken($token);
            }catch(\Exception $exception){
            }

            if(!empty($user)){
                //判断是否为员工账户
                $employee = $user->accountEmployee;
                if(!empty($employee)){
                    $account = $this->Account::getAccountById($employee->super_id);
                    if(!$account){
                        return apiReturn(-30006, '账户异常，无法发布商品！');
                    }
                    $business = $account->AccountInfo;
                }else{
                    $business = $user->AccountInfo;
                }
            }
        }


        //定位信息
        $data['offer_detail']['distance'] = '';
        $goods_delivery_address = $goods_offer->goodsDeliveryAddress;
        if(isset($business)){
            if(count($business)){
                //距离
                if(!empty($business->lat) and !empty($goods_delivery_address->lat)){
                    $distance = $this->Locate->getDistance($business->lat,$business->lng,$goods_delivery_address->lat,$goods_delivery_address->lng);
                    $data['offer_detail']['distance'] = (string)round($distance,2);
                }
            }
        }

        //如果没有定位
        if(empty($data['offer_detail']['distance'])){
            //获取当前ip
            $ipAddress = empty($_SERVER['REMOTE_ADDR'])?null:$_SERVER['REMOTE_ADDR'];
            $result = $this->Locate->GetIpLookup($ipAddress);
            if($result != false){
                $distance = $this->Locate->getDistance($result['y'],$result['x'],$goods_delivery_address->lat,$goods_delivery_address->lng);
                $data['offer_detail']['distance'] = (string)round($distance,2);
            }
        }

        unset($data['offer_detail']['goods_delivery_address']);
        return apiReturn(0, '请求成功！',$data);
    }


    /**
     * 编辑商品报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function editGoodsOffer()
    {

        $validator = $this->Validator::make($this->Input, [
            'offer_id' => 'required',
            'price' => 'required | numeric',
            'order_unit' => 'required',
            'describe' => '',
            'offer_info' => '',
            'stock' => 'required | numeric',
            'single_number' => 'required | numeric',
            'moq_number' => 'required | numeric',
            'offer_starttime' => 'required',
            'offer_endtime' => 'required',
            'delivery_starttime' => 'required',
            'delivery_endtime' => 'required',
        ], [
            'required' => ':attribute为必填项',
            'min' => ':attribute最短2位',
            'max' => ':attribute最长10位',
        ], [
            'offer_id' => '报价id',
            'price' => '价格',
            'order_unit' => '定价单位',
            'describe' => '价格提示',
            'stock' => '总量',
            'single_number' => '单个用户量',
            'moq_number' => '每单量',
            'offer_starttime' => '报价开始日期',
            'offer_endtime' => '报价结束日期',
            'delivery_starttime' => '提货开始日期',
            'delivery_endtime' => '提货结束日期',
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
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常，无法修改报价！');
            }
            $seller = $account->AccountSeller;
        }else{
            $seller = $user->AccountSeller;
        }

        if(empty($seller)){
            return apiReturn(-40019, '您不是卖家！');
        }

        $offer = $this->Offer::getGoodsOfferById($this->Input['offer_id']);

        if(is_null($offer)){
            return apiReturn(-40001, '报价不存在！');
        }

        $data = $this->Input;
        if(isset($this->Input['offer_info'])){
            //获取属性参数
            $param = array();
            foreach ($this->Input['offer_info'] as $k=>$v){
                $param[$v['english_name']] = $v['english_name'];
            }

            //获取报价属性
            $templates = $this->Offer::getOfferAttrsByPatternId($offer->offer_pattern_id);
            foreach($templates as $template_key => $template_val){

                if($template_val->is_necessary == GoodsOfferAttribute::IS_NECESSARY['yes']){

                    if(!isset($param[$template_val->english_name])){
                        $error['errors'][$template_val->english_name] = '为必传项!';
                        return apiReturn(-105, '表单验证失败', $error);
                    }
                }
            }

            $data['offer_info'] = json_encode($this->Input['offer_info'],JSON_UNESCAPED_UNICODE);
        }

        //报价审核方式
        if($seller->quote_type == AccountSeller::QUOTE_TYPE['system']){
            $data['review_status'] = GoodsOffer::REVIEW_STATUS['passed'];
        }

        //组装数据
        $data['id'] = $data['offer_id'];
        $data['status'] = GoodsOffer::STATUS['enable'];
        unset($data['offer_id']);
        unset($data['version_name']);
        unset($data['version_code']);
        if($this->Offer::updateOfferById($this->Input['offer_id'],$data)){
            return apiReturn(0, '请求成功！');
        }

        return apiReturn(-40000, '报价失败！');
    }


    /**
     * 报价列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function goodsOfferList()
    {

        $validator = $this->Validator::make($this->Input, [
            'type' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'type' => '报价类型',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        $seller = $user->AccountSeller;
        if(empty($seller)){
            return apiReturn(-40019, '您不是卖家！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $offer_lists = $this->Offer::getGoodsOfferListByAccountEmployeeId($employee->id,$this->Input['type']);
        }else{
            $offer_lists = $this->Offer::getGoodsOfferListBySellerId($seller->id,$this->Input['type']);
        }

        $data['offer_lists'] = array();

        //无报价
        if(is_null($offer_lists)){
            return apiReturn(0, '请求成功！',$data);
        }

        foreach ($offer_lists as $offer_list_key => $offer_list_val){
            $goods = $offer_list_val->goods;
            $goods_category = $goods->GoodsCategory;
            $offer_pattern = $offer_list_val->GoodsOfferPattern;

            $data['offer_lists'][$offer_list_key]['goods_params'] = null;
            $data['offer_lists'][$offer_list_key]['offer_params'] = null;

            //获取图片
            if(!empty($goods->faces)){
                $data['offer_lists'][$offer_list_key]['faces'] = getImgUrl(explode(',',$goods->faces)[0],'goods_imgs','');
            }

            //商品参数
            $data['offer_lists'][$offer_list_key]['goods_params'] = $this->abuttedParam($goods->goods_attrs);

            //报价参数
            $data['offer_lists'][$offer_list_key]['offer_params'] = $this->abuttedParam($offer_list_val->offer_info);

            //拼装参数
            $data['offer_lists'][$offer_list_key]['id'] = $offer_list_val->id;

            //豆粕 商品名显示
            $goods_attr_list = $this->paramList($goods->goods_attrs);
            $offer_attr_list = $this->paramList($offer_list_val->offer_info);

            //豆粕
            $data['offer_lists'][$offer_list_key]['goods_name'] = $goods->name;
            if($goods_category->name == GoodsCategory::NAME['soybean_meal']){
                $data['offer_lists'][$offer_list_key]['goods_name'] = $this->isSoybeanMeal($goods_attr_list);
            }

            //基差
            if($offer_pattern->name == GoodsOfferPattern::OFFER_PATTERN['basis_price']){
                $data['offer_lists'][$offer_list_key]['price'] = $this->isBasisPrice($offer_attr_list).'+'.$offer_list_val->price;
            }else{
                $data['offer_lists'][$offer_list_key]['price'] = '¥'.$offer_list_val->price;
            }

            $data['offer_lists'][$offer_list_key]['describe'] = $offer_list_val->describe;
            $data['offer_lists'][$offer_list_key]['offer_pattern_id'] = $offer_list_val->offer_pattern_id;
            $data['offer_lists'][$offer_list_key]['category_name'] = $goods_category->name;
            $data['offer_lists'][$offer_list_key]['review_status'] = $offer_list_val->review_status;
            //TODO 去掉类型转换
            $data['offer_lists'][$offer_list_key]['stock'] = (int)$offer_list_val->stock - $offer_list_val->lock_number;
            $data['offer_lists'][$offer_list_key]['order_unit'] = $offer_list_val->order_unit;
            $data['offer_lists'][$offer_list_key]['offer_name'] = $offer_pattern->name;
            $data['offer_lists'][$offer_list_key]['delivery_address'] = $offer_list_val->address_details;
            $data['offer_lists'][$offer_list_key]['offer_starttime'] = $offer_list_val->offer_starttime;
            $data['offer_lists'][$offer_list_key]['offer_endtime'] = $offer_list_val->offer_endtime;
            $data['offer_lists'][$offer_list_key]['delivery_starttime'] = $offer_list_val->delivery_starttime;
            $data['offer_lists'][$offer_list_key]['delivery_endtime'] = $offer_list_val->delivery_endtime;
            $data['offer_lists'][$offer_list_key]['lock_number'] = $offer_list_val->lock_number;
        }

        $data['paginate'] = pageing($offer_lists);
        return apiReturn(0, '请求成功！',$data);
    }


    /**
     * 删除报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteGoodsOffer()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常，无法删除报价！');
            }
            $seller = $account->AccountSeller;
        }else{
            $seller = $user->AccountSeller;
        }

        if(empty($seller)){
            return apiReturn(-40019, '您不是卖家！');
        }

        //验证报价id
        if($this->verifyOfferId()){
            return $this->verifyOfferId();
        }

        $offer = $this->Offer::getGoodsOfferById($this->Input['offer_id']);

        if(is_null($offer)){
            return apiReturn(-40002, '报价不存在！');
        }

        $order = $offer->order;
        if(count($order)){
            return apiReturn(-40005, '该报价下有订单,无法删除！');
        }

        if($this->Offer::deleteGoodsOfferById($this->Input['offer_id'])){
            return apiReturn(0, '请求成功！');
        }

        return apiReturn(-40003, '报价删除失败！');
    }


    /**
     * 修改报价信息
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function modifyGoodsOffer()
    {

        $validator = $this->Validator::make($this->Input, [
            'action_type' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'action_type' => '报价操作类型',
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
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常，无法修改报价！');
            }
            $seller = $account->AccountSeller;
        }else{
            $seller = $user->AccountSeller;
        }

        if(empty($seller)){
            return apiReturn(-40019, '您不是卖家！');
        }

        //验证报价id
        if($this->verifyOfferId()){
            return $this->verifyOfferId();
        }

        $action_type = $this->Input['action_type'];
        //验证操作类型
        if(!in_array($action_type,array('ON_SHELVES','OFF_SHELVES','ADD_STOCK','EDIT_PRICE'))){
            return apiReturn(-40004, '报价操作类型不正确');
        }

        $offer = $this->Offer::getGoodsOfferById($this->Input['offer_id']);

        if(is_null($offer)){
            return apiReturn(-40001, '报价不存在！');
        }

        //拼接mqtt消息
        $content['msg_type'] = $this->Mqtt->msg_type['offer'];
        $content['price_msg_content'] = array();
        $price_content['id'] = $offer->id;

        if($action_type == 'OFF_SHELVES'){
            $price_content['is_effective'] = 0;
        }else{
            $price_content['is_effective'] = 1;
        }

        $price_content['price'] = $offer->price;
        $price_content['stock'] = $offer->stock - $offer->lock_num;
        $content['price_msg_content']['price_content'] = $price_content;
        $content['price_msg_content']['a'] = 2;

        $BeginDate=date('Y-m-01', strtotime(date("Y-m-d")));
        $data['offer_starttime'] = date('Y-m-d',time());
        $data['offer_endtime'] = date('Y-m-d',strtotime('+1 day'));
        $data['delivery_starttime'] = date('Y-m-d',time());
        $data['delivery_endtime'] = date('Y-m-d', strtotime("$BeginDate +1 month -1 day"));

        //上架
        if($action_type == 'ON_SHELVES'){
            $data['status'] = GoodsOffer::STATUS['enable'];
            if($this->Offer::updateOfferById($this->Input['offer_id'],$data)){
                return apiReturn(0, '请求成功！');
            }

            return apiReturn(-40005, '报价上架失败！');
        }

        //下架
        if($action_type == 'OFF_SHELVES'){
            $data['status'] = GoodsOffer::STATUS['disable'];
            if($this->Offer::updateOfferById($this->Input['offer_id'],$data)){
                $this->Mqtt->broadcast(json_encode($content));
                return apiReturn(0, '请求成功！');
            }

            return apiReturn(-40006, '报价下架失败！');
        }

        //添加库存
        if($action_type == 'ADD_STOCK'){
            $validator = $this->Validator::make($this->Input, [
                'stock' => 'required | numeric | min:1',
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

            $data['stock'] = $this->Input['stock'];

            if($this->Offer::updateOfferById($this->Input['offer_id'],$data)){

                $data['stock'] -= $offer->lock_number;
                $content['price_msg_content']['price_content']['stock'] = $data['stock'] ;
                $this->Mqtt->broadcast(json_encode($content));
                return apiReturn(0, '请求成功！',$data);
            }

            return apiReturn(-40007, '库存添加失败！');
        }

        //修改价格
        if($action_type == 'EDIT_PRICE'){
            $validator = $this->Validator::make($this->Input, [
                'price' => 'required | numeric | min:1',
            ], [
                'required' => ':attribute为必填项',
                'numeric' => ':attribute为数字',
                'min' => ':attribute最小1元',
            ], [
                'price' => '价格',
            ]);
            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-104, '数据验证失败', $error);
            }

            $data['price'] = $this->Input['price'];

            if($this->Offer::updateOfferById($this->Input['offer_id'],$data)){

                //MQTT
                $content['price_msg_content']['price_content']['price'] = $data['price'] ;
                $this->Mqtt->broadcast(json_encode($content));
                return apiReturn(0, '请求成功！',$data);
            }

            return apiReturn(-40008, '价格修改失败！');
        }
    }


    /**
     * 获取报价筛选条件
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilterParam()
    {

        $validator = $this->Validator::make($this->Input, [
            'goods_category_id' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'goods_category_id' => '品类id',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data['lists'] = array();
        $data['lists']['brand'] = array();
        $data['lists']['product_area']= array();
        $data['lists']['offer_pattern'] = array();

        $goods_category = $this->Goods::getCategoryById($this->Input['goods_category_id']);
        $attributes  = $this->Goods::getGoodsCategoriesByCategoryId($this->Input['goods_category_id']);

        foreach ($attributes as $k=>$v){

            if(($v['english_name'] == 'brand')){
                $data['lists']['brand'] = json_decode($v['avaliable_value'],true);
            }

            if(($v['english_name'] == 'product_area')){
                $data['lists']['product_area'] = (array)json_decode($v['avaliable_value'],true);
            }

            //报价模式
            $offer_pattern_id = explode(',',$goods_category->offer_type);

            foreach ($offer_pattern_id as $key=>$val){
                $offer_pattern = $this->Offer::getOfferPatternById($val);
                $data['lists']['offer_pattern'][$key] = $offer_pattern['name'];
            }
        }

        return apiReturn(0, '请求成功！',$data);
    }


    /**
     * 推荐报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function recommendGoodsOffer()
    {
        $validator = $this->Validator::make($this->Input, [
            'action_type' => 'required | numeric',
            'ip' => '',
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'action_type' => '操作类型',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        if(!is_null($this->Request->header('token'))){
            try{
                $token = $this->Request->header('token');
                $user = $this->Account::getUserByToken($token);
            }catch(\Exception $exception){
            }

            if(!empty($user)){
                //判断是否为员工账户
                $employee = $user->accountEmployee;
                if(!empty($employee)){
                    $account = $this->Account::getAccountById($employee->super_id);
                    if(!$account){
                        return apiReturn(-30006, '账户异常，无法发布商品！');
                    }
                    $business = $account->AccountInfo;
                }else{
                    $business = $user->AccountInfo;
                }
            }
        }


        //首页
        if($this->Input['action_type'] == 1){
            $offer_lists = $this->Offer::getAectiveGoodsOffer(GoodsOffer::PAGE_NUM);
        }

        //筛选页
        if($this->Input['action_type'] == 2){
            $search_array = ['offer_pattern_name','brand_name','category_name','product_area'];
            $search_data = null;
            foreach ($search_array as $v){
                if(isset($this->Input[$v])){
                    $search_data[$v] = $this->Input[$v];
                }
            }
            $offer_lists = $this->Offer::getAectiveGoodsOffer(GoodsOffer::PAGE_NUM,$search_data);
        }

        //首页搜索
        if($this->Input['action_type'] == 3){
            $validator = $this->Validator::make($this->Input, [
                'search_keywords' => 'required',
            ], [
                'required' => ':attribute为必填项',
            ], [
                'action_type' => '操作类型',
            ]);

            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-104, '数据验证失败', $error);
            }
            $offer_lists = $this->Offer::getAectiveGoodsOfferByNameOrKeyWords(GoodsOffer::PAGE_NUM,$this->Input['search_keywords']);
        }

        //定位
        //获取当前ip
        if(isset($this->Input['ip'])){
            $ipAddress = $this->Input['ip'];
        }else{
            $ipAddress = $this->getIp();
        }

        $result = $this->Redis::hget("ipLocate",$ipAddress);
        if(empty($result)){
            $result = $this->Locate->GetIpLookup($ipAddress);
            if($result != false){
                $ip_locate['x'] = $result['x'];
                $ip_locate['y'] = $result['y'];
            }
        }else{
            $result = json_decode($result,true);
            $ip_locate['x'] = $result['x'];
            $ip_locate['y'] = $result['y'];
        }

        if(isset($this->Input['x'])){
            $ip_locate['x'] = $this->Input['x'];
        }

        if(isset($this->Input['y'])){
            $ip_locate['y'] = $this->Input['y'];
        }

        $data['offer_lists'] = array();
        $category_status = $this->isEffective();

        if(!empty($offer_lists)){
            foreach ($offer_lists as $offer_list_key => $offer_list_val){

                $goods = $offer_list_val->goods;
                $goods_delivery_address = $offer_list_val->goodsDeliveryAddress;
                $seller = $this->Account::getAccountSellerById($offer_list_val->seller_id);
                if($seller){
                    $account = $this->Account::getAccountBusinessByAccountId($seller->account_id);
                }else{
                    $account['name'] = '上海苏农集团';
                }

                //获取图片
                if(!empty($goods->faces)){
                    $data['offer_lists'][$offer_list_key]['faces'] = getImgUrl(explode(',',$goods->faces)[0],'goods_imgs','');
                }

                //商品参数
                $data['offer_lists'][$offer_list_key]['goods_params'] = $this->abuttedParam($goods->goods_attrs);

                //报价参数
                $data['offer_lists'][$offer_list_key]['offer_params'] = $this->abuttedParam($offer_list_val->offer_info);

                //拼装参数
                $data['offer_lists'][$offer_list_key]['id'] = $offer_list_val->id;

                //豆粕 商品名显示
                $goods_attr_list = $this->paramList($goods->goods_attrs);
                $offer_attr_list = $this->paramList($offer_list_val->offer_info);

                //豆粕
                $data['offer_lists'][$offer_list_key]['goods_name'] = $goods->name;
                if($offer_list_val->category_name == GoodsCategory::NAME['soybean_meal']){
                    $data['offer_lists'][$offer_list_key]['goods_name'] = $this->isSoybeanMeal($goods_attr_list);
                }

                //基差
                if($offer_list_val->offer_pattern_name == GoodsOfferPattern::OFFER_PATTERN['basis_price']){
                    $data['offer_lists'][$offer_list_key]['price'] = null;
                    if($offer_list_val->price < 0){
                        $data['offer_lists'][$offer_list_key]['price'] = $this->isBasisPrice($offer_attr_list).$offer_list_val->price;
                    }else{
                        $data['offer_lists'][$offer_list_key]['price'] = $this->isBasisPrice($offer_attr_list).'+'.$offer_list_val->price;
                    }
                }else{
                    $data['offer_lists'][$offer_list_key]['price'] = '¥'.$offer_list_val->price;
                }

                //所属卖家
                $data['offer_lists'][$offer_list_key]['business_name'] = $account['name'];

                //定位信息
                $data['offer_lists'][$offer_list_key]['distance'] = '';

                if(isset($business)){
                    if(count($business)){
                        //距离
                        if(!empty($business->lat) and !empty($goods_delivery_address->lat)){
                            $distance = $this->Locate->getDistance($business->lat,$business->lng,$goods_delivery_address->lat,$goods_delivery_address->lng);
                            $data['offer_lists'][$offer_list_key]['distance'] = (string)round($distance,2);
                        }
                    }
                }


                //如果没有定位
                if(empty($data['offer_lists'][$offer_list_key]['distance'])){
                    if(isset($ip_locate)){
                        $distance = $this->Locate->getDistance($ip_locate['y'],$ip_locate['x'],$goods_delivery_address->lat,$goods_delivery_address->lng);
                        $data['offer_lists'][$offer_list_key]['distance'] = (string)round($distance,2);
                    }
                }

                if($data['offer_lists'][$offer_list_key]['distance'] < 300){
                    $data['offer_lists'][$offer_list_key]['distance_sort'] = 1;
                }

                if((300 < $data['offer_lists'][$offer_list_key]['distance'] ) and (500 > $data['offer_lists'][$offer_list_key]['distance'] )){
                    $data['offer_lists'][$offer_list_key]['distance_sort'] = 2;
                }

                if((500 < $data['offer_lists'][$offer_list_key]['distance'] ) and (1000 > $data['offer_lists'][$offer_list_key]['distance'] )){
                    $data['offer_lists'][$offer_list_key]['distance_sort'] = 3;
                }

                if($data['offer_lists'][$offer_list_key]['distance'] > 1000){
                    $data['offer_lists'][$offer_list_key]['distance_sort'] = 4;
                }

                //是否有效
                $data['offer_lists'][$offer_list_key]['is_effective'] = 1;
                if(!$category_status[$goods->category_id]){
                    $data['offer_lists'][$offer_list_key]['is_effective'] = 0;
                }

                //格式化时间
                $data['offer_lists'][$offer_list_key]['updated_time'] = $offer_list_val->updated_at;
                $update_time = date('Y-m-d',strtotime($offer_list_val->updated_at));
                $today_time = date('Y-m-d',time());
                if($update_time == $today_time){
                    $data['offer_lists'][$offer_list_key]['updated_time'] = date('H:i',strtotime($offer_list_val->updated_at)).'发布';
                }else{
                    $data['offer_lists'][$offer_list_key]['updated_time'] = date('m/d',strtotime($offer_list_val->updated_at)).'发布';
                }

                //获取品类交易时间
                $goods_category = $goods->GoodsCategory;

                if($offer_list_val->status == GoodsOffer::STATUS['disable']){
                    $data['offer_lists'][$offer_list_key]['is_effective'] = 0;
                }

                if($offer_list_val->review_status != GoodsOffer::REVIEW_STATUS['passed']){
                    $data['offer_lists'][$offer_list_key]['is_effective'] = 0;
                }

                //提货城市
                $city = $this->Area::getAreaInfoByPid($goods_delivery_address->city);

                //拼装参数
                $data['offer_lists'][$offer_list_key]['updated_at'] = strtotime(date('Y-m-d',strtotime($offer_list_val->updated_at)));
                $data['offer_lists'][$offer_list_key]['delivery_city'] = $city->name;
                $data['offer_lists'][$offer_list_key]['single_number'] = $offer_list_val->single_number;
                $data['offer_lists'][$offer_list_key]['id'] = $offer_list_val->id;
                $data['offer_lists'][$offer_list_key]['describe'] = empty($offer_list_val->describe) ? '' : $offer_list_val->describe;
                $data['offer_lists'][$offer_list_key]['category_name'] = $goods_category->name;
                $data['offer_lists'][$offer_list_key]['stock'] = $offer_list_val->stock;
                $data['offer_lists'][$offer_list_key]['order_unit'] = $offer_list_val->order_unit;
                $data['offer_lists'][$offer_list_key]['offer_name'] = $offer_list_val->offer_pattern_name;
                $data['offer_lists'][$offer_list_key]['brand_name'] = $offer_list_val->product_area;
                $data['offer_lists'][$offer_list_key]['delivery_address'] = $goods_delivery_address->name;
                $data['offer_lists'][$offer_list_key]['offer_starttime'] = strtotime($offer_list_val->offer_starttime);
                $data['offer_lists'][$offer_list_key]['offer_endtime'] = strtotime($offer_list_val->offer_endtime);
                $data['offer_lists'][$offer_list_key]['tag_ids'] = $offer_list_val->tag_ids;
            }
        }

        //根据距离排序
        usort($data['offer_lists'],array($this, 'my_product_sort_default'));
        $data['paginate'] = pageing($offer_lists);

        //品类列表
        $category_list = $this->Goods::getGoodsCategories();
        $data['category_list'] = array();
        foreach ($category_list as $k=>$v){
            $data['category_list'][$k] = $v;
            if($v['name'] == GoodsCategory::NAME['soybean_meal']){
                $data['category_list'][$k]['sort'] = 1;
            }else{
                $data['category_list'][$k]['sort'] = 0;
            }
        }

        $arr6 = array_map(create_function('$n', 'return $n["sort"];'), $data['category_list']);
        array_multisort($arr6,SORT_DESC,$data['category_list'] );

        $new_offer_lists = [
            'xh' => [],
            'jc' => [],
            'qt' => []
        ];
        foreach ($data['offer_lists'] as $k => $v) {
            if ($v['offer_name'] === '基差价') {
                $new_offer_lists['jc'][] = $v;
            } elseif ($v['offer_name'] === '现货价') {
                $new_offer_lists['xh'][] = $v;
            } else {
                $new_offer_lists['qt'][] = $v;
            }
        }
        $data['offer_lists'] = [];
        foreach ($new_offer_lists as $k => $v) {
            foreach ($v as $kk => $vv) {
                $data['offer_lists'][] = $vv;
            }
        }

        return apiReturn(0, '请求成功！',$data);
    }

    /**
     * 推荐报价 带用户信息 微信端专用
     * @return \Illuminate\Http\JsonResponse
     */
    public function recommendGoodsOffer2()
    {
        $validator = $this->Validator::make($this->Input, [
            'action_type' => 'required | numeric',
            'ip' => '',
            'public_openid' => 'required'
        ], [
            'required' => ':attribute为必填项',
            'numeric' => ':attribute为数字',
        ], [
            'action_type' => '操作类型',
            'public_openid' => 'public_openid'
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        if(!is_null($this->Request->header('token'))){
            try{
                $token = $this->Request->header('token');
                $user = $this->Account::getUserByToken($token);
            }catch(\Exception $exception){
            }

            if(!empty($user)){
                //判断是否为员工账户
                $employee = $user->accountEmployee;
                if(!empty($employee)){
                    $account = $this->Account::getAccountById($employee->super_id);
                    if(!$account){
                        return apiReturn(-30006, '账户异常，无法发布商品！');
                    }
                    $business = $account->AccountInfo;
                }else{
                    $business = $user->AccountInfo;
                }
            }
        }


        //首页
        if($this->Input['action_type'] == 1){
            $offer_lists = $this->Offer::getAectiveGoodsOffer(GoodsOffer::PAGE_NUM);
        }

        //筛选页
        if($this->Input['action_type'] == 2){
            $search_array = ['offer_pattern_name','brand_name','category_name','product_area'];
            $search_data = null;
            foreach ($search_array as $v){
                if(isset($this->Input[$v])){
                    $search_data[$v] = $this->Input[$v];
                }
            }
            $offer_lists = $this->Offer::getAectiveGoodsOffer(GoodsOffer::PAGE_NUM,$search_data);
        }

        //首页搜索
        if($this->Input['action_type'] == 3){
            $validator = $this->Validator::make($this->Input, [
                'search_keywords' => 'required',
            ], [
                'required' => ':attribute为必填项',
            ], [
                'action_type' => '操作类型',
            ]);

            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-104, '数据验证失败', $error);
            }
            $offer_lists = $this->Offer::getAectiveGoodsOfferByNameOrKeyWords(GoodsOffer::PAGE_NUM,$this->Input['search_keywords']);
        }

        //定位
        //获取当前ip
        if(isset($this->Input['ip'])){
            $ipAddress = $this->Input['ip'];
        }else{
            $ipAddress = $this->getIp();
        }

        $result = $this->Redis::hget("ipLocate",$ipAddress);
        if(empty($result)){
            $result = $this->Locate->GetIpLookup($ipAddress);
            if($result != false){
                $ip_locate['x'] = $result['x'];
                $ip_locate['y'] = $result['y'];
            }
        }else{
            $result = json_decode($result,true);
            $ip_locate['x'] = $result['x'];
            $ip_locate['y'] = $result['y'];
        }

        if(isset($this->Input['x'])){
            $ip_locate['x'] = $this->Input['x'];
        }

        if(isset($this->Input['y'])){
            $ip_locate['y'] = $this->Input['y'];
        }

        $data['offer_lists'] = array();
        $category_status = $this->isEffective();

        if(!empty($offer_lists)){
            foreach ($offer_lists as $offer_list_key => $offer_list_val){

                $goods = $offer_list_val->goods;
                $goods_delivery_address = $offer_list_val->goodsDeliveryAddress;
                $seller = $this->Account::getAccountSellerById($offer_list_val->seller_id);
                if($seller){
                    $account = $this->Account::getAccountBusinessByAccountId($seller->account_id);
                }else{
                    $account['name'] = '上海苏农集团';
                }

                //获取图片
                if(!empty($goods->faces)){
                    $data['offer_lists'][$offer_list_key]['faces'] = getImgUrl(explode(',',$goods->faces)[0],'goods_imgs','');
                }

                //商品参数
                $data['offer_lists'][$offer_list_key]['goods_params'] = $this->abuttedParam($goods->goods_attrs);

                //报价参数
                $data['offer_lists'][$offer_list_key]['offer_params'] = $this->abuttedParam($offer_list_val->offer_info);

                //拼装参数
                $data['offer_lists'][$offer_list_key]['id'] = $offer_list_val->id;

                //豆粕 商品名显示
                $goods_attr_list = $this->paramList($goods->goods_attrs);
                $offer_attr_list = $this->paramList($offer_list_val->offer_info);

                //豆粕
                $data['offer_lists'][$offer_list_key]['goods_name'] = $goods->name;
                if($offer_list_val->category_name == GoodsCategory::NAME['soybean_meal']){
                    $data['offer_lists'][$offer_list_key]['goods_name'] = $this->isSoybeanMeal($goods_attr_list);
                }

                //基差
                if($offer_list_val->offer_pattern_name == GoodsOfferPattern::OFFER_PATTERN['basis_price']){
                    $data['offer_lists'][$offer_list_key]['price'] = null;
                    if($offer_list_val->price < 0){
                        $data['offer_lists'][$offer_list_key]['price'] = $this->isBasisPrice($offer_attr_list).$offer_list_val->price;
                    }else{
                        $data['offer_lists'][$offer_list_key]['price'] = $this->isBasisPrice($offer_attr_list).'+'.$offer_list_val->price;
                    }
                }else{
                    $data['offer_lists'][$offer_list_key]['price'] = '¥'.$offer_list_val->price;
                }

                //所属卖家
                $data['offer_lists'][$offer_list_key]['business_name'] = $account['name'];

                //定位信息
                $data['offer_lists'][$offer_list_key]['distance'] = '';

                if(isset($business)){
                    if(count($business)){
                        //距离
                        if(!empty($business->lat) and !empty($goods_delivery_address->lat)){
                            $distance = $this->Locate->getDistance($business->lat,$business->lng,$goods_delivery_address->lat,$goods_delivery_address->lng);
                            $data['offer_lists'][$offer_list_key]['distance'] = (string)round($distance,2);
                        }
                    }
                }


                //如果没有定位
                if(empty($data['offer_lists'][$offer_list_key]['distance'])){
                    if(isset($ip_locate)){
                        $distance = $this->Locate->getDistance($ip_locate['y'],$ip_locate['x'],$goods_delivery_address->lat,$goods_delivery_address->lng);
                        $data['offer_lists'][$offer_list_key]['distance'] = (string)round($distance,2);
                    }
                }

                if($data['offer_lists'][$offer_list_key]['distance'] < 300){
                    $data['offer_lists'][$offer_list_key]['distance_sort'] = 1;
                }

                if((300 < $data['offer_lists'][$offer_list_key]['distance'] ) and (500 > $data['offer_lists'][$offer_list_key]['distance'] )){
                    $data['offer_lists'][$offer_list_key]['distance_sort'] = 2;
                }

                if((500 < $data['offer_lists'][$offer_list_key]['distance'] ) and (1000 > $data['offer_lists'][$offer_list_key]['distance'] )){
                    $data['offer_lists'][$offer_list_key]['distance_sort'] = 3;
                }

                if($data['offer_lists'][$offer_list_key]['distance'] > 1000){
                    $data['offer_lists'][$offer_list_key]['distance_sort'] = 4;
                }

                //是否有效
                $data['offer_lists'][$offer_list_key]['is_effective'] = 1;
                if(!$category_status[$goods->category_id]){
                    $data['offer_lists'][$offer_list_key]['is_effective'] = 0;
                }

                //格式化时间
                $data['offer_lists'][$offer_list_key]['updated_time'] = $offer_list_val->updated_at;
                $update_time = date('Y-m-d',strtotime($offer_list_val->updated_at));
                $today_time = date('Y-m-d',time());
                if($update_time == $today_time){
                    $data['offer_lists'][$offer_list_key]['updated_time'] = date('H:i',strtotime($offer_list_val->updated_at)).'发布';
                }else{
                    $data['offer_lists'][$offer_list_key]['updated_time'] = date('m/d',strtotime($offer_list_val->updated_at)).'发布';
                }

                //获取品类交易时间
                $goods_category = $goods->GoodsCategory;

                if($offer_list_val->status == GoodsOffer::STATUS['disable']){
                    $data['offer_lists'][$offer_list_key]['is_effective'] = 0;
                }

                if($offer_list_val->review_status != GoodsOffer::REVIEW_STATUS['passed']){
                    $data['offer_lists'][$offer_list_key]['is_effective'] = 0;
                }

                //提货城市
                $city = $this->Area::getAreaInfoByPid($goods_delivery_address->city);

                //拼装参数
                $data['offer_lists'][$offer_list_key]['updated_at'] = strtotime(date('Y-m-d',strtotime($offer_list_val->updated_at)));
                $data['offer_lists'][$offer_list_key]['delivery_city'] = $city->name;
                $data['offer_lists'][$offer_list_key]['single_number'] = $offer_list_val->single_number;
                $data['offer_lists'][$offer_list_key]['id'] = $offer_list_val->id;
                $data['offer_lists'][$offer_list_key]['describe'] = empty($offer_list_val->describe) ? '' : $offer_list_val->describe;
                $data['offer_lists'][$offer_list_key]['category_name'] = $goods_category->name;
                $data['offer_lists'][$offer_list_key]['stock'] = $offer_list_val->stock;
                $data['offer_lists'][$offer_list_key]['order_unit'] = $offer_list_val->order_unit;
                $data['offer_lists'][$offer_list_key]['offer_name'] = $offer_list_val->offer_pattern_name;
                $data['offer_lists'][$offer_list_key]['brand_name'] = $offer_list_val->product_area;
                $data['offer_lists'][$offer_list_key]['delivery_address'] = $goods_delivery_address->name;
                $data['offer_lists'][$offer_list_key]['offer_starttime'] = strtotime($offer_list_val->offer_starttime);
                $data['offer_lists'][$offer_list_key]['offer_endtime'] = strtotime($offer_list_val->offer_endtime);
                $data['offer_lists'][$offer_list_key]['tag_ids'] = $offer_list_val->tag_ids;
            }
        }

        //根据距离排序
        usort($data['offer_lists'],array($this, 'my_product_sort_default'));
        $data['paginate'] = pageing($offer_lists);

        //品类列表
        $category_list = $this->Goods::getGoodsCategories();
        $data['category_list'] = array();
        foreach ($category_list as $k=>$v){
            $data['category_list'][$k] = $v;
            if($v['name'] == GoodsCategory::NAME['soybean_meal']){
                $data['category_list'][$k]['sort'] = 1;
            }else{
                $data['category_list'][$k]['sort'] = 0;
            }
        }

        $arr6 = array_map(create_function('$n', 'return $n["sort"];'), $data['category_list']);
        array_multisort($arr6,SORT_DESC,$data['category_list'] );

        $new_offer_lists = [
            'xh' => [],
            'jc' => [],
            'qt' => []
        ];
        foreach ($data['offer_lists'] as $k => $v) {
            if ($v['offer_name'] === '基差价') {
                $new_offer_lists['jc'][] = $v;
            } elseif ($v['offer_name'] === '现货价') {
                $new_offer_lists['xh'][] = $v;
            } else {
                $new_offer_lists['qt'][] = $v;
            }
        }
        $data['offer_lists'] = [];
        foreach ($new_offer_lists as $k => $v) {
            foreach ($v as $kk => $vv) {
                $data['offer_lists'][] = $vv;
            }
        }

        //获取用户的标签
        $user = [];
        if(isset($this->Input['public_openid']) && isset($this->Input['public_appid'])){
            $user = $this->Account::getUserByPublicOpenidandAppid($this->Input['public_openid'],$this->Input['public_appid']);
            if (is_null($user)) {
                $user = [];
            }
        }
        if (empty($user)) {
            return apiReturn(-90009, '获取用户信息异常！');
        }
        $user_tags = [];
        if($user->account_type == Account::ACCOUNT_TYPE['buyer']){
            $param = [
                'user_id' => $user->id
            ];
            try{
                $client = new Client();
                $result = $client->request('post', $this->url.$this->getUserTags, ['json'=>$param])->getBody()->getContents();
                $user_tags = json_decode($result,true);
            }catch (\Exception $exception){
                return apiReturn(-90009, '获取用户标签异常！');
            }
        }

        //去除报价中有标签值且用户没有的标签
        foreach ($data['offer_lists'] as $k => $v) {
            if ($v['tag_ids'] !== '') {
                $tag_ids = explode(',', $v['tag_ids']);
                if (count(array_intersect($tag_ids, $user_tags)) === 0) {
                    unset($data['offer_lists'][$k]);
                }
            }
        }
        $data['offer_lists'] = array_values($data['offer_lists']);

        return apiReturn(0, '请求成功！',$data);
    }


    /**
     * 验证报价id
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function verifyOfferId()
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

        return false;
    }


    function getIp(){
        $ip='未知IP';
        if(!empty($_SERVER['HTTP_CLIENT_IP'])){
            return $this->is_ip($_SERVER['HTTP_CLIENT_IP'])?$_SERVER['HTTP_CLIENT_IP']:$ip;
        }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            return $this->is_ip($_SERVER['HTTP_X_FORWARDED_FOR'])?$_SERVER['HTTP_X_FORWARDED_FOR']:$ip;
        }else{
            return $this->is_ip($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:$ip;
        }
    }

    function is_ip($str){
        $ip=explode('.',$str);
        for($i=0;$i<count($ip);$i++){
            if($ip[$i]>255){
                return false;
            }
        }
        return preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/',$str);
    }


    //my_product_sort_default
    function my_product_sort_default($a,$b)
    {
        if($a['updated_at']<$b['updated_at']) {
            return 1;
        }else if($a['updated_at']==$b['updated_at']) {
            if($a['distance_sort']<$b['distance_sort']) {return -1;}
            else if($a['distance_sort']==$b['distance_sort']) {
                if($a['price']<$b['price']){
                    return -1;
                }else if($a['price']==$b['price']){
                    if($a['distance']<$b['distance']) {return 1;}
                    else return -1;
                }else {return 1;}
            }else return 1;
        }else{
            return -1;
        }
    }


    /**
     * 拼接参数
     * @param $attr
     * @return string
     */
    public function abuttedParam($attr)
    {

        //拼接参数
        if(!empty($attr) and (!is_null(json_decode($attr)))){
            $param = '';
            foreach (json_decode($attr,true) as $k=>$v){
                $param .= $v['name'].$v['default_value'].'，';
            }
            $param = rtrim($param,'，');

            return $param;
        }
    }


    /**
     * 参数列表
     * @param $attr
     * @return array
     */
    public function paramList($attr)
    {

        //参数列表
        if(!empty($attr) and (!is_null(json_decode($attr)))){
            $param = array();
            foreach (json_decode($attr,true) as $k=>$v){
                $param[$k]['name'] = $v['name'];
                $param[$k]['english_name'] = $v['english_name'];
                $param[$k]['default_value'] = $v['default_value'];
                $param[$k]['sort'] = $v['sort'];
            }

            return $param;
        }
    }


    /**
     * 是否为豆粕
     * @param $goods_attr_list
     * @return string
     */
    public function isSoybeanMeal($goods_attr_list)
    {

        IF(is_array($goods_attr_list)){
            $param_array = array();
            foreach ($goods_attr_list as $k=>$v){
                if($v['english_name'] == GoodsCategory::GOODS_ATTR['brand']){
                    $param_array[0] = $v['default_value'];
                }

                if($v['english_name'] == GoodsCategory::GOODS_ATTR['protein']){
                    $param_array[1] = $v['default_value'];
                }

                if($v['english_name'] == GoodsCategory::GOODS_ATTR['unit']){
                    $param_array[2] = $v['default_value'];
                }

                if($v['english_name'] == GoodsCategory::GOODS_ATTR['product_area']){
                    $param_array[3] = $v['default_value'];
                }

            }

            $param = '';
            if(isset($param_array[0])){
                $param = $param_array[3].$param_array[0];
            }

            if(isset($param_array[1])){
                $param = $param.$param_array[1].'/';
            }

            if(isset($param_array[2])){
                $param = $param.$param_array[2];
            }
            return $param;
        }
    }


    /**
     * 是否为基差价
     * @param $offer_attr_list
     * @return string
     */
    public function isBasisPrice($offer_attr_list)
    {

        IF(is_array($offer_attr_list)){
            $param = '';
            foreach ($offer_attr_list as $k=>$v){
                if($v['english_name'] == 'DSM'){
                    $param = $v['default_value'];
                }

            }
            return $param;
        }
    }


    /**
     * 判断品类是否有效
     * @return array
     */
    public function isEffective()
    {
        //获取品类交易时间
        $goods_category_trade = $this->Goods::getAllGoodsCategoryTrade();
        $category_status = array();

        foreach ($goods_category_trade as $k=>$v){

            //判断是否在交易年
            if(($v->start_time ) and ($v->end_time)){
                $start_time = strtotime($v->start_time);
                $end_time = strtotime($v->end_time);

                if( (time() < $start_time) or (time() > $end_time)){
                    $category_status[$v->category_id] = false;
                }else{
                    $category_status[$v->category_id] = true;
                }
            }

            //判断是否在交易日内
            if($v->trading_day){
                if(strpos($v->trading_day,date('w')) === false){
                    $category_status[$v->category_id] = false;
                }else{
                    $category_status[$v->category_id] = true;
                }
            }

            //判断是否在交易时间段
            if(!empty($v->time_slot)){
                $i = 0;
                foreach (json_decode($v->time_slot,true) as $key=>$val){
                    if( ( strtotime(date('H:i:s',time())) >= strtotime($val['start_time']) ) and (strtotime(date('H:i:s',time())) <= strtotime($val['end_time']))){
                        $i +=1;
                    }
                }

                if($i == 0){
                    $category_status[$v->category_id] = false;
                }else{
                    $category_status[$v->category_id] = true;
                }
            }
        }

        return $category_status;
    }
}
