<?php

namespace App\Http\Controllers\Home;

use App\GoodsCategory;
use App\Services\AccountService;
use App\Services\AreaInfoService;
use App\Services\AddressService;
use App\Services\GoodsService;
use App\Services\OfferService;
use App\Http\Controllers\Admin\CommonController;
use App\Http\Controllers\Home\OfferController;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Mockery\Exception;
use Illuminate\Support\Facades\DB;
use function PHPSTORM_META\type;
use Intervention\Image\Facades\Image;

use App\GoodsCategoryAttribute;
use App\AccountBusiness;
use App\AccountSeller;
use App\Goods;
use App\GoodsOffer;
use App\Account;


class GoodsController extends BaseController
{
    
    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Account;
    protected $Validator;
    protected $Area;
    protected $Address;
    protected $Goods;
    protected $Offer;
    protected $Common;
    protected $OfferController;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AccountService $Account,
        Validator $validator,
        AreaInfoService $area,
        AddressService $address,
        GoodsService $goods,
        OfferService $offer,
        CommonController $common,
        OfferController $offerController
    )
    {
        parent::__construct($request, $log, $redis);
        $this->Account = $Account;
        $this->Validator = $validator;
        $this->Area = $area;
        $this->Address = $address;
        $this->Goods = $goods;
        $this->Offer = $offer;
        $this->Common = $common;
        $this->OfferController = $offerController;
    }

    
    /**
     * 商品品类列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGoodsCategories()
    {

        $data['category_lists'] = array();
        $data['category_lists'] =$this->Goods::getGoodsCategories();
        return apiReturn(0, '请求成功 !',$data);
    }


    /**
     * 商品模板
     * @return \Illuminate\Http\JsonResponse
     */
    public function goodsTemplate()
    {

        //验证参数
        if(!isset($this->Input['category_id'])){
            $error['errors']['category_id'] = '为必传项!';
            return apiReturn(-104, '数据验证失败', $error);
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function addGoods()
    {

        $content = $this->Request->all();
        $content = json_decode($content['good_info'],true);

        //验证参数
        $validator = $this->Validator::make($content, [
            'category_id' => 'required',
            'delivery_address_id' => 'required',
            'goods_attrs' => 'required',
            'name' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'category_id' => '品类id',
            'delivery_address_id' => '提货地址id',
            'goods_attrs' => '商品属性',
            'name' => '商品名',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //是否为商家
        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-30000, '您不是卖家，无法发布商品！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30006, '账户异常，无法发布商品！');
            }
            $data['account_employee_id'] = $employee->id;
            $seller = $account->AccountSeller;
            $business = $account->AccountInfo;
        }else{
            $seller = $user->AccountSeller;
            $business = $user->AccountInfo;
        }

        //是否通过审核
        if($business->review_status != AccountBusiness::REVIEW_STATUS['passed']){
            return apiReturn(-30004, '您还未通过审核，无法发布商品！');
        }

        //获取属性参数
        $param = array();
        foreach ($content['goods_attrs'] as $k=>$v){
            $param[$v['english_name']] = $v['english_name'];
        }

        //获取商品属性
        $templates = $this->Goods::getAttrsByCategoryId($content['category_id']);
        foreach($templates as $template_key => $template_val){
            if($template_val->is_necessary == GoodsCategoryAttribute::IS_NECESSARY['yes']){
                if(!isset($param[$template_val->english_name])){
                    $error['errors'][$template_val->english_name] = '为必传项!';
                    return apiReturn(-105, '表单验证失败', $error);
                }
            }
        }

        //上传图片
        if(!empty($this->Request->file('faces'))){
            $data['faces'] = $this->uploadFiles('faces','image');
        }

        //上传视频
        if(!empty($this->Request->file('vedios'))){
            $data['vedios'] = $this->uploadFiles('vedios','vedio');
        }

        //商品详情
        if(isset($content['details'])){
            $data['details'] = $content['details'];
        }

        //搜索关键字
        if(isset($content['search_keywords'])){
            $data['search_keywords'] = $content['search_keywords'];
        }

        //商品审核方式
        if($seller->release_type == AccountSeller::RELEASE_TYPE['system']){
            $data['review_status'] = Goods::REVIEW_STATUS['passed'];
        }

        //生成商品编号
        $data['code'] = generateNumber('SP');

        //拼装数据
        $data['goods_attrs'] = json_encode($content['goods_attrs'],JSON_UNESCAPED_UNICODE);
        $data['category_code'] = $this->Goods::getCategoryById($content['category_id'])->code;
        $data['category_id'] = $content['category_id'];
        $data['delivery_address_id'] = implode(',',$content['delivery_address_id']);
        $data['seller_id'] = $seller->id;
        $data['account_business_id'] = $business->id;

        $data['name'] = null;
        $category = $this->Goods::getCategoryById($content['category_id']);
        if($category->name == GoodsCategory::NAME['soybean_meal'] ){
            foreach ($content['goods_attrs'] as $k=>$v){
                $data['name'] .= $v['default_value'].',';
            }
            $data['name'] = rtrim($data['name'],',');
        }else{
            $data['name'] = $content['name'];
        }

        $result = $this->Goods::createGoods($data);
        if(!count($result)){
            return apiReturn(-9999, '操作异常');
        }

        //发送微信通知
        $goods_params = $this->OfferController->abuttedParam($data['goods_attrs']);
        $msg_data['data'] = array(
            'data' => array (
                'first'    => array('value' => "来自应用：%1\$s\n提交时间：".date('Y-m-d H:i:s',time())."\n卖家信息：".$business->name."，".$user->phone.""),
                'keyword1' => array('value' => $goods_params),
                'keyword2' => array('value' => "新增商品"),
                'remark'   => array('value' => "\n请及时进行审核！")
            )
        );
        $msg_data['action'] = "productChange";
        $this->Common->socketMessage($msg_data);
        return apiReturn(0, '请求成功！');
    }


    /**
     * 商品列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function goodsLists()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //是否为商家
        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-40019, '您不是卖家！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30006, '账户异常，无法发布商品！');
            }
            $data['account_employee_id'] = $employee->id;
            $seller = $account->AccountSeller;

        }else{
            $seller = $user->AccountSeller;
        }

        //判断是否获取所有商品列表
        if(!empty($employee)){
            $goods_lists = $this->Goods::getGoodsListsByAccountEmployeeId($employee->id);
        }else{
            $goods_lists = $this->Goods::getGoodsListsBySellerId($seller->id);
        }

        $data['goods_lists'] = array();
        foreach ($goods_lists as $goods_list_key => $goods_list_val){
            $data['goods_lists'][$goods_list_key]['id'] = $goods_list_val->id;
            if(!empty($goods_list_val->faces)){
                $data['goods_lists'][$goods_list_key]['faces'] = getImgUrl(explode(',',$goods_list_val->faces)[0],'goods_imgs','');
            }
            $data['goods_lists'][$goods_list_key]['name'] = $goods_list_val->name;
            $data['goods_lists'][$goods_list_key]['params'] = null;
            $data['goods_lists'][$goods_list_key]['delivery_addresses'] = null;
            $data['goods_lists'][$goods_list_key]['review_status'] = $goods_list_val->review_status;

            //报价信息
            $data['goods_lists'][$goods_list_key]['offer_list'] = array();
            $offer_lists = $goods_list_val->GoodsOffer;
            if(count($offer_lists)){
                foreach ($offer_lists as $k=>$v){
                    $data['goods_lists'][$goods_list_key]['offer_list'][$k]['price'] = $v->price;
                    $data['goods_lists'][$goods_list_key]['offer_list'][$k]['created_at'] = $v->price;
                }
            }

            //拼接商品自定义属性
            if(!empty($goods_list_val->goods_attrs) and (!is_null(json_decode($goods_list_val->goods_attrs)))){
                foreach (json_decode($goods_list_val->goods_attrs,true) as $k=>$v){
                    $data['goods_lists'][$goods_list_key]['params'] .= $v['name'].$v['default_value'].',';
                }
                $data['goods_lists'][$goods_list_key]['params'] = rtrim($data['goods_lists'][$goods_list_key]['params'],',');
            }

            //拼接提货地址
            if(!empty($goods_list_val->delivery_address_id)){
                $delivery_address_id = explode(',',$goods_list_val->delivery_address_id);
                foreach ($delivery_address_id as $k=>$v){
                    $delivery_address = $this->Address::getAddressById($v);
                    $data['goods_lists'][$goods_list_key]['delivery_addresses'] .= $delivery_address['address_details'].',';
                }
                $data['goods_lists'][$goods_list_key]['delivery_addresses'] = rtrim($data['goods_lists'][$goods_list_key]['delivery_addresses'],',');
            }
        }

        return apiReturn(0, '请求成功！',$data);
    }


    /**
     * 商品详情
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function goodsDetail()
    {

        //验证商品id是否存在
        if($this->verifyGoodsExist()){
            return $this->verifyGoodsExist();
        }

        $goods = $this->Goods::getGoodsById($this->Input['goods_id']);

        if(is_null($goods)){
            return apiReturn(-30001, '商品不存在！');
        }

        //商品参数
        $data['goods_details'] = array();
        $data['goods_details'] = $goods;
        $data['goods_details']['params'] = null;
        $data['goods_details']['delivery_addresses'] = null;
        $data['goods_patterns'] = array();

        if(!empty($goods->goods_attrs) and (!is_null(json_decode($goods->goods_attrs)))) {
            $data['goods_patterns'] = json_decode($goods->goods_attrs,true);
            foreach (json_decode($goods->goods_attrs,true) as $k=>$v){
                $data['goods_details']['params'] .= $v['name'].$v['default_value'].',';
            }
        }

        //商品提货地址
        $delivery_address_id = explode(',',$goods->delivery_address_id);
        foreach ($delivery_address_id as $k=>$v){
            $delivery_address = $this->Address::getAddressById($v);
            $data['goods_details']['delivery_addresses'] .= $delivery_address['address_details'].',';
        }

        $data['goods_details']['delivery_address_id'] = $goods->delivery_address_id;
        $data['goods_details']['params'] = rtrim($data['goods_details']['params'],',');
        $data['goods_details']['delivery_addresses'] = rtrim($data['goods_details']['delivery_addresses'],',');

        //商品图片
        if($goods->faces){
            $data['goods_details']['faces'] = getImgUrl(explode(',',$goods->faces),'goods_imgs','');
        }else{
            $data['goods_details']['faces']  = null;
        }

        //商品视频
        if(!empty($goods->vedios)){
            $data['goods_details']['vedios'] = getFileUrl(explode(',',$goods->vedios),'goods_vedios');
        }else{
            $data['goods_details']['vedios'] = null;
        }

        //审核日志
        if($goods->review_details){
            $data['goods_details']['review_details'] = json_decode($goods->review_details,true);
        }else{
            $data['goods_details']['review_details'] = null;
        }

        //商品所有参数
        $data['offer_patterns'] = array();
        $category = $goods->GoodsCategory;
        $data['offer_patterns'] =$this->Offer::getOfferPatternByOfferId($category->offer_type);

        //提货地址信息
        $data['address_lists'] = array();
        $data['address_lists'] = $this->Address::getAddressByIds(explode(',',$goods->delivery_address_id));

        //去除无效返回
        unset($data['goods_details']['goods_attrs']);
        unset($data['goods_details']['review_log']);
        return apiReturn(0, '请求成功！',$data);
    }


    /**
     * 编辑商品
     * @return \Illuminate\Http\JsonResponse
     */
    public function editGoods()
    {

        $content = $this->Request->all();

        if(!isset($content['good_info'])){
            $error['errors'] = 'good_info必传';
            return apiReturn(-104, '数据验证失败', $error);
        }

        $content = json_decode($content['good_info'],true);

        //验证参数
        $validator = $this->Validator::make($content, [
            'delivery_address_id' => 'required',
            'goods_attrs' => 'required',
            'name' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'delivery_address_id' => '提货地址id',
            'goods_attrs' => '商品属性',
            'name' => '商品名',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //是否为商家
        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-40019, '您不是卖家！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常，无法修改商品！');
            }
            $seller = $account->AccountSeller;
        }else{
            $seller = $user->AccountSeller;
        }

        //商品是否存在
        $goods = $this->Goods::getGoodsById($content['goods_id']);

        if(is_null($goods)){
            return apiReturn(-30001, '商品不存在！');
        }

        //是否为本人商品
        if($seller->id != $goods->seller_id){
            return apiReturn(-30008, '操作异常！');
        }

        //获取属性参数
        $param = array();
        foreach ($content['goods_attrs'] as $k=>$v){
            $param[$v['english_name']] = $v['english_name'];
        }

        //获取商品属性
        $templates = $this->Goods::getAttrsByCategoryId($goods->category_id);
        foreach($templates as $template_key => $template_val){
            if($template_val->is_necessary == GoodsCategoryAttribute::IS_NECESSARY['yes']){

                if(!isset($param[$template_val->english_name])){
                    $error['errors'][$template_val->english_name] = '为必传项!';
                    return apiReturn(-105, '表单验证失败', $error);
                }
            }
        }

        //上传图片
        if(!empty($this->Request->file('faces'))){
            $data['faces'] = $this->uploadFiles('faces','image');
        }

        //上传视频
        if(!empty($this->Request->file('vedios'))){
            $data['vedios'] = $this->uploadFiles('vedios','vedio');
        }

        //商品详情
        if(isset($content['details'])){
            $data['details'] = $content['details'];
        }

        //搜索关键字
        if(isset($content['search_keywords'])){
            $data['search_keywords'] = $content['search_keywords'];
        }

        //商品审核方式
        if($seller->release_type == AccountSeller::RELEASE_TYPE['system']){
            $data['review_status'] = Goods::REVIEW_STATUS['passed'];
        }else{
            $data['review_status'] = Goods::REVIEW_STATUS['waiting'];
        }

        //拼装数据
        $data['name'] = null;
        $category = $this->Goods::getCategoryById($goods->category_id);
        if($category->name == GoodsCategory::NAME['soybean_meal'] ){
            foreach ($content['goods_attrs'] as $k=>$v){
                $data['name'] .= $v['default_value'].',';
            }
            $data['name'] = rtrim($data['name'],',');
        }else{
            $data['name'] = $content['name'];
        }
        $data['goods_attrs'] = json_encode($content['goods_attrs'],JSON_UNESCAPED_UNICODE);
        $data['delivery_address_id'] = implode(',',$content['delivery_address_id']);

        $result = $this->Goods::updateGoodsById($content['goods_id'],$data);
        if(!count($result)){
            delFile($data['faces'],'goods_imgs');
            delFile($data['vedios'],'goods_vedios');
            return apiReturn(-30009, '商品编辑失败！');
        }

        if(isset($data['faces'])){
            delFile($goods->faces,'goods_imgs');
        }

        if(isset($data['vedios'])){
            delFile($goods->vedios,'goods_vedios');
        }

        return apiReturn(0, '请求成功！');
    }


    /**
     * 删除商品
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function deleteGoods()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //是否为商家
        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-40019, '您不是卖家！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常，无法修改商品！');
            }
            $seller = $account->AccountSeller;
        }else{
            $seller = $user->AccountSeller;
        }

        //验证商品id是否存在
        if($this->verifyGoodsExist()){
            return $this->verifyGoodsExist();
        }

        $goods = $this->Goods::getGoodsById($this->Input['goods_id']);

        if(is_null($goods)){
            return apiReturn(-30001, '商品不存在！');
        }

        //是否为本人商品
        if($seller->id != $goods->seller_id){
            return apiReturn(-30008, '操作异常！');
        }

        //商品删除条件
        $goods_offers = $goods->GoodsOffer;
        if(count($goods_offers)){
            return apiReturn(-30005, '商品存在报价，无法删除！');
        }


        if($goods->seller_id != $seller->id){
            return apiReturn(-30003, '商品无法删除！');
        }

        //删除图片和视频
        $faces = $goods->faces;
        $vedios = $goods->vedios;

        if($this->Goods::deleteGoodsById($this->Input['goods_id'])){
            delFile($faces,'goods_imgs');
            delFile($vedios,'goods_vedios');
            return apiReturn(0, '请求成功！');
        }

        return apiReturn(-30002, '商品删除失败！');
    }

    /**
     * 验证商品数据
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyGoodsData()
    {

        $validator = $this->Validator::make($this->Input, [
            'name' => 'required | min:2 | max:10',
        ], [
            'required' => ':attribute为必填项',
            'min' => ':attribute最短2位',
            'max' => ':attribute最长10位',
        ], [
            'name:attribute' => '商品全称',
        ]);
        
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
    }

    /**
     * 上传文件
     * @param $param
     * @param $type
     * @return mixed
     */
    public function uploadFiles($param, $type)
    {

        //上传文件
        $file_name = null;
        if(is_object($this->Request->file($param))){
            if($type == 'image'){
                $file_name = $this->uploadImages($this->Request->file($param));
            }

            if($type == 'vedio'){
                $file_name = $this->uploadVedios($this->Request->file($param));
            }

        }else{
            foreach($this->Request->file($param) as $k=>$v){
                if($type == 'image'){
                    $file_name .= $this->uploadImages($v).',';
                }

                if($type == 'vedio'){
                    $file_name .= $this->uploadVedios($v).',';
                }

            }
            $file_name = rtrim($file_name,',');
        }
        return $file_name;
    }

    /**
     * 执行封面处理
     * @param $file
     * @return null|string
     */
    public function uploadImages($image, $main = false)
    {

        $realPath = $image->getRealPath();

        $ext = $image->getClientOriginalExtension();

        $toDir = date('Y-m-d');
        Storage::disk('goods_imgs')->makeDirectory($toDir);

        $file = date('Y-m-d-H-i-s') . '-' . uniqid();
        $filename = $file . '.' . $ext;

        Storage::disk('goods_imgs')->put($toDir . '/' . $file . '.' . $ext, file_get_contents($realPath));

        if ($main) {
            $img = Image::make($realPath);
            Storage::disk('goods_imgs')->put($toDir . '/' . $file . '_60.' . $ext, $img->encode($ext));

            $img = Image::make($realPath)->resize(200, 200);
            Storage::disk('goods_imgs')->put($toDir . '/' . $file . '_200.' . $ext, $img->encode($ext));

            $img = Image::make($realPath)->resize(350, 350);
            Storage::disk('goods_imgs')->put($toDir . '/' . $file . '_350.' . $ext, $img->encode($ext));

        }

        return $filename;
    }


    /**
     * 上传视频
     * @param null $file
     * @return string
     */
    public function uploadVedios($file=null)
    {

        $realPath = $file->getRealPath();
        $suffix = $file->getClientOriginalExtension();

        $toDir = date('Y-m-d');

        Storage::disk('goods_vedios')->makeDirectory($toDir);

        $prefix = date('Y-m-d-H-i-s') . '-' . uniqid();
        $fileName = $prefix . '.' . $suffix;

        Storage::disk('goods_vedios')->put($toDir . '/' . $fileName, file_get_contents($realPath));

        return $fileName;
    }


    /**
     * 验证商品id是否存在
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function verifyGoodsExist()
    {

        //验证参数
        $validator = $this->Validator::make($this->Input, [
            'goods_id' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'goods_id' => '商品id',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        return false;
    }
}
