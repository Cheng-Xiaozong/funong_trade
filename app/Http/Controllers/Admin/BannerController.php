<?php

namespace App\Http\Controllers\Admin;

use App\Services\OfferService;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\Home\OfferController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\AppBanner;
use App\Services\AppService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use App\GoodsOfferPattern;
use App\GoodsCategory;

class BannerController extends BaseController
{
    protected $Log;
    protected $Redis;
    protected $Request;
    protected $AppService;
    protected $Offer;
    protected $Validator;
    protected $OfferController;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AppService $appService,
        Validator $validator,
        OfferController $offerController,
        OfferService $offer
    )
    {
        parent::__construct($request, $log, $redis);
        $this->Log = $log;
        $this->Redis = $redis;
        $this->Request = $request;
        $this->AppService = $appService;
        $this->Validator = $validator;
        $this->Offer = $offer;
        $this->OfferController = $offerController;
    }


    /**
     * 获取模板
     * @return \Illuminate\Http\JsonResponse
     */
    public function bannerTemple()
    {

        $data['type'] = AppBanner::TYPE_MEAN;
        $data['action_type'] = AppBanner::ACTION_TYPE;
        return apiArrayReturn(0,'请求成功',$data);
    }


    /**
     * 通过卖家搜索报价
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchGoodsOffer()
    {

        $validator = $this->Validator::make($this->Input, [
            'seller_id' => 'required',
            'page_size' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'seller_id' => '卖家id',
            'page_size' => '显示条数',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data['offer_lists'] = array();
        $offer_lists = $this->Offer::getGoodsOfferListsBySellerId($this->Input['seller_id'],$this->Input['page_size']);

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
            $data['offer_lists'][$offer_list_key]['goods_params'] = $this->OfferController->abuttedParam($goods->goods_attrs);

            //报价参数
            $data['offer_lists'][$offer_list_key]['offer_params'] = $this->OfferController->abuttedParam($offer_list_val->offer_info);

            //拼装参数
            $data['offer_lists'][$offer_list_key]['id'] = $offer_list_val->id;

            //豆粕 商品名显示
            $goods_attr_list = $this->OfferController->paramList($goods->goods_attrs);
            $offer_attr_list = $this->OfferController->paramList($offer_list_val->offer_info);

            //豆粕
            $data['offer_lists'][$offer_list_key]['goods_name'] = $goods->name;
            if($goods_category->name == GoodsCategory::NAME['soybean_meal']){
                $data['offer_lists'][$offer_list_key]['goods_name'] = $this->OfferController->isSoybeanMeal($goods_attr_list);
            }

            //基差
            if($offer_pattern->name == GoodsOfferPattern::OFFER_PATTERN['basis_price']){
                $data['offer_lists'][$offer_list_key]['price'] = $this->OfferController->isBasisPrice($offer_attr_list).'+'.$offer_list_val->price;
            }else{
                $data['offer_lists'][$offer_list_key]['price'] = '¥'.$offer_list_val->price;
            }

            $data['offer_lists'][$offer_list_key]['describe'] = $offer_list_val->describe;
            $data['offer_lists'][$offer_list_key]['offer_pattern_id'] = $offer_list_val->offer_pattern_id;
            $data['offer_lists'][$offer_list_key]['category_name'] = $goods_category->name;
            $data['offer_lists'][$offer_list_key]['review_status'] = $offer_list_val->review_status;
            $data['offer_lists'][$offer_list_key]['stock'] = $offer_list_val->stock;
            $data['offer_lists'][$offer_list_key]['order_unit'] = $offer_list_val->order_unit;
            $data['offer_lists'][$offer_list_key]['offer_name'] = $offer_pattern->name;
            $data['offer_lists'][$offer_list_key]['delivery_address'] = $offer_list_val->address_details;
            $data['offer_lists'][$offer_list_key]['offer_starttime'] = $offer_list_val->offer_starttime;
            $data['offer_lists'][$offer_list_key]['offer_endtime'] = $offer_list_val->offer_endtime;
            $data['offer_lists'][$offer_list_key]['delivery_starttime'] = $offer_list_val->delivery_starttime;
            $data['offer_lists'][$offer_list_key]['delivery_endtime'] = $offer_list_val->delivery_endtime;
        }

        $data['paginate'] = pageing($offer_lists);
        return apiReturn(0,'请求成功',$data);
    }


    /**
     * 添加广告
     * @return \Illuminate\Http\JsonResponse
     */
    public function addBanner()
   {

       $content = $this->Request->all();
       //调用验证表单提交数据的方法
       if($this->ValidateCheck($content)){
           return $this->ValidateCheck($content);
       }

       $data['describe'] = $content['describe'];
       $data['sort'] = $content['sort'];
       $data['type'] = $content['type'];
       $data['action_type'] = $content['action_type'];

       if(isset($content['link'])){
           $data['link'] = $content['link'];
       }

       if(isset($this->Request->file()['img_path'])){
           if(!empty(isset($this->Request->file()['img_path']))){
               $data['img_path'] = $this->upBannerFace($this->Request->file()['img_path']);
           }
       }

       if(isset($content['link_id'])){
           $data['link_id'] = $content['link_id'];
           $offer = $this->Offer::getGoodsOfferById($content['link_id']);

           if(is_null($offer)){
               return apiReturn(-60036,'报价不存在');
           }

           $data['img_path'] = explode(',',$offer->good->faces)[0];
       }

       if(isset($content['content'])){
           $data['content'] = $content['content'];
       }

       if ($this->AppService->addBanner($data)) {
           return apiReturn(0,'请求成功');
       } else {
           return apiReturn(-60030,'添加失败');
       }

   }

    /**
     * 上传封面
     * @param $file
     * @return bool|string
     */
    public function upBannerFace($file)
    {

        $realPath = $file->getRealPath();
        $suffix = $file->getClientOriginalExtension();
        $toDir = date('Y-m-d');
        Storage::disk('banner_imgs')->makeDirectory($toDir);
        $prefix = date('Y-m-d-H-i-s') . '-' . uniqid();
        $fileName = $prefix . '.' . $suffix;
        Storage::disk('banner_imgs')->put($toDir . '/' . $fileName, file_get_contents($realPath));

        return $fileName;
    }


    /**
     * 广告列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function bannerList()
    {

        $list = $this->AppService::bannerList($this->Input['page_size']);
        foreach ($list as $k=>$v){
            if($v->link_id){
                $v->img_path = getImgUrl($v->img_path,'goods_imgs','');
            }else{
                $v->img_path = getImgUrl($v->img_path,'banner_imgs','');
            }
        }
        return apiReturn(0,'请求成功',$list);
    }


    /**
     * 编辑banner状态
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function editBannerStatus()
    {

        $validator = $this->Validator::make($this->Input, [
            'id' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'id' => '广告id',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        if ($this->AppService->editBannerStatus($this->Input['id'])) {
            return apiReturn(0,'修改成功');
        } else {
            return apiReturn(-60031,'修改失败');
        }
    }


    /**
     * 删除广告
     * @return \Illuminate\Http\JsonResponse
     */
    public function delBanner()
    {

        $validator = $this->Validator::make($this->Input, [
            'id' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'id' => '广告id',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $banner = $this->AppService::getBannerById($this->Input['id']);

        if(is_null($banner)){
            return apiReturn(-60032,'广告不存在');
        }

        if ($this->AppService::delBanner($this->Input['id'])) {

            if(empty($banner->link_id)){
                delFile($banner->img_path,'banner_imgs');
            }
            return apiReturn(0,'操作成功');
        } else {
            return apiReturn(-60033,'广告删除失败');
        }

    }


    /**
     * 更新广告信息
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function updateBanner()
    {

        $content = $this->Request->all();
        //调用验证表单提交数据的方法
        if($this->ValidateCheck($content)){
            return $this->ValidateCheck($content);
        }

        $banner = $this->AppService::getBannerById($content['id']);

        if(is_null($banner)){
            return apiReturn(-60032,'广告不存在');
        }

        $data['describe'] = $content['describe'];
        $data['sort'] = $content['sort'];
        $data['type'] = $content['type'];
        $data['action_type'] = $content['action_type'];

        if(isset($content['link'])){
            $data['link'] = $content['link'];
        }

        if(isset($this->Request->file()['img_path'])){
            if(!empty(isset($this->Request->file()['img_path']))){
                $data['img_path'] = $this->upBannerFace($this->Request->file()['img_path']);
            }
        }

        if(isset($content['link_id'])){
            $data['link_id'] = $content['link_id'];
            $offer = $this->Offer::getGoodsOfferById($content['link_id']);

            if(is_null($offer)){
                return apiReturn(-60036,'报价不存在');
            }

            $data['img_path'] = explode(',',$offer->good->faces)[0];
        }

        if(isset($content['content'])){
            $data['content'] = $content['content'];
        }

        if ($this->AppService->updateBanner($content['id'],$data)) {

            if(empty($banner->link_id)){
                delFile($banner->img_path,'banner_imgs');
            }
            return apiReturn(0,'请求成功');
        } else {
            delFile($data['img_path'],'banner_imgs');
            return apiReturn(-60034,'广告编辑失败');
        }

    }


    /**
     * 广告详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function bannerDetail()
    {

        $validator = $this->Validator::make($this->Input, [
            'id' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'id' => '广告id',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data['detail'] = $this->AppService::getBannerById($this->Input['id']);

        if(is_null($data['detail'])){
            return apiReturn(-60032,'广告不存在');
        }

        if($data['detail']['link_id']){
            $data['detail']['img_path'] = getImgUrl($data['detail']['img_path'],'goods_imgs','');
        }else{
            $data['detail']['img_path'] = getImgUrl($data['detail']['img_path'],'banner_imgs','');
        }

        return apiReturn(0,'请求成功',$data);
    }


    /**
     * 添加广告时验证表单提交的数据
     * @param $request
     */
    public function ValidateCheck($content)
    {

        $validator = $this->Validator::make($content, [
            "describe" => 'required|between:2,15',
            'link' => 'between:0,200',
            'sort' => 'required|numeric|between:1,100',
            'type' => 'required|integer',
            'action_type' => 'required|integer',
        ], [
            'required' => ':attribute 为必填项',
            'integer' => ':attribute 必须为数字',
            'between' => ':attribute 必须为指定字符串长度',
            'image' => ':attribute 必须为图片',
        ], [
            'describe' => '广告描述',
            'link' => '跳转链接',
            'sort' => '广告排序',
            'type' => '显示区域',
            'action_type' => '跳转类型',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        return false;
    }
}
