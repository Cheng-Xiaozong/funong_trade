<?php
/**
 * Created by PhpStorm.
 * User: alen
 * Date: 2017/11/1
 * Time: 16:36
 */

namespace App\Http\Controllers\Admin;

use App\AppVersion;
use App\Http\Controllers\BaseController;
use App\Services\AppVersionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AppVersionController extends BaseController
{
    protected $AppVersion;
    protected $Validator;

    /**
     * AppVersionController constructor.
     * @param Request $request
     * @param Log $log
     * @param Redis $redis
     * @param AppVersionService $appVersion
     * @param Validator $validator
     */
    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AppVersionService $appVersion,
        Validator $validator
    )
    {
        parent::__construct($request, $log, $redis);
        $this->AppVersion = $appVersion;
        $this->Validator = $validator;
    }


    /**
     * 获取版本列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function list()
    {

        if (request('keyword') !== null) {
            $data['appVersions'] = $this->AppVersion::getListByKeyword(request('keyword'),$this->Input['page_size']);
        } else {
            $data['appVersions'] = $this->AppVersion::getList($this->Input['page_size']);
        }

        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 新增版本
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create()
    {

        $content = $this->Request->all();
        $version_info = json_decode($content['version_info'],true);

        if($this->validateData($version_info)){
            return $this->validateData($version_info);
        }

        $data = $version_info;
        $file = $this->Request->file('file');

        if ($file && $file->isValid()) {
            $data['file_name'] = $this->uploadFile($file);
        }

        if ($this->AppVersion::createAppVersion($data)) {
            return apiReturn(0,'请求成功',$data);
        } else {
            return apiReturn(-60020,'版本提交失败');
        }

    }

    /**
     * 上传文件
     * @param $file
     * @return string
     */
    public function uploadFile($file)
    {
        $realPath = $file->getRealPath();
        $suffix = $file->getClientOriginalExtension();

        $toDir = date('Y-m-d');

        Storage::disk('app_version_files')->makeDirectory($toDir);

        $prefix = date('Y-m-d-H-i-s') . '-' . uniqid();
        $fileName = $prefix . '.' . $suffix;

        Storage::disk('app_version_files')->put($toDir . '/' . $fileName, file_get_contents($realPath));

        return $fileName;
    }


    /**
     * 编辑状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function editStatus()
    {

        $validator = $this->Validator::make($this->Input, [
            'id' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'id' => 'id',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $appVersion = $this->AppVersion::findAppVersionById($this->Input['id']);

        if ($appVersion->status == AppVersion::STATUS['ENABLE']) {
            $appVersion->status = AppVersion::STATUS['DISABLE'];
        } else {
            $appVersion->status = AppVersion::STATUS['ENABLE'];
        }

        if ($appVersion->save()) {
            return apiReturn(0,'操作成功');
        } else {
            return apiReturn(-60021,'状态编辑失败');
        }
    }


    /**
     * 删除版本
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete()
    {

        $validator = $this->Validator::make($this->Input, [
            'id' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'id' => 'id',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $appVersion = $this->AppVersion::findAppVersionById($this->Input['id']);

        if ($appVersion) {
            $this->deleteFile($appVersion->file_name);

            if (!$appVersion->delete()) {
                return apiReturn(-60021,'删除失败');
            }
        }

        return apiReturn(0,'删除成功');
    }


    /**
     * 删除文件
     * @param $fileName
     */
    public function deleteFile($fileName)
    {
        $targetDir = substr($fileName, 0, 10);
        $targetFile = $targetDir . '/' .$fileName;
        Storage::disk('app_version_files')->delete($targetFile);
    }


    /**
     * 验证表单数据
     * @param $request
     * @param string $file
     */
    public function validateData($version_info)
    {

        $validator = $this->Validator::make($version_info, [
            'update_title' => 'required',
            'version_code' => 'required',
            'version_name' => 'required|between:1,20',
            'app_name' => 'required|between:1,8',
            'is_update_anyway' => 'integer',
            'update_info' => 'required',
        ], [
            'required' => ':attribute为必填项',
            'integer' => ':attribute必须为数字',
            'between' => ':attribute必须为指定字符串长度',
        ], [
            'update_title' => '标题',
            'version_code' => '版本号',
            'version_name' => '版本名称',
            'app_name' => 'APP名称',
            'is_update_anyway' => '强制更新',
            'update_info' => '本次更新的内容'
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        return false;
    }


    /**
     * 获取版本详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail()
    {

        $validator = $this->Validator::make($this->Input, [
            'id' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ], [
            'id' => 'id',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $appVersion = $this->AppVersion::findAppVersionById($this->Input['id']);

        if ($appVersion) {
            return apiReturn(0,'获取成功',$appVersion);
        } else {
            return apiReturn(-1,'无相关信息');
        }
    }


    /**
     * 更新版本信息
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function update()
    {

        $content = $this->Request->all();

        $version_info = json_decode($content['version_info'],true);

        if($this->validateData($version_info)){
            return $this->validateData($version_info);
        }

        $data = $version_info;
        $file = $this->Request->file('file');
        if ($file && $file->isValid()) {
            $appVersion = $this->AppVersion::findAppVersionById($version_info['id']);
            $this->deleteFile($appVersion->file_name);
            $data['file_name'] = $this->uploadFile($file);
        }

        if ($this->AppVersion::updateAppVersion($version_info['id'], $data)) {
            return apiReturn(0,'更新成功');
        } else {
            return apiReturn(-60022,'更新失败');
        }

    }

}
