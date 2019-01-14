<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/11/7
 * Time: 15:36
 */
namespace App\Services;
use App\App;
use App\AppBanner;

class AppService
{
    /**
     * 获取列表
     * @return mixed
     */
    public static function lists($page_size)
    {
        return App::paginate($page_size);
    }

    /**
     * 编辑
     * @param $id
     * @param $data
     * @return int
     */
    public static function edit($id, $data)
    {
        return App::where('id',$id)->update($data);
    }

    /**
     * 删除
     * @param $id
     * @return int
     */
    public static function delete($id)
    {
        return App::destroy($id);
    }


    /**
     * 添加
     * @param $data
     * @return object
     */
    public static function add($data)
    {
        return App::create($data);
    }

    /**
     * 编辑状态
     * @param $id
     * @return bool
     */
    public static function status($id)
    {
        $app=self::getAppById($id);
        if($app->status==App::STATUS_ENABLE)
        {
            $app->status=App::STATUS_DISABLE;
        }else{
            $app->status=App::STATUS_ENABLE;
        }
        return $app->save();
    }

    /**
     * 根据Id获取
     * @param $id
     * @return mixed
     */
    public static function getAppById($id)
    {
        return App::find($id);
    }


    /**
     * 添加广告
     * @param $data
     * @return object
     */
    public static function addBanner($data)
    {
        return AppBanner::create($data);
    }

    /**
     * 广告列表
     * @return mixed
     */
    public static function bannerList($page_size)
    {
        return AppBanner::paginate($page_size);
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function editBannerStatus($id)
    {
        $banner = AppBanner::find($id);
        if($banner->status == AppBanner::STATUS_DISABLE){
            $banner->status = AppBanner::STATUS_ENABLE;
        }else{
            $banner->status = AppBanner::STATUS_DISABLE;
        }

        return $banner->save();
    }

    /**
     * @param $banner_id
     * @return mixed
     */
    public static function getBannerById($banner_id)
    {
        return AppBanner::where('id', $banner_id)->get()->first();
    }

    /**
     * 删除广告
     * @param $banner_id
     * @return int
     */
    public static function delBanner($banner_id)
    {
        return AppBanner::destroy($banner_id);
    }

    /**
     * 广告详情
     * @param $id
     * @return mixed
     */
    public static function getBannerDetail($id)
    {
        return AppBanner::find($id);
    }

    /**
     * 编辑广告
     * @param $id
     * @param $data
     * @return mixed
     */
    public static function updateBanner($id, $data)
    {
        return AppBanner::where('id','=',$id)->update($data);
    }


    /**
     * @param $type
     * @return mixed
     */
    public static function getBannerByType($type)
    {
        return AppBanner::where('type',$type)->where('status',AppBanner::STATUS_ENABLE)->get();
    }


    public static function appBannerTypeDescribe()
    {
        $data['type'] = AppBanner::TYPE_MEAN;
        return $data;
    }


    public static function appBannerActionDescribe()
    {
        $data['action_type'] = AppBanner::ACTION_TYPE;
        return $data;
    }
}