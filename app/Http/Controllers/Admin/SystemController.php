<?php

namespace App\Http\Controllers\Admin;
use App\Admin;
use App\Http\Controllers\BaseController;
use App\Menu;
use App\Services\AdminUserService;
use App\Services\SystemService;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SystemController extends BaseController
{
    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Msg;
    protected $Validator;
    protected $Area;
    protected $system;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        SystemService $system,
        Validator $validator
    ){
        parent::__construct($request, $log, $redis);
        $this->Validator = $validator;
        $this->system = $system;
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



}
