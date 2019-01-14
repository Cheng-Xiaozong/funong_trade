<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Services\AppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class AppController extends BaseController
{

    protected $App;
    protected $Validator;

    /**
     * 构造方法
     * ShippingController constructor.
     * @param Request $request
     * @param Log $log
     * @param Redis $redis
     * @param AppService $app
     */
    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AppService $app,
        Validator $validator
    ){
        parent::__construct($request,$log,$redis);
        $this->App=$app;
        $this->Validator = $validator;
    }


    /**
     * 列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function list()
    {
        $data['apps']=$this->App::lists($this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }


    public function getSecret()
    {

        $data['appid']='5'.time();
        $data['appsecret']= strtoupper(md5(sha1(time())));
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 添加应用
     * @return bool|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function add()
    {

        if($this->verifyFormData()){
            return $this->verifyFormData();
        }

        $data['name'] = $this->Input['name'];
        $data['appid'] = $this->Input['appid'];
        $data['appsecret'] = $this->Input['appsecret'];

        if(isset($this->Input['remark']) && !empty($this->Input['remark'])){
            $data['remark'] = $this->Input['remark'];
        }

        if($this->App::add($data))
        {
            return apiReturn(0,'操作成功');
        }else{
            return apiReturn(-60050,'添加失败');
        }


    }

    /**
     * 删除应用
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete()
    {
        try{
            $this->Redis::ping();
        }catch(\Exception $exception)
        {
            return apiReturn(-60051,'redis服务未开启');
        }
        $key=$this->App::getAppById($this->Input['id'])->appid.'_'.config('ext.rediskey');
        if($this->App::delete($this->Input['id']))
        {
            $this->Redis::del($key);
            return apiReturn(0,'操作成功');
        }else{
            return apiReturn(-60052,'删除失败');
        }
    }

    /**
     * 获取应用详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail()
    {
        $data['app']=$this->App::getAppById($this->Input['id']);
        return apiReturn(0,'操作成功',$data);
    }


    /**
     * 编辑应用
     * @param $id
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function edit()
    {

        if($this->verifyFormData()){
            return $this->verifyFormData();
        }

        $data['name'] = $this->Input['name'];
        $data['appid'] = $this->Input['appid'];
        $data['appsecret'] = $this->Input['appsecret'];

        if(isset($this->Input['remark']) && !empty($this->Input['remark'])){
            $data['remark'] = $this->Input['remark'];
        }

        if($this->App::edit($this->Input['id'],$data))
        {
            return apiReturn(0,'修改成功');
        }else{
            return apiReturn(-60053,'修改失败');
        }

    }


    /**
     * 表单数据验证
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function verifyFormData()
    {

        $validator = $this->Validator::make($this->Input, [
            "name" => 'required',
            "appid" =>'required',
            'appsecret' => 'required'
        ], [
            'required' => ':attribute 为必填项'
        ], [
            'name' => '应用名称',
            'appid' => '应用ID',
            'appsecret' => '应用密钥'
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        return false;
    }


    /**
     * 状态修改
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {

        try{
           $this->Redis::ping();
        }catch(\Exception $exception)
        {
            return apiReturn(-60051,'redis服务未开启');
        }

        if($this->App::status($this->Input['id']))
        {
            $app=$this->App::getAppById($this->Input['id']);
            if($app->status==0)
            {
                $this->delAppSecret($this->Input['id']);
            }else{
                $this->addAppSecret($this->Input['id']);
            }
            return apiReturn(0,'修改成功');
        }else{
            return apiReturn(-60053,'修改失败');
        }
    }

    /**
     * 将App密钥添加到内存
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function addAppSecret($id)
    {
        $app=$this->App::getAppById($id);
        $key=$app->appid.'_'.config('ext.rediskey');
        $value=$app->appsecret;
        $this->Redis::set($key,$value);
    }

    /**
     * 将App密钥从内存删除
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delAppSecret($id)
    {
        $app=$this->App::getAppById($id);
        $key=$app->appid.'_'.config('ext.rediskey');
        $this->Redis::del($key);
    }

}
