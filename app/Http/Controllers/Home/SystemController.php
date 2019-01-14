<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\BaseController;
use App\Services\SystemService;
use App\Services\AppVersionService;
use App\Services\AppService;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\AppBanner;

class SystemController extends BaseController
{

    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Msg;
    protected $Validator;
    protected $Area;
    protected $system;
    protected $AppVersion;
    protected $AppService;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        SystemService $system,
        Validator $validator,
        AppVersionService $appVersion,
        AppService $appService
    ){
        parent::__construct($request, $log, $redis);
        $this->Validator = $validator;
        $this->system = $system;
        $this->AppVersion = $appVersion;
        $this->AppService = $appService;
    }


    /**
     * 获取最新版本
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAppVersion()
    {

        $appVersion = $this->AppVersion::getLatestAppVersion();

        if ($appVersion) {
            $appVersion->download_url = getImgUrl($appVersion->file_name,'app_version_files','');
            unset($appVersion->file_name);

            $data['appVersion'] = $appVersion;
            return apiReturn(0, '获取成功', $data);
        } else {
            return apiReturn(-1, '获取失败');
        }
    }


    /**
     * 日志
     * @return \Illuminate\Http\JsonResponse
     */
    public function addLogs()
    {
        
        $validator = $this->Validator::make($this->Input, [
            'admin_id' => 'required',
            'admin_name' => 'required',
            'type' => 'required',
            'log' => 'required',
        ], [
            'required' => '为必填项',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        //校验数据
        $result=$this->system::addLogs($this->Input);
        if(!empty($result)){
            return apiReturn(0,'记录成功',null);
        }else{
            return apiReturn(-60001,'无权限或者操作失败');
        }
    }


    /**
     * 首页轮播
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexBanner()
    {

        $data['banner_list'] = array();
        $banners = $this->AppService::getBannerByType(AppBanner::TYPE['INDEX']);

        foreach ($banners as $k=>$v){
            if($v->link_id){
                $v->img_path = getImgUrl($v->img_path,'goods_imgs','');
            }else{
                $v->img_path = getImgUrl($v->img_path,'banner_imgs','');
            }
        }

        $data['banner_list'] = $banners;
        $data['tell_phone'] = '50495203';
        return apiReturn(0,'记录成功',$data);
    }
}
