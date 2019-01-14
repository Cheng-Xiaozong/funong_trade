<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/9/12
 * Time: 11:12
 */
namespace App\Services;

use App\AreaInfo;

class AreaInfoService
{

    /**
     * 地区初始化，获取所有的省份
     * @return mixed
     */
    public static function initData()
    {
        return AreaInfo::where('pid','=',0)->get()->toArray();
    }


    /**
     * 获取子类
     * @param int $id
     * @return mixed
     */
    public static function getElementByPid($id=0)
    {
        return AreaInfo::where('pid','=',$id)->get()->toArray();
    }


    /**
     * 获取所有城市
     * @return array
     */
    public static function getAreaInfo()
    {
        return AreaInfo::all()->toArray();
    }

    /**
     * 获取
     * @param int $id
     * @return mixed
     */
    public static function getAreaInfoByPid($id)
    {
        return AreaInfo::where('id','=',$id)->first();
    }

    /**
     * 根据县，获取所有父级
     * @param $county_id
     * @return array
     */
    public static function getParentsByCountyId($county_id)
    {
        $county=AreaInfo::find($county_id);
        $city=AreaInfo::find($county->pid);
        $province=AreaInfo::find($city->pid);
        $area['areas']['county']=AreaInfo::where('pid',$county->pid)->get()->toArray();
        $area['areas']['city']=AreaInfo::where('pid',$city->pid)->get()->toArray();
        $area['areas']['province']=AreaInfo::where('pid',$province->pid)->get()->toArray();
        $area['selected_id']['county']=$county->id;
        $area['selected_id']['city']=$county->pid;
        $area['selected_id']['province']=$city->pid;
        return $area;
    }


    /**
     * 获取完整地址
     * @param $data
     * @return null|string
     */
    public static function getFullAddress($data)
    {

        $full_address = null;

        if(isset($data['province'])){
            $full_address .= AreaInfoService::getAreaInfoByPid($data['province'])->name;
        }

        if(isset($data['city'])){
            $full_address .= AreaInfoService::getAreaInfoByPid($data['city'])->name;
        }

        if(isset($data['county'])){
            $full_address .= AreaInfoService::getAreaInfoByPid($data['county'])->name;
        }

        return $full_address;
    }





}