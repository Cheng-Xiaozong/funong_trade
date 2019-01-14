<?php

namespace App\Http\Controllers\Admin;
use App\Admin;
use App\GoodsCategory;
use App\GoodsOfferAttribute;
use App\Order;
use App\GoodsOffer;
use App\AccountSeller;
use App\Services\AccountService;
use App\GoodsOfferPattern;
use App\Services\AddressService;
use App\Services\GoodsService;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Admin\CommonController;
use App\Services\MqttService;
use App\Services\OfferService;
use App\Services\AdminUserService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class OfferController extends BaseController
{
    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Msg;
    protected $Validator;
    protected $Address;
    protected $AdminUser;
    protected $Goods;
    protected $Account;
    protected $Area;
    protected $offer;
    protected $Mqtt;
    protected $Common;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AccountService $Account,
        OfferService $offer,
        AddressService $address,
        AdminUserService $admin_user,
        GoodsService $goods,
        Validator $validator,
        MqttService $mqtt,
        CommonController $common
    ){
        parent::__construct($request, $log, $redis);
        $this->Account = $Account;
        $this->Validator = $validator;
        $this->AdminUser = $admin_user;
        $this->Address = $address;
        $this->offer = $offer;
        $this->Goods = $goods;
        $this->Mqtt = $mqtt;
        $this->Common = $common;
    }



    /**
     * 获取可用的卖家列表
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function offerSeller()
    {

        $validator = $this->Validator::make($this->Input, [
            'action' => 'required | in:all',
        ], [
            'required' => '为必填项',
            'in' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result = $this->offer::offerSeller($this->Input);
        $data['seller_list'] = $result;
        return apiReturn(0, '获取成功！', $result);
    }


    /**
     * 获取可用的商品
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function offerGoods()
    {

        $validator = $this->Validator::make($this->Input, [
            'action' => 'required | in:all,enabled,disable',
            'seller_id' => 'required | int',
        ], [
            'required' => '为必填项',
            'string' => '必须是字符串',
            'int' => '必须是数字',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result = $this->offer::offerGoods($this->Input);
        $data['good_list'] = $result;
        return apiReturn(0, '获取成功！', $result);
    }


    /**
     * 获取某个品类下可用的报价模式
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function offerPattern()
    {
        $validator = $this->Validator::make($this->Input, [
            'action' => 'required | in:all,enabled,disable',
            'pattern_list' => 'required | array',
        ], [
            'required' => '为必填项',
            'array' => '必须是数组',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result = $this->offer::offerPattern($this->Input);
        $data['good_list'] = $result;
        return apiReturn(0, '请求成功！', $result);
    }


    /**
     * 获取某个商品类的地址
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function offerAddress()
    {
        $validator = $this->Validator::make($this->Input, [
            'action' => 'required | in:all,enabled,disable',
            'address_list' => 'required | array',
        ], [
            'required' => '为必填项',
            'array' => '必须是数组',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result = $this->offer::offerAddress($this->Input);
        $data['address_list'] = $result;
        return apiReturn(0, '请求成功！', $data);
    }

    /**
     * 报价列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function offerList()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->offer::offerList($this->Input['page_size'],$this->Input['page']);
        $data['offer_list']=$result;
        return apiReturn(0,'获取报价列表成功',$data);
    }


    /**
     * 新增报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function addOffer()
    {

        $validator = $this->Validator::make($this->Input, [
            'goods_id' => 'required',
            'offer_pattern_id' => 'required',
            'delivery_address_id' => 'required',
            'price' => 'required | numeric',
            'order_unit' => 'required|string',
            'describe' => 'string',
            'offer_info' => '',
            'stock' => 'required | numeric',
            'single_number' => 'required | numeric',
            'moq_number' => 'required | numeric',
            'offer_starttime' => 'required',
            'offer_endtime' => 'required',
            'delivery_starttime' => 'required',
            'delivery_endtime' => 'required',
        ], [
            'required' => '为必填项',
            'min' => '最短2位',
            'max' => '最长10位',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;

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

        if(isset($this->Input['offer_info'])){
            //获取属性参数
            $param = array();
            foreach ($this->Input['offer_info'] as $k=>$v){
                $param[$v['english_name']] = $v['english_name'];
            }

            //获取报价属性
            $templates = $this->offer::getOfferAttrsByPatternId($this->Input['offer_pattern_id']);
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
        $seller = $this->Account::getSellerByid($this->Input['seller_id']);;
        if($seller->quote_type == AccountSeller::QUOTE_TYPE['system']){
            $data['review_status'] = GoodsOffer::REVIEW_STATUS['passed'];
        }

        //报价模式
        $offer_pattern = $this->offer::getOfferPatternById($this->Input['offer_pattern_id']);
        $data['goods_name'] = $goods->name;
        $data['offer_pattern_name'] = $offer_pattern['name'];
        //提货地址获取一些信息
        $delivery_address = $this->Address::getAddressById($this->Input['delivery_address_id']);
        $data['name'] = $delivery_address['name'];
        $data['province'] = $delivery_address['province'];
        $data['city'] = $delivery_address['city'];
        $data['county'] = $delivery_address['county'];
        $data['address'] = $delivery_address['address'];
        $data['address_details'] = $delivery_address['address_details'];
        $data['lng'] = $delivery_address['lng'];
        $data['lat'] = $delivery_address['lat'];
        $data['account_businesses_id'] = $this->Input['account_businesses_id'];

        if($this->offer::createOffer($data)->id){
            return apiReturn(0, '添加报价成功！');
        }
        return apiReturn(-40000, '添加报价失败！');
    }


    /**
     * 编辑商品报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function editGoodsOffer()
    {

        //组装数据
        $validator = $this->Validator::make($this->Input, [
            'offer_id' => 'required',
            'goods_id' => 'required',
            'price' => 'required | numeric',
            'order_unit' => 'required|string',
            'describe' => 'string',
            'offer_info' => '',
            'stock' => 'required | numeric',
            'single_number' => 'required | numeric',
            'moq_number' => 'required | numeric',
            'offer_starttime' => 'required',
            'offer_endtime' => 'required',
            'delivery_starttime' => 'required',
            'delivery_endtime' => 'required',
            'delivery_address_id' => 'required',
        ], [
            'required' => ':attribute为必填项',
            'min' => ':attribute最短2位',
            'max' => ':attribute最长10位',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;

        if(isset($this->Input['offer_info'])){
            //获取属性参数
            $param = array();
            foreach ($this->Input['offer_info'] as $k=>$v){
                $param[$v['english_name']] = $v['english_name'];
            }

            //获取报价属性
            $templates = $this->offer::getOfferAttrsByPatternId($this->Input['offer_pattern_id']);
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

        //报价审核方式
        $seller = $this->Account::getSellerByid($goods->seller_id);;
        if($seller->quote_type == AccountSeller::QUOTE_TYPE['system']){
            $data['review_status'] = GoodsOffer::REVIEW_STATUS['passed'];
        }

        //报价模式
        $offer_pattern = $this->offer::getOfferPatternById($this->Input['offer_pattern_id']);
        $data['offer_pattern_name'] = $offer_pattern['name'];
        $data['goods_name'] = $goods->name;
        //提货地址获取一些信息
        $delivery_address = $this->Address::getAddressById($this->Input['delivery_address_id']);
        $data['name'] = $delivery_address['name'];
        $data['province'] = $delivery_address['province'];
        $data['city'] = $delivery_address['city'];
//        $data['county'] = $delivery_address['county'];
        $data['address'] = $delivery_address['address'];
        $data['address_details'] = $delivery_address['address_details'];
        $data['lng'] = $delivery_address['lng'];
        $data['lat'] = $delivery_address['lat'];
        unset($data['offer_id']);
        if($this->offer::updateOfferById($this->Input['offer_id'],$data)){
            return apiReturn(0, '修改成功！');
        }

        return apiReturn(-40001, '修改报价失败！');
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

        $goods_offer = $this->offer::getGoodsOfferById($this->Input['offer_id']);
        $data['offer_detail'] = $goods_offer;

        if(!count($data['offer_detail'])){
            return apiReturn(-40010, '报价不存在！');
        }
        return apiReturn(0, '请求成功！',$data);
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
            }

            return $param;
        }
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

        $offer = $this->offer::getGoodsOfferById($this->Input['offer_id']);

        if(!count($offer)){
            return apiReturn(-40003, '报价不存在！');
        }

        $order = $offer->order;
        if(count($order)){
            return apiReturn(-40005, '该报价下有订单,无法删除！');
        }

        if($this->offer::deleteGoodsOfferById($this->Input['offer_id'])){
            return apiReturn(0, '删除成功！');
        }

        return apiReturn(-40004, '报价删除失败！');
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

    /**
     * 搜索报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchOffer()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
            'where' => 'array|required',
            'where.status' => 'integer',
            'where.review_status' => 'integer',
            'where.seller_id' => 'integer',
            'where.offer_pattern_id' => 'integer',
            'where.delivery_address_id' => 'integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['all_Offer']=$this->offer::searchOffer($this->Input['where'],$this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 获取报价模式
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOfferPattern()
    {
        $data['offer_patterns'] = array();
        $data['offer_patterns'] =$this->offer::getOfferPattern();
        return apiReturn(0, '请求成功 !',$data);
    }

    /**
     * 新增报价模式
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Support\Facades\Exception
     */
    public function addOfferPatterns()
    {
        //验证类 如果有新增属性就做校验 没有不用做校验
        if(!empty($this->Input['offer_attr'])){
            $validator = $this->Validator::make($this->Input, [
                'name' => 'required|unique:goods_offer_patterns,name',
                'offer_attr' => 'required|array',
                'offer_attr.*.name' => 'required',
                'offer_attr.*.describe' => 'required',
                'offer_attr.*.english_name' => 'required',
                'offer_attr.*.sort' => 'required|integer',
                'offer_attr.*.is_necessary' => 'required|integer',
                'offer_attr.*.type' => 'required|in:string,int',
                'offer_attr.*.control_type' => 'required|integer',
                'status' => 'required',
            ], [
                'required' => '为必填项',
                'integer' => '必须为整数',
                'array' => '必须为数组',
                'date' => '日期不合法',
                'unique' => '已存在',
                'name.unique'=>'报价模式名称已存在！',
            ]);
        }else{
            $validator = $this->Validator::make($this->Input, [
                'name' => 'required|unique:goods_offer_patterns,name',
                'status' => 'required',
            ], [
                'required' => '为必填项',
                'unique' => '已存在',
                'name.unique'=>'报价模式名称已存在！',
            ]);
        }

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        //没有报价属性 只要操作一张表 有新增报价模式 同时要新增报价模式属性表
        if(!empty($this->Input['offer_attr'])){
            //映射报价模式属性
            $data_attr=$this->Input['offer_attr'];
            DB::beginTransaction();
            //开始创建报价模式
            $data = $this->Input;
            unset($data['offer_attr']);
            $pattern_status=$this->offer::addOfferPatterns($data);
            if(empty($pattern_status)){
                DB::rollBack();
                return apiReturn(-40007, '添加报价模式失败！');
            }
            foreach ($data_attr as $key => $val)
            {
                //可选值
                if(isset($val['avaliable_value']))
                {
                    $val['avaliable_value']= json_encode($val['avaliable_value'],JSON_UNESCAPED_UNICODE);
                }
                $val['pattern_id']=$pattern_status->id;
                if(!$this->offer::createOfferAttribute($val)){
                    DB::rollBack();
                    return apiReturn(-40005, '属性创建失败！');
                }
            }
            DB::commit();
            return apiReturn(0, '添加报价模式成功！');
        }else{
            $pattern_status=$this->offer::addOfferPatterns($this->Input);
            if(empty($pattern_status)){
                return apiReturn(-40007, '添加报价模式失败！');
            }else{
                return apiReturn(0,'添加报价模式成功！');
            }
        }

    }


    /**
     * 修改报价模式
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Support\Facades\Exception
     */
    public function updateOfferPatterns()
    {
        //验证类 如果有新增属性就做校验 没有不用做校验
        if(!empty($this->Input['offer_attr'])){
            $validator = $this->Validator::make($this->Input, [
                'pattern_id' => 'required',
                'name' => 'required',
                'offer_attr' => 'required|array',
                'offer_attr.*.name' => 'required',
                'offer_attr.*.describe' => 'required',
                'offer_attr.*.english_name' => 'required',
                'offer_attr.*.sort' => 'required|integer',
                'offer_attr.*.is_necessary' => 'required|integer',
                'offer_attr.*.type' => 'required|in:string,int',
                'offer_attr.*.control_type' => 'required|integer',
                'status' => 'required',
            ], [
                'required' => '为必填项',
                'integer' => '必须为整数',
                'array' => '必须为数组',
                'date' => '日期不合法',
                'unique' => '已存在',
                'name.unique'=>'报价模式名称已存在！',
            ]);
        }else{
            $validator = $this->Validator::make($this->Input, [
                'pattern_id' => 'required',
                'name' => 'required',
                'status' => 'required',
            ], [
                'required' => '为必填项',
                'unique' => '已存在',
                'name.unique'=>'报价模式名称已存在！',
            ]);
        }
        $validator->after(function($validator) {
            $full_name=$this->offer::uniqueOfferName($this->Input['name'],$this->Input['pattern_id']);
            if(!empty($full_name)){
                $validator->errors()->add('name', '此报价模式名称已存在！');
            }
        });
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        //报价模式主表
        $pattern['name']=$this->Input['name'];
        $pattern['status']=$this->Input['status'];
        //没有报价属性 只要操作一张表 有新增报价模式 同时要新增报价模式属性表
        DB::beginTransaction();
        //先删除原有的报价属性 前提必须判断此报价是否之前包含报价属性
        $pattern_attr_status=$this->offer->getGoodsOfferByAttributes($this->Input['pattern_id']);
        if(!empty($pattern_attr_status)){
            $result_num=$this->offer::deleteOfferPatternsAttributeById($this->Input['pattern_id']);
            if($result_num==0)
            {
                DB::rollBack();
                return apiReturn(-40016, '报价属性删除失败！');
            }
        }
        if(!empty($this->Input['offer_attr'])){
            //映射报价模式属性
            $data_attr=$this->Input['offer_attr'];

            //报价属性修改
            foreach ($data_attr as $key => $val)
            {
                //可选值
                if(isset($val['avaliable_value']))
                {
                    $val['avaliable_value']= json_encode($val['avaliable_value'],JSON_UNESCAPED_UNICODE);
                }
                $val['pattern_id']=$this->Input['pattern_id'];
                if(!$this->offer::createOfferAttribute($val)){
                    DB::rollBack();
                    return apiReturn(-40005, '属性创建失败！');
                }
            }
            //报价修改
            $pattern_status=$this->offer::updateOfferPatterns($pattern,$this->Input['pattern_id']);
            if(empty($pattern_status)){
                DB::rollBack();
                return apiReturn(-40008, '修改报价模式失败！');
            }
            DB::commit();
            return apiReturn(0,'修改报价模式成功！');
        }else{
            //报价修改
            $pattern_status=$this->offer::updateOfferPatterns($pattern,$this->Input['pattern_id']);
            if(empty($pattern_status)){
                return apiReturn(-40008, '修改报价模式失败！');
            }
            DB::commit();
            return apiReturn(0,'修改报价模式成功！');
        }
    }

    /**
     * 删除报价模式
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteOfferPatterns()
    {
        $validator = $this->Validator::make($this->Input, [
            'patterns_id' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'number' => ':attribute为数字',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $goods_offer_patterns_status = $this->offer::getGoodsOfferByPatterns($this->Input['patterns_id']);
        if($goods_offer_patterns_status){
            return apiReturn(-40012, '对不起，您无法删除此报价模式，在商品报价表里有使用到此报价模式！');
        }

        $goods_offer_attributes_status = $this->offer::getGoodsOfferByAttributes($this->Input['patterns_id']);
        if($goods_offer_attributes_status){
            return apiReturn(-40013, '对不起，您无法删除此报价模式，在商品报价模式属性表里有使用到此报价模式！');
        }

        $goods_categories_status = $this->offer::getGoodsByCategories($this->Input['patterns_id']);
        if($goods_categories_status){
            return apiReturn(-40014, '对不起，您无法删除此报价模式，在商品品类表里有使用到此报价模式！');
        }

        if($this->offer::deleteGoodsOfferPatternsById($this->Input['patterns_id'])){
            return apiReturn(0, '删除成功！');
        }

        return apiReturn(-40011, '报价模式删除失败！');
    }


    /**
     * 报价模式列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function OfferPatternsList()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->offer::OfferPatternsList($this->Input['page_size'],$this->Input['page']);
        $data['patterns_list']=$result;
        return apiReturn(0,'报价模式列表成功',$data);
    }



    /**
     * 搜索报价模式列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchOfferPatterns()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
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
        $data['patterns_list']=$this->offer::searchOfferPatterns($this->Input['where'],$this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 根据ID获取报价模式详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOfferPatternsById()
    {
        $validator = $this->Validator::make($this->Input, [
            'patterns_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $result=$this->offer::getOfferPatternsById($this->Input['patterns_id']);

        return apiReturn(0,'获取成功',$result);
    }


    /**
     * 根据报价ID获取报价模式属性详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOfferPatternsAttributeById()
    {
        $validator = $this->Validator::make($this->Input, [
            'pattern_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $result=$this->offer::getOfferPatternsAttributeById($this->Input['pattern_id']);

        return apiReturn(0,'获取成功',$result);
    }



    /**
     * 审核报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function auditOffer()
    {
        $content=$this->Input;
        $validator = $this->Validator::make($content, [
            'offer_id' => 'required | integer',
            'review_status' => 'required | array',
            'review_status.status' => 'required | integer',
            'review_status.describe' => 'required',
//            'review_status.remark' => 'required'
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $goods=$this->offer::getOfferById($content['offer_id']);
        if($goods->review_log)
        {
            $review_log=json_decode($goods->review_log,true);
        }
        $review_data['review_detail']=$content['review_status'];
        //TODO 审核日志
        $review_data['review_reviewer']=$this->AdminUser::getUserByToken($this->Request->header('token'));
        $review_data['review_time']=date('Y-m-d H:i:s');
        $review_log[]=$review_data;
        $data['review_log']=json_encode($review_log,JSON_UNESCAPED_UNICODE);
        $data['review_status']=$content['review_status']['status'];
        $data['review_details']=json_encode($content['review_status'],JSON_UNESCAPED_UNICODE);
        if($this->offer::updateOfferById($goods->id,$data))
        {

            //mqtt消息
            $account_id = $goods->businesses->account_id;
            if($data['review_status'] == GoodsOffer::REVIEW_STATUS['passed']){
                $this->Mqtt->sendCommonMsg('offer','您的报价审核通过!',$goods->id,$account_id);
            }else{
                $this->Mqtt->sendCommonMsg('offer','您的报价审核未通过!',$goods->id,$account_id);
            }
            return apiReturn(0,'审核成功');
        }else{
            return apiReturn(-40015, '审核失败！');
        }
    }

}
