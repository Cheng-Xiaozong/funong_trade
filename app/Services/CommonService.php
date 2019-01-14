<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/8/24
 * Time: 11:12
 */
namespace App\Services;

use App\Goods;

class CommonService
{

    /**
     *  字段描述
     * @return mixed
     */
    public static function fieldDescribe()
    {
        //账户列表
        $data['accounts']=AccountService::accountFieldDescribe();
        //账户详情
        $data['account']=AccountService::accountDetailsFieldDescribe();
        //公司列表
        $data['businesses']=AccountService::businessFieldDescribe();
        //公司详情
        $data['business']=AccountService::businessDetailsFieldDescribe();
        //商品列表
        $data['all_goods']=GoodsService::goodsListFieldDescribe();
        //报价列表
        $data['all_offer']=OfferService::offerListFieldDescribe();
        //报价模式列表
        $data['all_offer_patterns']=OfferService::offerPatternsListFieldDescribe();
        //报价模式属性列表
        $data['all_offer_attributes']=OfferService::offerAttributesListFieldDescribe();
        //商品详情
        $data['goods']=GoodsService::goodsFieldDescribe();
        //商品模板
        $data['template_lists']=GoodsService::goodsTemplateFieldDescribe();
        //是否上传视频
        $data['upload_vedio']=GoodsService::goodsIsUploadVedioDescribe();
        //是否上传图片
        $data['upload_image']=GoodsService::goodsIsUploadImageDescribe();
        //商品分类列表
        $data['goods_categorys']=GoodsService::goodsCategorysDescribe();
        //商品分类详情
        $data['goods_category']=GoodsService::goodsCategoryDescribe();
        //商品地址列表
        $data['all_goods_address_attributes']=AddressService::addressAttributesListFieldDescribe();
        //订单列表
        $data['all_order_lists']=OrderService::AllOrderListDescribe();
        //公共接口买家卖家列表
        $data['business_list']=AccountService::businessListFieldDescribe();
        //app版本状态
        $data['appversion_status'] = AppVersionService::appVersionDescribe();
        //app是否强制更新
        $data['appversion_is_update_anyway'] = AppVersionService::isUpdateAnyWay();
        //广告显示位置
        $data['appbanner_type'] = AppService::appBannerTypeDescribe();
        //广告跳转方式
        $data['appbanner_action_type'] = AppService::appBannerActionDescribe();
        return $data;
    }

    /**
     * 省市县选择
     * @param $area_id
     * @return mixed
     */
    public static function getSonAreaInfo($area_id)
    {
        return AreaInfoService::getElementByPid($area_id);
    }

    /**
     * 通用验证模型某个字段是否重复
     * @param $model
     * @param $attr_name
     * @param $attr_value
     * @param $id
     * @return mixed
     */
    public static function uniqueModelAttr($model,$attr_name,$attr_value,$id)
    {

        return $model->where($attr_name,$attr_value)->where('id','<>',$id)->first();
    }
}