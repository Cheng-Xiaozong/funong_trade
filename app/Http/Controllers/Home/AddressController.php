<?php

namespace App\Http\Controllers\Home;

use App\Services\AccountService;
use App\Services\AreaInfoService;
use App\Services\AddressLocateService;
use App\Services\AddressService;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Auth;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Mockery\Exception;
use App\Account;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class AddressController extends BaseController
{
    
    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Account;
    protected $Validator;
    protected $Area;
    protected $Locate;
    protected $Address;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AccountService $Account,
        Validator $validator,
        AreaInfoService $area,
        AddressLocateService $Locate,
        AddressService $address
    )
    {
        parent::__construct($request, $log, $redis);
        $this->Account = $Account;
        $this->Validator = $validator;
        $this->Area = $area;
        $this->Locate = $Locate;
        $this->Address = $address;
    }


    /**
     * 省市县选择
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
     * 搜索地址
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchDeliveryAddress()
    {

        //验证参数
        if(!isset($this->Input['city_id'])){
            $error['errors']['city_id'] = '为必传项!';
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data['search_addresses'] = array();
        $data['search_addresses'] = $this->Address::getAddressByCityId($this->Input['city_id']);
        return apiReturn(0, '获取成功', $data);
    }


    /**
     * 添加提货地址
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function addDeliveryAddress()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-10021, '您不是卖家，无法添加提货地址！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $data['account_employee_id'] = $employee->id;
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常,无法添加提货地址！');
            }
            $seller = $account->AccountSeller;
            $business = $account->AccountInfo;
        }else{
            $seller = $user->AccountSeller;
            $business = $user->AccountInfo;
        }

        //验证参数
        if(!isset($this->Input['type'])){
            $error['errors']['type'] = '为必传项!';
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data = $this->Input;
        if($this->Input['type'] == 1){
            //验证地址
            if($this->verifyAddress()){
                return $this->verifyAddress();
            }

            //获取完整地址
            $city = $this->Area->getFullAddress($this->Input);
            $full_address = $city.$this->Input['name'];
            $full_address = str_replace(' ','',$full_address);
            //检测redis是否开启
            try{
                $this->Redis::ping();
            }catch(\Exception $exception)
            {
                return apiReturn(-500, '服务器内部错误，redis未开启！');
            }
            $locate = $this->Locate->addressLocate($full_address);

            if(!$locate){
                $locate = $this->Locate->addressLocate(str_replace(' ','',$city));
            }

            if($locate){
                $data['lng'] = $locate['lng'];
                $data['lat'] = $locate['lat'];
            }

            //组装参数
            $data['address_details'] = $full_address;

        }

        if($this->Input['type'] == 2){
            //验证参数
            if(!isset($this->Input['address_id'])){
                $error['errors']['address_id'] = '为必传项!';
                return apiReturn(-104, '数据验证失败', $error);
            }
            $data = $this->Address::getAddressById($this->Input['address_id']);
            $data = objectToArray($data);

            unset($data['id']);
            unset($data['created_at']);
            unset($data['updated_at']);
        }

        $data['seller_id'] = $seller->id;
        $data['account_businesses_id'] = $business->id;

        $result = $this->Address::create($data);

        if(!count($result)){
            return apiReturn(-20001, '提货地址添加失败！');
        }

        return apiReturn(0, '操作成功！');
    }

    /**
     * 提货地址列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function deliveryAddressList()
    {

        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-10021, '您不是卖家，无法添加提货地址！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $data['account_employee_id'] = $employee->id;
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常,无法添加提货地址！');
            }
            $seller = $account->AccountSeller;
        }else{
            $seller = $user->AccountSeller;
        }

        $data['address_lists'] = array();
        $data['address_lists'] = $this->Address::getAddressBySellerId($seller->id);

        return apiReturn(0, '操作成功！',$data);
    }


    /**
     * 编辑提货地址
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function editDeliveryAddress()
    {
        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-10021, '您不是卖家，无法编辑提货地址！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $data['account_employee_id'] = $employee->id;
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常,无法编辑提货地址！');
            }
            $seller = $account->AccountSeller;
        }else{
            $seller = $user->AccountSeller;
        }

        // 接收地址id
        if (!isset($this->Input['id'])) {
            $return_data['errors']['id'] = '为必填项';
            return apiReturn(-104,'数据验证失败',$return_data);
        }

        //地址id是否有效
        if(!is_null($employee)){
            $address = $this->Address::findAddressByAddressIdAndEmployeeId($this->Input['id'],$employee->id);
        }else{
            $address = $this->Address::findAddressByAddressIdAndSellerId($this->Input['id'],$seller->id);
        }

        if(is_null($address)){
            return apiReturn(-20000,'提货地址不存在！');
        }

        //验证新密码
        if($this->verifyAddress()){
            return $this->verifyAddress();
        }

        //获取完整地址
        $data = $this->Input;
        $city = $this->Area->getFullAddress($this->Input);
        $full_address = $city.$this->Input['name'];
        $full_address = str_replace(' ','',$full_address);
        //检测redis是否开启
        try{
            $this->Redis::ping();
        }catch(\Exception $exception)
        {
            return apiReturn(-500, '服务器内部错误，redis未开启！');
        }
        $locate = $this->Locate->addressLocate($full_address);

        if(!$locate){
            $locate = $this->Locate->addressLocate(str_replace(' ','',$city));
        }

        if($locate){
            $data['lng'] = $locate['lng'];
            $data['lat'] = $locate['lat'];
        }

        //组装参数
        $data['address_details'] = $full_address;

        $result = $this->Address::update($this->Input['id'],$data);

        if(!count($result)){
            return apiReturn(-20002, '提货地址编辑失败！');
        }

        return apiReturn(0, '操作成功！');

    }


    /**
     * 删除提货地址
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteDeliveryAddress()
    {
        $token = $this->Request->header('token');
        $user = $this->Account::getUserByToken($token);

        if($user->account_type != Account::ACCOUNT_TYPE['seller']){
            return apiReturn(-10021, '您不是卖家，无法删除提货地址！');
        }

        //判断是否为员工账户
        $employee = $user->accountEmployee;
        if(!empty($employee)){
            $data['account_employee_id'] = $employee->id;
            $account = $this->Account::getAccountById($employee->super_id);
            if(!$account){
                return apiReturn(-30007, '账户异常,无法删除提货地址！');
            }
            $seller = $account->AccountSeller;
        }else{
            $seller = $user->AccountSeller;
        }

        // 接收地址id
        if (!isset($this->Input['id'])) {
            $return_data['errors']['id'] = '为必填项';
            return apiReturn(-104,'数据验证失败',$return_data);
        }

        // 接收地址id
        if (!isset($this->Input['id'])) {
            $return_data['errors']['id'] = '为必填项';
            return apiReturn(-104,'数据验证失败',$return_data);
        }

        //地址id是否有效
        if(!is_null($employee)){
            $address = $this->Address::findAddressByAddressIdAndEmployeeId($this->Input['id'],$employee->id);
        }else{
            $address = $this->Address::findAddressByAddressIdAndSellerId($this->Input['id'],$seller->id);
        }

        if(is_null($address)){
            return apiReturn(-20000,'提货地址不存在！');
        }

        if($this->Address::deleteAddressById($this->Input['id'])){
            return apiReturn(0, '操作成功！');
        }

        return apiReturn(-20003, '提货地址删除失败！');
    }

    /**
     * 验证提货地址
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function verifyAddress()
    {
        $validator = $this->Validator::make($this->Input, [
            'province' => 'required | integer',
            'city' => 'required | integer',
            'name' => 'required',
        ], [
            'required' => '为必填项',
            'integer' => '整数',
        ], [
            'province' => '省',
            'city' => '市',
            'name' => '提货地址描述',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        return false;
    }

}
