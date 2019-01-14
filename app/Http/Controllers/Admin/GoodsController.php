<?php

namespace App\Http\Controllers\Admin;

use App\Goods;
use App\GoodsCategory;
use App\GoodsCategoryAttribute;
use App\AccountSeller;
use App\GoodsOffer;
use App\Http\Controllers\BaseController;
use App\Services\AddressService;
use App\Services\AdminUserService;
use App\Services\AreaInfoService;
use App\Services\GoodsService;
use App\Services\MqttService;
use App\Services\AccountService;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Debug\Debug;
use Intervention\Image\Facades\Image;

class GoodsController extends BaseController
{
    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Goods;
    protected $Address;
    protected $AdminUser;
    protected $Msg;
    protected $Validator;
    protected $Area;
    protected $Mqtt;
    protected $Account;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        GoodsService $goods,
        AddressService $address,
        AccountService $Account,
        AdminUserService $admin_user,
        Validator $validator,
        AreaInfoService $area,
        MqttService $mqtt
    ){
        parent::__construct($request, $log, $redis);
        $this->Goods = $goods;
        $this->Address = $address;
        $this->AdminUser = $admin_user;
        $this->Validator = $validator;
        $this->Area = $area;
        $this->Mqtt = $mqtt;
        $this->Account = $Account;
    }

    /**
     * 获取全部商品
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllGoods()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer'
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['all_goods']=$this->Goods::getAllGoods($this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }

    /**
     * 获取全部商品品类
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllGoodsCategory()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer'
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['goods_categorys']=$this->Goods::getAllGoodsCategory($this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }

    /**
     * 获取分类，对外接口
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGoodsCategoryApi()
    {
        $validator = $this->Validator::make($this->Input, [
            'action' => 'required | in:all,enabled,disable',
        ], [
            'required' => '为必填项',
            'in' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['category_list']=$this->Goods::geGoodsCategoryApi($this->Input['action']);
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 获取品类详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGoodsCategory()
    {
        $validator = $this->Validator::make($this->Input, [
            'category_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $category=$this->Goods::getCategoryById($this->Input['category_id']);
        if(!empty($category->default_image))
        {
            $category->default_image=getImgUrl($category->default_image,'goods_category_images','');
        }
        $data['goods_category']=$category;
        $data['goods_category']['goods_category_attribute']=$category->GoodsCategoryAttribute;
        $data['goods_category']['goods_category_trade']=$category->GoodsCategoryTrade;
        return apiReturn(0,'获取成功',$data);
    }

    /**
     * 搜索商品品类
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchGoodsCategory()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
            'where' => 'array|required',
            'where.status' => 'integer',
            'where.is_upload_image' => 'integer',
            'where.is_upload_vedio' => 'integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['goods_categorys']=$this->Goods::searchGoodsCategory($this->Input['where'],$this->Input['page_size']);
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
            'page' => 'required | integer',
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
        $data['all_goods']=$this->Goods::searchGoods($this->Input['where'],$this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }

    /**
     * 商品审核
     * @return \Illuminate\Http\JsonResponse
     */
    public function auditGoods()
    {
        $content=$this->Input;
        $validator = $this->Validator::make($content, [
            'goods_id' => 'required | integer',
            'review_status' => 'required | array',
            'review_status.status' => 'required | integer',
            'review_status.describe' => 'required',
            'review_status.remark' => 'max:20'
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
            'max' => '不合法',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $goods=$this->Goods::getGoodsById($content['goods_id']);
        if($goods->review_log)
        {
            $review_log=json_decode($goods->review_log,true);
        }
        $review_data['review_detail']=$content['review_status'];
        $admin_user=$this->AdminUser::getUserByToken($this->Request->header('token'));
        $review_data['review_reviewer']=$admin_user->full_name;
        $review_data['review_time']=date('Y-m-d H:i:s');
        $review_log[]=$review_data;
        $data['review_log']=json_encode($review_log,JSON_UNESCAPED_UNICODE);
        $data['review_status']=$content['review_status']['status'];
        $data['review_details']=json_encode($content['review_status'],JSON_UNESCAPED_UNICODE);
        if($this->Goods::updateGoodsById($goods->id,$data))
        {

            //mqtt消息
            $account_id = $goods->AccountBusiness->account_id;
            if($data['review_status'] == Goods::REVIEW_STATUS['passed']){
                $this->Mqtt->sendCommonMsg('goods','您的商品审核通过!',$goods->id,$account_id);
            }else{
                $this->Mqtt->sendCommonMsg('goods','您的商品审核未通过!',$goods->id,$account_id);
            }
            return apiReturn(0,'审核成功');
        }else{
            return apiReturn(-30001, '审核失败！');
        }
    }

    /**
     * 根据ID获取商品
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

    /**
     * 删除商品
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function deleteGoods()
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
        $goods = $this->Goods::getGoodsById($this->Input['goods_id']);
        if(is_null($goods)){
            return apiReturn(-30002, '商品不存在！');
        }

        //查看商品是否存在报价
        $goods_offers = $goods->GoodsOffer;

        if(count($goods_offers)){
            return apiReturn(-30005, '商品存在报价，无法删除！');
        }

        //删除商品
        if($this->Goods::deleteGoodsById($this->Input['goods_id'])==0){
            return apiReturn(-30004, '商品删除失败！');
        }else{
            return apiReturn(0, '删除成功！');
        }
    }

    /**
     * 编辑商品
     * @return \Illuminate\Http\JsonResponse
     */
    public function editGoods()
    {

        //验证文件
        $files=$this->Request->file();
        foreach ($files as $key=>$val)
        {
            foreach ($val as $k=>$v)
            {
                $error=[];
                //判断文件是否出错
                if($v->getError()!=0)
                {
                    $error[$key][]='上传失败，请检查系统配置！';
                }
                //判断的文件是否超出2M
                if( $v->getClientSize()>20971520)
                {
                    $error[$key][]='超出文件最大限制20M';
                }
                if($error){
                    $data['errors']=$error;
                    return apiReturn(-105, '表单验证失败', $data);
                }
            }
        }

        //验证参数
        $this->Input=json_decode($this->Request->all()['form_data'],true);
        $validator = $this->Validator::make($this->Input, [
            'goods_id' => 'required | integer',
            'category_id'=>'required | integer',
            'name'=>'required',
            'delivery_address_id' => 'required | array',
            'goods_attrs'=>'required | array',
            'custom_attrs'=>'array',
            'status'=>'required|integer',
            'review_status'=>'required | integer',
            'details'=>'required',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '必须为数组',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-105, '表单验证失败', $data);
        }

        //验证商品属性
        $param =[];
        foreach ($this->Input['goods_attrs'] as $k=>$v){
            $param[$v['english_name']] = $v['default_value'];
        }
        $templates = $this->Goods::getAttrsByCategoryId($this->Input['category_id']);
        foreach($templates as $template_key => $template_val){
            if($template_val->is_necessary == GoodsCategoryAttribute::IS_NECESSARY['yes']){
                if(!isset($param[$template_val->english_name]) || $param[$template_val->english_name]==''){
                    $error['errors']['goods_attrs'][$template_val->english_name] = '为必传项!';
                    return apiReturn(-105, '表单验证失败', $error);
                }
            }
        }

        //验证商品是否存在
        $goods = $this->Goods::getGoodsById($this->Input['goods_id']);
        if(is_null($goods)){
            return apiReturn(-30007, '商品不存在！');
        }

        //上传文件
        $files_name=[];
        foreach ($files as $key=>$val)
        {
            foreach ($val as $k=>$v)
            {
                if($v->isValid()){
                    $ext = $v->getClientOriginalExtension();     // 扩展名
                    $realPath = $v->getRealPath();   //临时文件的绝对路径
                    $filename = date('Y-m-d-H-i-s') . '-' . uniqid() . '.' . $ext;// 生成的文件名
                    if($key=='faces')
                    {
                        $disk='goods_imgs';
                    }
                    if($key=='vedios')
                    {
                        $disk='goods_vedios';
                    }
                    $result = Storage::disk($disk)->put(date('Y-m-d').'/'.$filename, file_get_contents($realPath));
                    if($result){
                        $files_name[$key][$k]=$filename;
                    }else{
                        return apiReturn(-30007, '文件保存失败，请检查服务器配置！');
                    }
                }
            }
        }

        $data=[];
        $data['name']=$this->Input['name'];
        $data['short_name']=$this->Input['short_name'];
        $data['search_keywords']=$this->Input['search_keywords'];
        $data['details']=$this->Input['details'];
        $data['review_status']=$this->Input['review_status'];
        if(isset($this->Input['bar_code']))
        {
            $data['bar_code']=$this->Input['bar_code'];
        }

        if(isset($this->Input['short_name']))
        {
            $data['short_name']=$this->Input['short_name'];
        }
        if(isset($this->Input['search_keywords']))
        {
            $data['search_keywords']=$this->Input['search_keywords'];
        }
        $data['status']=$this->Input['status'];

        if(isset($this->Input['goods_attrs'])&&!empty($this->Input['goods_attrs']))
        {
            $data['goods_attrs'] = json_encode($this->Input['goods_attrs'],JSON_UNESCAPED_UNICODE);
        }

        $data['delivery_address_id'] = implode(',',$this->Input['delivery_address_id']);
        //是否有文件上传
        if($files_name)
        {
            $data=array_merge($data,$files_name);
        }
        //商品图片
        if(isset($data['faces']))
        {
            $data['faces'] = implode(',',$data['faces']);
        }
        //商品视频
        if(isset($data['vedios']))
        {
            $data['vedios'] = implode(',',$data['vedios']);
        }
        //自定义属性
        if(isset($this->Input['custom_attrs'])&&!empty($this->Input['custom_attrs']))
        {
            $data['custom_attrs'] = json_encode($this->Input['custom_attrs'],JSON_UNESCAPED_UNICODE);
        }

        if($this->Goods::updateGoodsById($this->Input['goods_id'],$data)){
            $this->delOldGoodsFile($goods->toArray(),$data);
            return apiReturn(0, '编辑成功！');
        }else{
            $this->delGoodsFile($data);
            return apiReturn(-30008, '编辑失败！');
        }
    }

    /**
     * 添加商品
     * @return \Illuminate\Http\JsonResponse
     */
    public function addGoods()
    {
        //验证文件
        $files=$this->Request->file();
        foreach ($files as $key=>$val)
        {
            foreach ($val as $k=>$v)
            {
                $error=[];
                //判断文件是否出错
                if($v->getError()!=0)
                {
                    $error[$key][]='上传失败，请检查系统配置！';
                }
                //判断的文件是否超出2M
                if( $v->getClientSize()>2097152)
                {
                    $error[$key][]='超出文件最大限制2M';
                }
                if($error){
                    $data['errors']=$error;
                    return apiReturn(-105, '表单验证失败', $data);
                }
            }
        }

        //验证参数
        $this->Input=json_decode($this->Request->all()['form_data'],true);
        $validator = $this->Validator::make($this->Input, [
            'seller_id' => 'required | integer',
            'category_id' => 'required | integer',
            'account_employee_id' => 'required | integer',
            'account_business_id' => 'required | integer',
            'delivery_address_id' => 'required | array',
            'name' => 'required',
            'goods_attrs'=>'required | array',
            'custom_attrs'=>'array',
            'status'=>'required | integer',
            'review_status'=>'required | integer',
            'details'=>'required',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '必须为数组',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-105, '表单验证失败', $data);
        }

        //验证商品属性
        $param =[];
        foreach ($this->Input['goods_attrs'] as $k=>$v){
            $param[$v['english_name']] = $v['default_value'];
        }
        $templates = $this->Goods::getAttrsByCategoryId($this->Input['category_id']);
        foreach($templates as $template_key => $template_val){
            if($template_val->is_necessary == GoodsCategoryAttribute::IS_NECESSARY['yes']){
                if(!isset($param[$template_val->english_name]) || $param[$template_val->english_name]==''){
                    $error['errors']['goods_attrs'][$template_val->english_name] = '为必传项!';
                    return apiReturn(-105, '表单验证失败', $error);
                }
            }
        }


        //上传文件
        $files_name=[];
        foreach ($files as $key=>$val)
        {
            foreach ($val as $k=>$v)
            {
                if($v->isValid()){
                    $ext = $v->getClientOriginalExtension();     // 扩展名
                    $realPath = $v->getRealPath();   //临时文件的绝对路径
                    $filename = date('Y-m-d-H-i-s') . '-' . uniqid() . '.' . $ext;// 生成的文件名
                    if($key=='faces')
                    {
                        $disk='goods_imgs';
                    }
                    if($key=='vedios')
                    {
                        $disk='goods_vedios';
                    }
                    $result = Storage::disk($disk)->put(date('Y-m-d').'/'.$filename, file_get_contents($realPath));
                    if($result){
                        $files_name[$key][$k]=$filename;
                    }else{
                        return apiReturn(-30005, '文件保存失败，请检查服务器配置！');
                    }
                }
            }
        }
        $data=$this->Input;
        if($files_name)
        {
            $data=array_merge($data,$files_name);
        }

        //拼接数据
        if(isset($this->Input['bar_code']))
        {
            $data['bar_code']=$this->Input['bar_code'];
        }

        //商品审核方式
        $seller = $this->Account::getSellerByid($this->Input['seller_id']);;
        if($seller->release_type == AccountSeller::RELEASE_TYPE['system']){
            $data['review_status'] = Goods::REVIEW_STATUS['passed'];
        }

        $data['code'] = generateNumber('SP');
        $data['goods_attrs'] = json_encode($data['goods_attrs'],JSON_UNESCAPED_UNICODE);
        if(isset($data['custom_attrs']))
        {
            $data['custom_attrs'] = json_encode($data['custom_attrs'],JSON_UNESCAPED_UNICODE);
        }
        $data['category_code'] = $this->Goods::getCategoryById($data['category_id'])->code;
        $data['delivery_address_id'] = implode(',',$data['delivery_address_id']);
        //商品图片
        if(isset($data['faces']))
        {
            $data['faces'] = implode(',',$data['faces']);
        }
        //商品视频
        if(isset($data['vedios']))
        {
            $data['vedios'] = implode(',',$data['vedios']);
        }
        if($this->Goods::createGoods($data)){
            return apiReturn(0, '添加成功！');
        }else{
            $this->delGoodsFile($data);
            return apiReturn(-30006, '添加失败');
        }
    }


    /**
     * 删除商品文件
     * @param $data | array
     */
    public function delGoodsFile($data=null)
    {
        if(isset($data['faces']))
        {
            delFile($data['faces'],'goods_imgs');
        }

        if(isset($data['vedios']))
        {
            delFile($data['vedios'],'goods_vedios');
        }
    }

    /**
     * 删除商品图片
     * @param $goods
     * @param $data
     */
    public function delOldGoodsFile($goods, $data)
    {
        if(isset($data['faces']))
        {
            delFile($goods['faces'],'goods_imgs');
        }

        if(isset($data['vedios']))
        {
            delFile($goods['vedios'],'goods_vedios');
        }
    }

    /**
     * 商品启用禁用
     * @return \Illuminate\Http\JsonResponse
     */
    public function editGoodsStatus()
    {
        $validator = $this->Validator::make($this->Input, [
            'goods_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '不合法',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $goods=$this->Goods::getGoodsById($this->Input['goods_id']);
        if(is_null($goods))
        {
            return apiReturn(-30009,'商品不存在！');
        }
        $data['status']= ($goods->status==Goods::STATUS['enable']) ? Goods::STATUS['disable'] : Goods::STATUS['enable'];
        if($this->Goods::updateGoodsById($this->Input['goods_id'],$data)){
            return apiReturn(0, '更新成功');
        }else{
            return apiReturn(-30010, '更新失败');
        }
    }

    /**
     * 商品模板
     * @return \Illuminate\Http\JsonResponse
     */
    public function goodsTemplate()
    {
        $validator = $this->Validator::make($this->Input, [
            'category_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '不合法',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }

        $data['template_lists'] = array();
        $category = $this->Goods::getCategoryById($this->Input['category_id']);
        $templates = $this->Goods::getAttrsByCategoryId($this->Input['category_id']);

        //解析json
        foreach ($templates as $template_key => $template_val){
            //是否为json格式
            if((!is_null(json_decode($template_val->avaliable_value)))){
                $template_val->avaliable_value = json_decode($template_val->avaliable_value,true);
            }
        }
        //是否允许上传图片和视频
        $data['upload_vedio'] = $category->is_upload_vedio;
        $data['upload_image'] = $category->is_upload_image;
        $data['template_lists'] = $templates;
        return apiReturn(0, '请求成功！', $data);
    }

    /**
     * 添加商品品类
     * @return \Illuminate\Http\JsonResponse
     */
    public function addGoodsCategory()
    {
        //验证参数
        $this->Input=json_decode($this->Request->all()['form_data'],true);
        $validator = $this->Validator::make($this->Input, [
            'categories_info' => 'required | array',
            'categories_info.name' => 'required| unique:goods_categories,name',
            'categories_info.offer_type' => 'required|array',
            'categories_info.status' => 'required|integer',
            'categories_info.is_upload_image' => 'required|integer',
            'categories_info.is_upload_vedio' => 'required|integer',
            'categories_attr' => 'required|array',
            'categories_attr.*.name' => 'required',
            'categories_attr.*.describe' => 'required',
            'categories_attr.*.english_name' => 'required',
            'categories_attr.*.sort' => 'required|integer',
            'categories_attr.*.is_necessary' => 'required|integer',
            'categories_attr.*.type' => 'required|in:string,int',
            'categories_attr.*.control_type' => 'required|integer',
            'categories_time'=>'array',
            'categories_time.trading_day'=>'array',
            'categories_time.start_time'=>'date',
            'categories_time.end_time'=>'date',
            'categories_time.exclude_time'=>'array',
            'categories_time.exclude_time.*'=>'date',
            'categories_time.time_slot'=>'array',
            'categories_time.status'=>'integer'
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '必须为数组',
            'date' => '日期不合法',
            'unique' => '已存在',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-105, '表单验证失败', $data);
        }

        //验证文件
        $files_name=$this->uploadGoodsDefaultImage();
        if(is_object($files_name))
        {
            return $files_name;
        }

        //创建分类
        DB::beginTransaction();
        $data_info=$this->Input['categories_info'];
        if($files_name)
        {
            $data_info['default_image']=$files_name;
        }
        $data_info['offer_type']= implode(',',$data_info['offer_type']);
        $data_info['code']=  generateNumber('PL');
        $goods_category=$this->Goods::createGoodsCategory($data_info);
        if(is_null($goods_category))
        {
            DB::rollBack();
            return apiReturn(-30012, '品类创建失败！');
        }

        //创建品类属性
        $data_attr=$this->Input['categories_attr'];
        foreach ($data_attr as $key => $val)
        {
            $val['category_id']=$goods_category->id;
            //可选值
            if(isset($val['avaliable_value']))
            {
                $val['avaliable_value']= json_encode($val['avaliable_value'],JSON_UNESCAPED_UNICODE);
            }

            if(!$this->Goods::createGoodsCategoryAttribute($val)){
                DB::rollBack();
                return apiReturn(-30013, '属性创建失败！');
            }
        }

        //创建品类交易时间
        if(isset($this->Input['categories_time']))
        {
            $data_time=$this->Input['categories_time'];
            //交易星期
            if(isset($data_time['trading_day']))
            {
                $data_time['trading_day']= implode(',',$data_time['trading_day']);
            }
            //排除日期
             if(isset($data_time['exclude_time']))
             {
                 $data_time['exclude_time']= implode(',',$data_time['exclude_time']);
             }
             //交易时间段
            if(isset($data_time['time_slot']))
            {
                $data_time['time_slot']= json_encode($data_time['time_slot']);
            }
            $data_time['category_id']= $goods_category->id;
            if(!$this->Goods::createGoodsCategoryTrade($data_time)){
                DB::rollBack();
                return apiReturn(-30014, '交易时间创建失败！');
            }
        }
        DB::commit();
        return apiReturn(0, '操作成功！');
    }

    /**
     * 编辑商品品类
     * @return \Illuminate\Http\JsonResponse
     */
    public function editGoodsCategory()
    {
        //验证参数
        $this->Input=json_decode($this->Request->all()['form_data'],true);
        $validator = $this->Validator::make($this->Input, [
            'categories_info' => 'required | array',
            'categories_info.category_id' => 'required|integer',
            'categories_info.name' => 'required',
            'categories_info.offer_type' => 'required|array',
            'categories_info.status' => 'required|integer',
            'categories_info.is_upload_image' => 'required|integer',
            'categories_info.is_upload_vedio' => 'required|integer',
            'categories_attr' => 'required|array',
            'categories_attr.*.name' => 'required',
            'categories_attr.*.describe' => 'required',
            'categories_attr.*.english_name' => 'required',
            'categories_attr.*.sort' => 'required|integer',
            'categories_attr.*.is_necessary' => 'required|integer',
            'categories_attr.*.type' => 'required|in:string,int',
            'categories_attr.*.control_type' => 'required|integer',
            'categories_time'=>'array',
            'categories_time.trading_day'=>'array',
            'categories_time.start_time'=>'date',
            'categories_time.end_time'=>'date',
            'categories_time.exclude_time'=>'array',
            'categories_time.exclude_time.*'=>'date',
            'categories_time.time_slot'=>'array',
            'categories_time.status'=>'integer'
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '必须为数组',
            'date' => '日期不合法',
            'unique' => '必须唯一',
        ]);
        $validator->after(function($validator) {
            $goods_categorys=$this->Goods::getGoodsCategoryByName($this->Input['categories_info']['name']);
            if(count($goods_categorys)>1){
                $validator->errors()->add('categories_info.name', '必须唯一！');
            }
        });

        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-105, '表单验证失败', $data);
        }

        //验证是否存在
        $goods_category=$this->Goods::getCategoryById($this->Input['categories_info']['category_id']);
        if(is_null($goods_category))
        {
            return apiReturn(-30016, '分类不存在！');
        }

        //验证文件
        if(isset($this->Request->file()['default_image']))
        {
            $files_name=$this->uploadGoodsDefaultImage();
            if(is_object($files_name))
            {
                return $files_name;
            }

            if($files_name)
            {
                $data_info['default_image']=$files_name;
            }
        }


        //编辑分类
        DB::beginTransaction();
        $data_info['name']= $this->Input['categories_info']['name'];
        $data_info['offer_type']= implode(',',$this->Input['categories_info']['offer_type']);
        $data_info['status']= $this->Input['categories_info']['status'];
        $data_info['is_upload_image']= $this->Input['categories_info']['is_upload_image'];
        $data_info['is_upload_vedio']= $this->Input['categories_info']['is_upload_vedio'];
        $result_num=$this->Goods::updateGoodsCategoryById($goods_category->id,$data_info);
        if($result_num==0)
        {
            DB::rollBack();
            return apiReturn(-30017, '品类更新失败！');
        }

        //编辑品类属性
        $result_num=$this->Goods::deleteGoodsCategoryAttributeByGoodsCategoryId($goods_category->id);
        if($result_num==0)
        {
            DB::rollBack();
            return apiReturn(-30018, '品类属性删除失败！');
        }
        $data_attr=$this->Input['categories_attr'];
        foreach ($data_attr as $key => $val)
        {
            $val['category_id']=$goods_category->id;
            //可选值
            if(isset($val['avaliable_value']))
            {
                $val['avaliable_value']= json_encode($val['avaliable_value'],JSON_UNESCAPED_UNICODE);
            }

            if(!$this->Goods::createGoodsCategoryAttribute($val)){
                DB::rollBack();
                return apiReturn(-30019, '属性编辑失败！');
            }
        }

        //编辑品类交易时间
        if(isset($this->Input['categories_time']))
        {
            $data_time=$this->Input['categories_time'];
            //交易星期
            if(isset($data_time['trading_day']))
            {
                $data_time['trading_day']= implode(',',$data_time['trading_day']);
            }
            //排除日期
            if(isset($data_time['exclude_time']))
            {
                $data_time['exclude_time']= implode(',',$data_time['exclude_time']);
            }
            //交易时间段
            if(isset($data_time['time_slot']))
            {
                $data_time['time_slot']= json_encode($data_time['time_slot']);
            }

            //查询交易时间是否存在，存在则编辑，不存在则添加
            if($goods_category->GoodsCategoryTrade)
            {
                if($this->Goods::updateGoodsCategoryTradeByCategoryId($goods_category->id,$data_time)==0)
                {
                    DB::rollBack();
                    return apiReturn(-30020, '交易时间更新失败！');
                }
            }else{
                $data_time['category_id']= $goods_category->id;
                if(!$this->Goods::createGoodsCategoryTrade($data_time)){
                    DB::rollBack();
                    return apiReturn(-30021, '交易属性创建失败！');
                }
            }

        }
        DB::commit();
        return apiReturn(0, '操作成功！');
    }

    /**
     * 品类启用禁用
     * @return \Illuminate\Http\JsonResponse
     */
    public function editGoodsCategoryStatus()
    {
        $validator = $this->Validator::make($this->Input, [
            'category_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '不合法',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $goods_category=$this->Goods::getCategoryById($this->Input['category_id']);
        if(is_null($goods_category))
        {
            return apiReturn(-30022,'品类不存在！');
        }
        $data['status']= ($goods_category->status==GoodsCategory::STATUS['enable']) ? Goods::STATUS['disable'] : Goods::STATUS['enable'];
        if($this->Goods::updateGoodsCategoryById($this->Input['category_id'],$data)){
            return apiReturn(0, '更新成功');
        }else{
            return apiReturn(-30023, '更新失败');
        }
    }

    /**
     * 品类删除
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteGoodsCategory()
    {
        $validator = $this->Validator::make($this->Input, [
            'category_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '不合法',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $goods_category=$this->Goods::getCategoryById($this->Input['category_id']);
        if(is_null($goods_category))
        {
            return apiReturn(-30024,'品类不存在！');
        }

        if(count($goods_category->Goods)!=0)
        {
            return apiReturn(-30025,'该品类下存在商品，不能删除！');
        }

        DB::beginTransaction();
        //删除品类属性
        if($this->Goods::deleteGoodsCategoryAttributeByGoodsCategoryId($this->Input['category_id'])==0)
        {
            DB::rollBack();
            return apiReturn(-30026,'品类属性删除失败！');
        }

        //删除交易时间
        if($goods_category->GoodsCategoryTrade)
        {
            if($this->Goods::deleteGoodsCategoryTradeByCategoryId($this->Input['category_id'])==0)
            {
                DB::rollBack();
                return apiReturn(-30027,'品类交易时间删除失败！');
            }
        }

        //删除品类
        if($this->Goods::deleteGoodsCategoryById($this->Input['category_id'])==0){
            DB::rollBack();
            return apiReturn(-30028, '删除品类失败！');
        }else{
            DB::commit();
            return apiReturn(0, '删除成功！');
        }
    }


    /**
     * 上传商品默认图片
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadGoodsDefaultImage()
    {
        //验证文件
        if(!isset($this->Request->file()['default_image']))
        {
            $data['errors']['default_image'] = '默认图片为必填项！';
            return apiReturn(-105, '表单验证失败', $data);
        }
        $default_image=$this->Request->file()['default_image'];
        $error=[];
        //判断文件是否出错
        if($default_image->getError()!=0)
        {
            $error['default_image'][]='上传失败，请检查系统配置！';
        }
        //判断的文件是否超出2M
        if( $default_image->getClientSize()>2097152)
        {
            $error['default_image'][]='超出文件最大限制2M';
        }
        if($error){
            $data['errors']=$error;
            return apiReturn(-105, '表单验证失败', $data);
        }

        //上传文件
        if($default_image->isValid()){
            $ext = $default_image->getClientOriginalExtension();     // 扩展名
            $realPath = $default_image->getRealPath();   //临时文件的绝对路径
            $filename = date('Y-m-d-H-i-s') . '-' . uniqid() . '.' . $ext;// 生成的文件名
            $result = Storage::disk('goods_category_images')->put(date('Y-m-d').'/'.$filename, file_get_contents($realPath));
            if($result){
                return $filename;
            }else{
                return apiReturn(-30011, '文件保存失败，请检查服务器配置！');
            }
        }
    }

}
