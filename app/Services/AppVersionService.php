<?php
/**
 * Created by PhpStorm.
 * User: alen
 * Date: 2017/12/15
 * Time: 18:27
 */

namespace App\Services;


use App\AppVersion;

class AppVersionService
{
    /**
     * 获取版本列表
     * @return mixed
     */
    public static function getList($page_size)
    {
        return AppVersion::orderBy('created_at', 'desc')
            ->paginate($page_size);
    }


    /**
     * 通过关键字搜索版本
     * @param $keyword
     * @return mixed
     */
    public static function getListByKeyword($keyword,$page_size)
    {
        return AppVersion::orWhere('version_code', $keyword)
            ->orWhere('version_name', 'like', "%$keyword%")
            ->orderBy('created_at', 'desc')
            ->paginate($page_size);
    }


    /**
     * 新增版本
     * @param $data
     * @return object
     */
    public static function createAppVersion($data)
    {
        return AppVersion::create($data);
    }

    /**
     * 通过id查找版本
     * @param $id
     * @return mixed
     */
    public static function findAppVersionById($id)
    {
        return AppVersion::find($id);
    }

    /**
     * 删除版本
     * @param $id
     * @return int
     */
    public static function destroyAppVersion($id)
    {
        return AppVersion::destroy($id);
    }

    /**
     * 编辑版本信息
     * @param $id
     * @param $data
     * @return mixed
     */
    public static function updateAppVersion($id, $data)
    {
        return AppVersion::where('id', $id)->update($data);
    }


    /**
     * 获取版本信息
     * @return mixed
     */
    public static function getLatestAppVersion()
    {
        return AppVersion::orderBy('created_at', 'desc')
            ->first();
    }


    /**
     * 获得版本状态
     * @return mixed
     */
    public static function appVersionDescribe()
    {
        $data['status']=AppVersion::STATUS_TRANSLATION;
        return $data;
    }


    /**
     * 是否强制更新
     * @return mixed
     */
    public static function isUpdateAnyWay()
    {
        $data['is_update_anyway']=AppVersion::IS_UPDATE_ANYWAY_TRANSLATION;
        return $data;
    }
}