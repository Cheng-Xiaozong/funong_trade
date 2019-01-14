<?php

namespace App\Http\Controllers\Home;

use App\Services\AccountService;
use App\Services\SendMsgService;
use App\Services\AreaInfoService;
use App\Services\OrderService;;
use App\Services\AddressLocateService;
use App\Services\SendWeChatMsgService;
use App\Http\Controllers\Admin\CommonController;

use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Account;
use App\AccountBusiness;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Mockery\Exception;


class AccountController extends BaseController
{

    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Account;
    protected $Msg;
    protected $Validator;
    protected $Area;
    protected $Locate;
    protected $SendWeChatMsg;
    protected $Common;
    protected $Order;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AccountService $Account,
        SendMsgService $msg,
        Validator $validator,
        AddressLocateService $Locate,
        AreaInfoService $area,
        OrderService $order,
        SendWeChatMsgService $sendWeChatMsg,
        CommonController $common
    )
    {
        parent::__construct($request, $log, $redis);
        $this->Account = $Account;
        $this->Msg = $msg;
        $this->Validator = $validator;
        $this->Area = $area;
        $this->Locate = $Locate;
        $this->Order = $order;
        $this->SendWeChatMsg = $sendWeChatMsg;
        $this->Common = $common;
    }


    /**
     * 注册省市县选择
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSonAreaInfo()
    {

        if (is_null($this->Input['area_id']))
            return apiReturn(-104, 'area_id为必填项');
        $data['area'] = $this->Area::getElementByPid($this->Input['area_id']);
        return apiReturn(0, '获取成功', $data);
    }


    /**
     * 注册
     * @return \Illuminate\Http\JsonResponse
     */
    public function register()
    {

        if(isset($this->Input['register_status'])){
            //验证参数
            $verify_data = array('phone','register_status');
            if($this->verifyData($verify_data)){
                return $this->verifyData($verify_data);
            }
        }

        //验证手机
        if($this->verifyMobile()){
            return $this->verifyMobile();
        }

        //是否完成注册流程
        $user = $this->Account::getUserByPhone($this->Input['phone']);
        if(!is_null($user) and ($user->register_status >= Account::REGISTER_STATUS['inforamtion'])){

            //是否有绑定微信操作
            if(isset($this->Input['wechat_info'])){
                $wechat_info = json_decode($this->Input['wechat_info'],true);
                if(!($this->updateWechatInfo($wechat_info,$user))){
                    return apiReturn(-10015, '微信已被绑定或已失效!');
                }
            }
            $data['token'] = $this->Account::createToken($user);
            return apiReturn(-10004, '已注册,请直接登录！',$data);
        }

        if(is_null($user) and ($this->Input['register_status'] != Account::REGISTER_STATUS['unregistered '])) {
            return apiReturn(-10010, '注册状态不正确!');
        }

        //绑定微信
        $data = array();
        $data_info = array();
        $identity_info = array();

        //首次进入注册页
        if($this->Input['register_status'] == Account::REGISTER_STATUS['unregistered ']){

            //验证验证码
            if($this->verifyCode('REGISTER')){
                return $this->verifyCode('REGISTER');
            }

            //验证手机号验证码
            $user = $this->Account::getUserByPhone($this->Input['phone']);
            if(!is_null($user)){
                $user_info = $user->AccountInfo;

                $return_data['identity_type'] = null;
                if(!is_null($user_info)){
                    $return_data['identity_type'] = (string)$user_info->type;
                }

                //是否有绑定微信操作
                if(isset($this->Input['wechat_info'])){

                    $wechat_info = json_decode($this->Input['wechat_info'],true);
                    if(!($this->updateWechatInfo($wechat_info,$user))){
                        return apiReturn(-10015, '微信已被绑定或已失效!');
                    }
                }

                $return_data['register_status'] = $user->register_status;
                $return_data['message'] = Account::REGISTER_STATUS_DESCRIBE[$user->register_status];
                return apiReturn(0, '请求成功！',$return_data);
            }

            $validator = $this->Validator::make($this->Input, [
                'phone' => 'required | unique:accounts,phone',
            ], [
                'required' => ':attribute为必填项',
                'unique' => ':attribute已存在',
            ], [
                'phone' => '账号',
            ]);

            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-10017, '手机号已存在');
            }

            DB::beginTransaction();

            //添加account数据
            $data['phone'] = $this->Input['phone'];
            $data['role'] = 0;
            $data['register_status'] = Account::REGISTER_STATUS['mobile'];
            $result = $this->Account::createAccount($data);

            ////添加account_iunfo数据
            $info_data['account_id'] =$result->id;
            $info_data['contact_phone'] =$this->Input['phone'];
            $info_result = $this->Account::createAccountInfo($info_data);

            if ($result && $info_result) {
                DB::commit();
            } else {
                DB::rollBack();
                return apiReturn(-9999, '操作异常');
            }

            if($info_result){

                //是否有绑定微信操作
                if(isset($this->Input['wechat_info'])){
                    $wechat_info = json_decode($this->Input['wechat_info'],true);
                    if(!($this->updateWechatInfo($wechat_info,$result))){
                        return apiReturn(-10015, '微信已被绑定或已失效!');
                    }
                }

                $return_data['identity_type'] = (string)$info_result->type;
                $return_data['register_status'] = $result->register_status;
                $return_data['message'] = Account::REGISTER_STATUS_DESCRIBE[$result->register_status];
                return apiReturn(0, '请求成功！',$return_data);
            }
        }

        //account
        $data['register_status'] = $this->Input['register_status'] + 1;

        switch ($this->Input['register_status']) {
            //选择身份
            case Account::REGISTER_STATUS['mobile']:
                //验证参数
                if($this->verifyData('identity')){
                    return $this->verifyData('identity');
                }
                $data['account_type'] = $this->Input['identity'];
                break;
            //择身份类型
            case Account::REGISTER_STATUS['identity']:
                //验证参数
                if($this->verifyData('identity_type')){
                    return $this->verifyData('identity_type');
                }
                $data_info['type'] = $this->Input['identity_type'];
                break;
            //填写密码
            case Account::REGISTER_STATUS['identity_type']:
                if($this->verifyAccount()){
                    return $this->verifyAccount();
                }
                $data['account_number'] = $this->Input['account_number'];
                $data['password'] = bcrypt($this->Input['password']);;
                break;
            //填写资料
            case Account::REGISTER_STATUS['password']:
                if($this->verifyAccountInfo()){
                    return $this->verifyAccountInfo();
                }
                $data['status'] = Account::STATUS['enable'];
                break;
        }

        if($this->Input['register_status'] == Account::REGISTER_STATUS['identity']){
            $info_result = $this->Account::updateAccountInfoByAccountId($user->id,$data_info);
        }

        if($this->Input['register_status'] == Account::REGISTER_STATUS['password']){

            if(isset($this->Input['contact_name'])){
                $data_info['contact_name'] = $this->Input['contact_name'];
            }

            $data['nickname'] = $this->Input['name'];
            $data_info['province'] = $this->Input['province'];
            $data_info['city'] = $this->Input['city'];
            $data_info['county'] = $this->Input['county'];
            $data_info['address'] = $this->Input['address'];
            $data_info['name'] = $this->Input['name'];

            //获取完整地址
            $full_address = $this->Area->getFullAddress($this->Input).$data_info['address'];
            $full_address = str_replace(' ','',$full_address);
            //检测redis是否开启
            try{
                $this->Redis::ping();
            }catch(\Exception $exception)
            {
                return apiReturn(-500, '服务器内部错误，redis未开启！');
            }
            $locate = $this->Locate->addressLocate($full_address);
            $data_info['address_details'] = $full_address;

            //是否获取到经纬度
            if(!$locate){
                $locate = $this->Locate->addressLocate($this->Area->getFullAddress($this->Input));
            }

            $data_info['lng'] = $locate['lng'];
            $data_info['lat'] = $locate['lat'];
            $info_result = $this->Account::updateAccountInfoByAccountId($user->id,$data_info);
        }

        $result = $this->Account::updateAccountByPhone($this->Input['phone'],$data);

        if($result){
            $user_info = $user->AccountInfo;

            //是否有绑定微信操作
            if(isset($this->Input['wechat_info'])){
                $wechat_info = json_decode($this->Input['wechat_info'],true);
                if(!($this->updateWechatInfo($wechat_info,$user))){
                    return apiReturn(-10015, '微信已被绑定或已失效!');
                }
            }

            $return_data['identity_type'] = (string)$user_info->type;
            if(isset($info_result)){
                $return_data['identity_type'] = (string)$user_info->type;
            }

            $return_data['register_status'] = $data['register_status'];
            $return_data['message'] = Account::REGISTER_STATUS_DESCRIBE[$data['register_status']];

            $user = $this->Account::getUserByPhone($this->Input['phone']);
            //完成注册
            $data = array();
            if($user->register_status == Account::REGISTER_STATUS['inforamtion']){

                //填充账户关联表
                $identity_info['account_id'] = $user->id;
                $identity_info['account_business_id'] = $user_info->id;
                if($user->account_type ==Account::ACCOUNT_TYPE['buyer']){

                    if(!is_null($this->Account::getBuyerByAccountId($user->id))){
                        $this->Account::updateBuyerByAccountId($user->id,$identity_info);
                    }else{
                        $this->Account::createByuer($identity_info);
                    }
                }

                if($user->account_type ==Account::ACCOUNT_TYPE['seller']){

                    if(!is_null($this->Account::getSellerByAccountId($user->id))){
                        $this->Account::updateSellerByAccountId($user->id,$identity_info);
                    }else{
                        $this->Account::createSeller($identity_info);
                    }
                }

                //发送微信通知
                $msg_data['data'] = array(
                    'data' => array (
                        'first'    => array('value' => "来自应用：商贸通APP\n注册类型：".Account::ACCOUNT_TYPE_DESCRIBE[$user->account_type]."\n主体身份：".AccountBusiness::TYPE__DESCRIBE[$user_info->type]),
                        'keyword1' => array('value' => $user_info->name.','.$user->phone),
                        'keyword2' => array('value' => date('Y-m-d H:i:s',time())),
                        'remark'   => array('value' => "\n请及时进行审核！")
                    )
                );
                $msg_data['action'] = "userRegister";
                $this->Common->socketMessage($msg_data);
                
                $data['token'] = $this->Account::createToken($user);
                return apiReturn(0, '已注册,请直接登录！',$data);
            }

            return apiReturn(0, '请求成功！',$return_data);
        }
    }


    /**
     * 登录
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        if(!isset($this->Input['login_type']) || !in_array($this->Input['login_type'],array(1,2,3))){
            return apiReturn(-10007, '登录类型不正确 !');
        }

        //手机号/账号
        if($this->Input['login_type'] != 3){

            $validator = $this->Validator::make($this->Input, [
                'account_number' => 'required | min:2 | max:12',
            ], [
                'required' => ':attribute为必填项',
                'min' => ':attribute最短2位',
                'max' => ':attribute最长10位',
            ], [
                'account_number' => '账号',
            ]);

            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-104, '数据验证失败', $error);
            }

            //验证是否注册
            $user = $this->Account::loginAccount(htmlspecialchars($this->Input['account_number']));

            if(is_null($user) || ($user->register_status < Account::REGISTER_STATUS['inforamtion'])){
                return apiReturn(-10003, '请先注册！');
            }

            //验证密码
            if($this->Input['login_type'] == 1){
                if(!isset($this->Input['password'])){
                    $error['errors'] = '密码不能为空';
                    return apiReturn(-104, '数据验证失败', $error);
                }

                if(!(\Hash::check($this->Input['password'],$user->password))){
                    return apiReturn(-10009, '密码不正确！');
                }
            }

            //验证验证码
            if($this->Input['login_type'] == 2){
                //验证验证码
                if($this->verifyCode('LOGIN',$this->Input['account_number'])){
                    return $this->verifyCode('LOGIN',$this->Input['account_number']);
                }
            }
        }

        //微信
        if($this->Input['login_type'] == 3){
            if(!isset($this->Input['wechat_info']) or (is_null(json_decode($this->Input['wechat_info'])))){
                return apiReturn(-10016, '微信登录失败');
            }
            $wechat_info = json_decode($this->Input['wechat_info'],true);
            //是否有绑定微信操作
            if(isset($wechat_info)){
                if(!isset($wechat_info['openid'])){
                    return apiReturn(-10016, '微信登录失败');
                }
            }

            $user = $this->Account::getUserByOpenidAndAppId($wechat_info['openid'],$wechat_info['appid']);
            if(is_null($user)){
                $user = $this->Account::getUserByUnionid($wechat_info['unionid']);
                if(is_null($user)){
                    return apiReturn(-10008, '请先绑定账号！');
                }
            }

            if(($user->register_status < Account::REGISTER_STATUS['inforamtion'])){
                $user_info = $user->AccountInfo;
                $return_data['identity_type'] = (string)$user_info->type;
                $return_data['register_status'] = $user->register_status;
                $return_data['message'] = Account::REGISTER_STATUS_DESCRIBE[$user->register_status];
                return apiReturn(-10003, '请先注册！',$return_data);
            }
        }

        //是否冻结
        if($user->status == Account::STATUS['disable']){
            return apiReturn(-10012, '账号已被禁用！');
        }

        //是否有绑定微信操作
        if(isset($this->Input['wechat_info'])){
            $wechat_info = json_decode($this->Input['wechat_info'],true);
            //是否有绑定微信操作
            $wx_data['openid'] = $wechat_info['openid'];
            $wx_data['unionid'] = $wechat_info['unionid'];
            $wx_data['android_appid'] = $wechat_info['appid'];
            $wx_data['wechat_info'] = json_encode($wechat_info);
            $account_result = $this->Account::updateAccountByPhone($user->phone,$wx_data);

            if(!$account_result){
                return apiReturn(-10016, '微信登录失败');
            }
        }

//        $old_token = $this->Redis::get($user->phone.'token');
//
//        if($old_token){
//            $this->Account::addBlacklist($old_token);
//            $this->Redis::del($user->phone.'token');
//        }

        $data['token'] = $this->Account::createToken($user);
//        $this->Redis::set($user->phone.'token',$data['token']);
        return apiReturn(0, '请求成功！',$data);
    }


    /**
     * 微信公众号注册
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function weChatOfficialRegister()
    {

        $validator = $this->Validator::make($this->Input, [
            'phone' => 'required | min:11 | max:11',
            'public_openid' => 'required',
            'public_appid' => 'required',
            'unionid' => 'required',
        ], [
            'required' => ':attribute为必填项',
            'min' => ':attribute最短11位',
            'max' => ':attribute最长11位',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        if($this->verifyCode('REGISTER',$this->Input['phone'])){
            return $this->verifyCode('REGISTER',$this->Input['phone']);
        }

        if(isset($this->Input['name'])){

            $validator = $this->Validator::make($this->Input, [
                'identity' => 'required',
                'identity_type' => 'required',
                'province' => 'required',
                'city' => 'required',
                'county' => 'required',
                'address' => 'required',
            ], [
                'required' => ':attribute为必填项',
                'min' => ':attribute最短11位',
                'max' => ':attribute最长11位',
            ]);

            if ($validator->fails()) {
                $error['errors'] = $validator->errors();
                return apiReturn(-104, '数据验证失败', $error);
            }

            $old_user = $this->Account::getAccountsByPhone($this->Input['phone']);

            if(count($old_user)){
                $update_data['public_openid'] = $this->Input['public_openid'];
                $update_data['public_appid'] = $this->Input['public_appid'];
                $update_data['unionid'] = $this->Input['unionid'];
                $this->Account::updateAccountById($old_user[0]['id'],$update_data);
                $data['token'] = $this->Account::createToken($old_user[0]);
                return apiReturn(0, '请求成功！',$data);
            }

            DB::beginTransaction();

            //添加account数据
            $data['public_openid'] = $this->Input['public_openid'];
            $data['public_appid'] = $this->Input['public_appid'];
            $data['unionid'] = $this->Input['unionid'];
            $data['phone'] = $this->Input['phone'];
            $data['account_type'] = $this->Input['identity'];
            $data['role'] = 0;
            $data['status'] = 1;
            $data['source'] = Account::SOURCE['wechat'];
            $data['register_status'] = Account::REGISTER_STATUS['inforamtion'];
            $data['nickname'] = $this->Input['name'];
            $result = $this->Account::createAccount($data);

            ////添加account_iunfo数据
            $info_data['account_id'] =$result->id;
            $info_data['type'] =$this->Input['identity_type'];
            $info_data['contact_phone'] =$this->Input['phone'];
            $info_data['province'] = $this->Input['province'];
            $info_data['city'] = $this->Input['city'];
            $info_data['county'] = $this->Input['county'];
            $info_data['address'] = $this->Input['address'];
            $info_data['name'] = $this->Input['name'];
            //获取完整地址
            $full_address = $this->Input['province'].$info_data['city'].$info_data['address'];
            $full_address = str_replace(' ','',$full_address);
            //检测redis是否开启
            try{
                $this->Redis::ping();
            }catch(\Exception $exception)
            {
                return apiReturn(-500, '服务器内部错误，redis未开启！');
            }
            $locate = $this->Locate->addressLocate($full_address);
            $info_data['address_details'] = $full_address;

            //是否获取到经纬度
            if(!$locate){
                $re_full_address = $this->Input['province'].$info_data['city'];
                $re_full_address = str_replace(' ','',$re_full_address);
                $locate = $this->Locate->addressLocate($re_full_address);
            }

            $info_data['lng'] = $locate['lng'];
            $info_data['lat'] = $locate['lat'];
            $info_result = $this->Account::createAccountInfo($info_data);

            $identity_info['account_id'] = $result->id;
            $identity_info['account_business_id'] = $info_result->id;
            //新建卖家
            if($result->account_type ==Account::ACCOUNT_TYPE['seller']){
                if(!is_null($this->Account::getSellerByAccountId($result->id))){
                    $this->Account::updateSellerByAccountId($result->id,$identity_info);
                }else{
                    $this->Account::createSeller($identity_info);
                }
            }

            //新建买家
            if($result->account_type ==Account::ACCOUNT_TYPE['buyer']){
                if(!is_null($this->Account::getBuyerByAccountId($result->id))){
                    $identity_result = $this->Account::updateBuyerByAccountId($result->id,$identity_info);
                }else{
                    $identity_result = $this->Account::createByuer($identity_info);
                }
            }

            if ($result && $info_result && $identity_result) {
                DB::commit();
            } else {
                DB::rollBack();
                return apiReturn(-9999, '操作异常');
            }

            //发送微信通知
            $msg_data['data'] = array(
                'data' => array (
                    'first'    => array('value' => "来自应用：微信公众号\n注册类型：".Account::ACCOUNT_TYPE_DESCRIBE[$result->account_type]."\n主体身份：".AccountBusiness::TYPE__DESCRIBE[$info_result->type]),
                    'keyword1' => array('value' => $info_result->name.','.$result->phone),
                    'keyword2' => array('value' => date('Y-m-d H:i:s',time())),
                    'remark'   => array('value' => "\n请及时进行审核！")
                )
            );

            $msg_data['action'] = "userRegister";
            $this->Common->socketMessage($msg_data);
            $data['token'] = $this->Account::createToken($result);
            $data['user'] = $result;
            return apiReturn(0, '请求成功！',$data);
        }
    }


    /**
     * 微信公众号登录
     * @return \Illuminate\Http\JsonResponse
     */
    public function weChatOfficialLogin()
    {

        $validator = $this->Validator::make($this->Input, [
            'public_openid' => 'required',
            'public_appid' => 'required',
            'unionid' => 'required',
        ], [
            'required' => ':attribute为必填项',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $user = $this->Account::getUserByPublicOpenidandAppid($this->Input['public_openid'],$this->Input['public_appid']);;

        if(is_null($user)){
            $common_user = $this->Account::getUserByUnionid($this->Input['unionid']);

            if(is_null($common_user)){
                return apiReturn(-10008, '请先绑定账号！');
            }

            $update_data['public_openid'] = $this->Input['public_openid'];
            $update_data['public_appid'] = $this->Input['public_appid'];
            $update_data['unionid'] = $this->Input['unionid'];
            $result = $this->Account::updateUserByUnionid($this->Input['unionid'],$update_data);
            if($result){
                $data['token'] = $this->Account::createToken($common_user);
                $data['user'] = $common_user;
                return apiReturn(0, '请求成功！',$data);
            }

            return apiReturn(-10023, '登录失败，请稍后重试！');
        }

        //是否冻结
        if($user->status == Account::STATUS['disable']){
            return apiReturn(-10012, '账号已被禁用！');
        }

        $data['token'] = $this->Account::createToken($user);
        $data['user'] = $user;
        return apiReturn(0, '请求成功！',$data);
    }


    /**
     * 刷新用户token
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {

        if (is_null($this->Request->header('token'))) return apiReturn(-200, 'Token does not exist');
        try {
            $token = JWTAuth::refresh($this->Request->header('token'));
        } catch (\Exception $exception) {
            return apiReturn(-204, 'Token validation fails');
        }
        $data['token'] = $token;
        return apiReturn(0, '刷新成功', $data);
    }


    /**
     * 发送验证码
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendVerifyCode()
    {

        $verify_type = $this->Input['verify_type'];
        $phone = $this->Input['phone'];

        //验证类型是否合法
        if(!in_array($verify_type,array('REGISTER','FORGET_PASSWORD','LOGIN','MODIFY_ACCOUNT'))){
            return apiReturn(-10000, '验证码验证类型不正确');
        }

        if (!isset($this->Input['phone']) || !isMobilePhone($this->Input['phone'])) {
            return apiReturn(-10001, '手机号不能为空或者非法');
        }

        $key_array = array(
            'REGISTER'  => 'sendRegisterMsg' . $phone . '_' . config('ext.rediskey'),
            'LOGIN'     => 'sendLoginMsg_' . $phone . '_' . config('ext.rediskey'),
            'FORGET_PASSWORD'     => 'sendFORGET_PASSWORDMsg_' . $phone . '_' . config('ext.rediskey'),
            'MODIFY_ACCOUNT'     => 'sendMODIFY_ACCOUNTMsg_' . $phone . '_' . config('ext.rediskey')
        );

        $key = $key_array[$verify_type];

        try {
            $redis_val = $this->Redis::get($key);
        } catch (\Exception $exception) {
            return apiReturn(-500, '服务器内部错误');
        }

        //发送闫恒吗
        if ($redis_val) {
            $data['die_time'] = $this->Redis::ttl($key);
            return apiReturn(-10002, '一分钟内,请勿重复发送短信！', $data);
        } else {
            $code = rand(1000, 9999);
            $this->Redis::set($key, $code);
            $this->Redis::expire($key, 60);
            $result_json = $this->Msg::sendMsg('sendValidCodeSMS', $phone, $code);
            $result = json_decode($result_json, true);
            if ($result && $result['code'] == 0) {
                $this->Log::info('用户登陆验证码：' . $phone . '：' . $code . '：' . $result_json);
                return apiReturn(0, '请求成功！');
            } else {
                $this->Redis::expire($key, 0);
                return apiReturn(-10003, '验证码请求失败！');
            }
        }
    }


    /**
     * 解绑微信
     * @return \Illuminate\Http\JsonResponse
     */
    public function relieveWechat()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        if(empty($user->openid)){
            return apiReturn(-10021, '您还未绑定微信！');
        }

        $data['unionid'] = '';
        $data['wechat_info'] = '';
        $data['public_appid'] = '';
        $data['android_appid'] = '';

        $result = $this->Account::updateAccountById($user->id,$data);

        if($result){
            return apiReturn(0, '解绑成功！');
        }

        return apiReturn(-10022, '微信解绑失败！');
    }

    /**
     * 忘记密码
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function forgetPassword()
    {

        //验证新密码
        $validator = $this->Validator::make($this->Input, [
            'password' => 'required | min:6 | max:18',
        ], [
            'required' => ':attribute为必填项',
            'min' => ':attribute最短6位',
            'max' => ':attribute最长18位',
        ], [
            'password' => '密码',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        //验证手机
        if($this->verifyMobile()){
            return $this->verifyMobile();
        }

        //验证验证码
        if($this->verifyCode('FORGET_PASSWORD')){
            return $this->verifyCode('FORGET_PASSWORD');
        }

        $user = $this->Account::forgetPassword($this->Input['phone'],bcrypt($this->Input['password']));

        if($user == 1){
            return apiReturn(0, '请求成功!');
        }

        return apiReturn(-10011, '密码重置失败,请稍后再试 ！');
    }

    /**
     * 账户初始化信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function initInfo()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $data['account_employee_id'] = $employee->id;
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常,无法添加提货地址！');
            }
            $business = $account->AccountInfo;
        }else{
            $business = $user->AccountInfo;
        }

        if(count($seller = $user->AccountSeller)){
            $data['init_list']['seller_id'] = $seller = $user->AccountSeller->id;
        }else{
            $data['init_list']['buyer_id'] = $seller = $user->AccountBuyer->id;
        }

        $data['init_list']['limit_stock'] = 1000;
        $data['init_list']['user_id'] = $user->id;
        $data['init_list']['name'] = $business->name;
        $data['init_list']['legal_cn_id'] = $business->legal_cn_id;
        $data['init_list']['contact_name'] = $business->contact_name;
        $data['init_list']['nickname'] = $user->nickname;
        $data['init_list']['account_number'] = $user->account_number;
        $data['init_list']['phone'] = $user->phone;
        $data['init_list']['account_type'] = $user->account_type;
        $data['init_list']['status'] = $user->status;
        $data['init_list']['type'] = $business->type;
        $data['init_list']['review_status'] = $business->review_status;
        $data['init_list']['province'] = $business->province;
        $data['init_list']['city'] = $business->city;
        $data['init_list']['county'] = $business->county;
        $data['init_list']['address'] = $business->address;
        $data['init_list']['address_details'] = $business->address;
        $data['init_list']['business_id'] = $business->id;

        //微信信息
        if(!empty($user->wechat_info) and (!is_null(json_decode($user->wechat_info)))){
            $wechat_info = json_decode($user->wechat_info,true);
            $data['init_list']['wechat_name'] = $wechat_info['nickname'];
            $data['init_list']['wechat_img'] = $wechat_info['headimgurl'];
            $data['init_list']['is_band_wx'] = 1;
        }else{
            $data['init_list']['wechat_name'] = '';
            $data['init_list']['wechat_img'] = '';
            $data['init_list']['is_band_wx'] = 0;
        }

        //图片信息
        if(!empty($business->legal_id_positive)){
            $data['init_list']['legal_id_positive'] = getImgUrl(explode(',',$business->legal_id_positive),'account_imgs','');
        }

        if(!empty($business->legal_id_reverse)){
            $data['init_list']['legal_id_reverse'] = getImgUrl(explode(',',$business->legal_id_reverse),'account_imgs','');
        }

        if(!empty($business->business_license)){
            $data['init_list']['business_license'] = getImgUrl(explode(',',$business->business_license),'account_imgs','');
        }

        //图片审核日志
        if((!empty($business->review_detail)) and (!is_null(json_decode($business->review_detail)))){
            $data['init_list']['picture'] = json_decode($business->review_detail,true);
        }

        return apiReturn(0, '请求成功!',$data);
    }

    /**
     * 修改账户信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function modifyAccountInfo()
    {
        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        //修改账号验证码
        if(($this->Input['type'] == 1)){

            //验证验证码
            if($this->verifyCode('MODIFY_ACCOUNT')){
                return $this->verifyCode('MODIFY_ACCOUNT');
            }
        }

        //修改手机号验证码
        if(($this->Input['type'] == 2)){

            //验证验证码
            if($this->verifyCode('MODIFY_ACCOUNT',$this->Input['change_phone'])){
                return $this->verifyCode('MODIFY_ACCOUNT',$this->Input['change_phone']);
            }
        }

        //修改账号密码
        if($this->Input['type'] == 1 ){

            if($user->account_number != $this->Input['account_number']){
                $validator = $this->Validator::make($this->Input, [
                    'account_number' => ' min:2 | max:12 | unique:accounts,account_number',
                    'password' => ' min:6 | max:18',
                ], [
                    'required' => ':attribute为必填项',
                    'unique' => ':attribute已存在',
                ], [
                    'account_number' => '账号',
                    'password' => '密码',
                ]);

                if ($validator->fails()) {
                    $error['errors'] = $validator->errors()->getMessages();
                    return apiReturn(-10018, $validator->errors()->first());
                }

                $data['account_number'] = $this->Input['account_number'];
            }

            $data['password'] = bcrypt($this->Input['password']);
        }

        //修改手机号
        if($this->Input['type'] == 2 ){

            $validator = $this->Validator::make($this->Input, [
                'change_phone' => 'required | unique:accounts,phone',
            ], [
                'required' => ':attribute为必填项',
                'unique' => ':attribute已存在',
            ], [
                'change_phone' => '手机号',
            ]);

            if ($validator->fails()) {
                $error['errors'] = $validator->errors()->getMessages();
                return apiReturn(-10019, $validator->errors()->first());
            }

            $data['phone'] = $this->Input['change_phone'];
        }

        //修改姓名地址
        if($this->Input['type'] == 3 ){

            //验证信息
            if($this->verifyAccountInfo()){
                return $this->verifyAccountInfo();
            }

            if(isset($this->Input['contact_name'])){
                $data_info['contact_name'] = $this->Input['contact_name'];
            }

            if(isset($this->Input['nickname'])){
                $data['nickname'] = $this->Input['nickname'];
            }

            $data_info['province'] = $this->Input['province'];
            $data_info['city'] = $this->Input['city'];
            $data_info['county'] = $this->Input['county'];
            $data_info['address'] = $this->Input['address'];
            $data_info['name'] = $this->Input['name'];
            $account_info['nickname'] = $this->Input['name'];

            //获取完整地址
            $full_address = $this->Area->getFullAddress($this->Input).$data_info['address'];
            $full_address = str_replace(' ','',$full_address);
            //检测redis是否开启
            try{
                $this->Redis::ping();
            }catch(\Exception $exception)
            {
                return apiReturn(-500, '服务器内部错误，redis未开启！');
            }
            $locate = $this->Locate->addressLocate($full_address);
            $data_info['address_details'] = $full_address;

            //是否获取到经纬度
            if(!$locate){
                $locate = $this->Locate->addressLocate($this->Area->getFullAddress($this->Input));
            }

            $data_info['lng'] = $locate['lng'];
            $data_info['lat'] = $locate['lat'];
        }

        if(isset($data)){
            $result = $this->Account::updateAccountByPhone($user->phone,$data);
            $return_data = $data;
        }

        if(($this->Input['type'] == 3)){
            $this->Account::updateAccountById($user->id,$account_info);
            $result = $this->Account::updateAccountInfoByAccountId($user->id,$data_info);
            $return_data = $data_info;
        }

        if($result){
            return apiReturn(0, '请求成功!',$return_data);
        }

        return apiReturn(-10020, '账户信息修改失败！!');
    }


    /**
     * 修改身份图片
     * @return \Illuminate\Http\JsonResponse
     */
    public function modifyCertifyImgs()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);
        $account_business = $user->AccountInfo;

        if(empty($account_business)){
            return apiReturn(-10015, '您没有权限！');
        }

        if($account_business->review_status == AccountBusiness::REVIEW_STATUS['passed']){
            return apiReturn(-10013, '身份信息已审核通过！');
        }

        $data = array();
        //上传身份证
        if(!empty($this->Request->file('legal_id_positive'))){
            delFile($account_business->legal_id_positive,'account_imgs');
            $data['legal_id_positive'] = $this->uploadImages($this->Request->file('legal_id_positive'));
            $return_data['legal_id_positive'] = getImgUrl(explode(',',$data['legal_id_positive']),'account_imgs','');
        }

        if(!empty($this->Request->file('legal_id_reverse'))){
            delFile($account_business->legal_id_reverse,'account_imgs');
            $data['legal_id_reverse'] = $this->uploadImages($this->Request->file('legal_id_reverse'));
            $return_data['legal_id_reverse'] = getImgUrl(explode(',',$data['legal_id_reverse']),'account_imgs','');
        }

        //上传营业执照
        if(!empty($this->Request->file('business_license'))){
            delFile($account_business->business_license,'account_imgs');
            $data['business_license'] = $this->uploadImages($this->Request->file('business_license'));
            $return_data['business_license'] = getImgUrl(explode(',',$data['business_license']),'account_imgs','');
        }

        if(empty($data)){
            return apiReturn(-10014, '图片上传失败!');
        }

        $data['review_status'] = AccountBusiness::REVIEW_STATUS['wating'];
        if($this->Account::updateAccountInfoByAccountId($user->id,$data)){
            return apiReturn(0, '请求成功!',$return_data);
        }

        return apiReturn(-10014, '图片上传失败');
    }


    /**
     * 上传身份图片
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadCertifyImgs()
    {
        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);
        $account_business = $user->AccountInfo;

        if(empty($account_business)){
            return apiReturn(-10015, '您没有权限！！');
        }

        if($account_business->review_status == AccountBusiness::REVIEW_STATUS['passed']){
            return apiReturn(-10013, '身份信息已审核通过！');
        }

        $data = array();
        //上传身份证
        if(!empty($this->Request->file('legal_id_positive'))){
            $data['legal_id_positive'] = $this->uploadImages($this->Request->file('legal_id_positive'));
        }

        if(!empty($this->Request->file('legal_id_reverse'))){
            $data['legal_id_reverse'] = $this->uploadImages($this->Request->file('legal_id_reverse'));
        }

        //上传营业执照
        if(!empty($this->Request->file('business_license'))){
            $data['business_license'] = $this->uploadImages($this->Request->file('business_license'));
        }

        if(empty($data)){
            return apiReturn(-10014, '图片上传失败!');
        }

        $data['review_status'] = AccountBusiness::REVIEW_STATUS['wating'];
        if($this->Account::updateAccountInfoByAccountId($user->id,$data)){
            return apiReturn(0, '请求成功!');
        }

        return apiReturn(-9999, '操作异常');
    }

    /**
     * 添加部分
     * @return mixed
     */
    public function addBranch()
    {
        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);
        $business = $user->AccountInfo;
        return $business;
    }


    /**
     * 验证手机号
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function verifyMobile()
    {

        if (!isset($this->Input['phone']) || !isMobilePhone($this->Input['phone'])) {
            return apiReturn(-10001, '手机号不能为空或者非法');
        }

        return false;
    }

    /**
     * 验证验证码
     * @param $verify_type
     * @param null $phone
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function verifyCode($verify_type, $phone=null)
    {

        if(empty($phone)){
            $phone = $this->Input['phone'];
        }

        if (!isset($this->Input['verify_code'])) {
            return apiReturn(-10005, '请输入正确的验证码！');
        }

        //验证码类型
        $key_array = array(
            'REGISTER'  => 'sendRegisterMsg' . $phone . '_' . config('ext.rediskey'),
            'LOGIN'     => 'sendLoginMsg_' . $phone . '_' . config('ext.rediskey'),
            'FORGET_PASSWORD'     => 'sendFORGET_PASSWORDMsg_' . $phone . '_' . config('ext.rediskey'),
            'MODIFY_ACCOUNT'     => 'sendMODIFY_ACCOUNTMsg_' . $phone . '_' . config('ext.rediskey')
        );

        $key = $key_array[$verify_type];
        $redis_val = $this->Redis::get($key);

        if(empty($redis_val)){
            return apiReturn(-10006, '请先获取验证码！');
        }

        if($redis_val != $this->Input['verify_code']){
            return apiReturn(-10005, '请输入正确的验证码！');
        }

        return false;
    }


    /**
     * 验证注册数据
     * @param $param
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function verifyData($param)
    {

        if(is_array($param)){
            foreach ($param as $v){
                if(!isset($this->Input[$v])){
                    $error['errors'][$v] = '为必传项!';
                    return apiReturn(-104, '数据验证失败', $error);
                }
            }
        }

        if(is_string($param)){
            if(!isset($this->Input[$param])){
                $error['errors'][$param] = '为必传项!';
                return apiReturn(-104, '数据验证失败', $error);
            }
        }

        return false;
    }


    /**
     * 验证注册账号
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function verifyAccount()
    {

        $validator = $this->Validator::make($this->Input, [
            'account_number' => 'required | min:2 | max:12 | unique:accounts,account_number',
            'password' => 'required | min:6 | max:18',
        ], [
            'required' => ':attribute为必填项',
            'unique' => ':attribute已存在',
        ], [
            'account_number' => '账号',
            'password' => '密码',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors()->getMessages();
            return apiReturn(-104, $validator->errors()->first(), $error);
        }

        return false;
    }


    /**
     * 验证注册信息
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function verifyAccountInfo()
    {

        $validator = $this->Validator::make($this->Input, [
            'province' => 'required | integer',
            'city' => 'required | integer',
            'county' => 'required | integer',
            'address' => 'required | min:2',
            'name' => 'required | min:2',
        ], [
            'required' => ':attribute为必填项',
            'min' => ':attribute最短2位',
            'max' => ':attribute最长4位',
            'integer' => ':attribute整数',
        ], [
            'province' => '省份id',
            'city' => '城市id',
            'county' => '县id',
            'address' => '详细地址',
            'name' => '全程',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        return false;
    }


    /**
     * 上传图片
     * @param $image
     * @param bool $main
     * @return string
     */
    public function uploadImages($image, $main = false)
    {

        $realPath = $image->getRealPath();
        $ext = $image->getClientOriginalExtension();
        $toDir = date('Y-m-d');
        Storage::disk('account_imgs')->makeDirectory($toDir);
        $file = date('Y-m-d-H-i-s') . '-' . uniqid();
        $filename = $file . '.' . $ext;
        Storage::disk('account_imgs')->put($toDir . '/' . $file . '.' . $ext, file_get_contents($realPath));

        return $filename;
    }


    /**
     * 更新微信登录信息
     * @param $wechat_info
     * @param $user
     */
    public function updateWechatInfo($wechat_info, $user)
    {

        if(!isset($wechat_info['openid'])){
            return false;
        }

        //是否有绑定微信操作
        $wx_data['openid'] = $wechat_info['openid'];
        $wx_data['unionid'] = $wechat_info['unionid'];
        $wx_data['android_appid'] = $wechat_info['appid'];

        $account = $this->Account::getUserByOpenidAndAppId($wechat_info['openid'],$wechat_info['appid']);
        if(!is_null($account)){
            $user = $this->Account::getUserByUnionid($wechat_info['unionid']);
            if(is_null($user)){
                return false;
            }
        }

        $wx_data['wechat_info'] = json_encode($wechat_info);
        $account_result = $this->Account::updateAccountByPhone($user->phone,$wx_data);

        if ($account_result) {
            return true;
        }

        return false;
    }
}