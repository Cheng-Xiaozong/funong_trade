<?php

namespace App\Http\Controllers\Admin;

use App\GoodsDeliveryAddress;
use App\Services\AccountService;
use App\Services\AreaInfoService;
use App\Services\AddressLocateService;
use App\Services\AddressService;
use App\Services\CommonService;
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
    protected $Common;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AccountService $Account,
        CommonService $common,
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
        $this->Common = $common;
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
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
            'where' => 'array|required',
            'where.name' => 'string',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['address_list']=$this->Address::searchDeliveryAddress($this->Input['where'],$this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }

    /**
     * 添加提货地址
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function addDeliveryAddress()
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
        //组装参数
        $data = $this->Input;
        //获取完整地址
        $city = $this->Area->getFullAddress($this->Input);
        $full_address = $city.$this->Input['name'];
        $full_address = str_replace(' ','',$full_address);
        $data['address_details'] = $full_address;
        $locate = $this->Locate->addressLngLat($full_address);

        if(!$locate){
            $locate = $this->Locate->addressLngLat(str_replace(' ','',$city));
        }

        if($locate){
            $data['lng'] = $locate['lng'];
            $data['lat'] = $locate['lat'];
        }

        $result = $this->Address::create((array)$data);

        if($result){
            return apiReturn(0, '添加商品地址成功！');
        }
        return apiReturn(-40009, '添加商品地址失败！');

    }



    /**
     * 编辑提货地址
     * @return bool|\Illuminate\Http\JsonResponse
     */
    public function editDeliveryAddress()
    {
        $validator = $this->Validator::make($this->Input, [
            'id'=>'required',
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

        $validator->after(function($validator) {
            $model_address=new GoodsDeliveryAddress();
            $full_name=$this->Common::uniqueModelAttr($model_address,'name',$this->Input['name'],$this->Input['id']);
            if(!empty($full_name)){
                $validator->errors()->add('name', '此提货地址已存在！');
            }
        });

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        //获取完整地址
        $full_address = $this->Area->getFullAddress($this->Input).$this->Input['name'];
        $full_address = str_replace(' ','',$full_address);
        $locate = $this->Locate->addressLngLat($full_address);

        //组装参数
        $data = $this->Input;
        $data['address_details'] = $full_address;
        $data['lng'] = $locate['lng'];
        $data['lat'] = $locate['lat'];

        $result = $this->Address::update($this->Input['id'],$data);
        if($result){
            return apiReturn(0,'修改商品地址成功！');
        }else{
            return apiReturn(-40010,'修改商品地址失败！');
        }

    }


    /**
     * 提货地址列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function deliveryAddressList()
    {

        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->Address::addressList($this->Input['page_size']);
        $data['address_lists']=$result;
        return apiReturn(0,'获取地址列表成功',$data);
    }


    /**
     * 根据ID获取商品地址详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGoodsAddressById()
    {
        $validator = $this->Validator::make($this->Input, [
            'address_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        $result=$this->Address::getAddressByIds($this->Input['address_id']);

        return apiReturn(0,'获取成功',$result[0]);
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


    /**
     * 获取公共的地址列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllAddressApi()
    {
        $validator = $this->Validator::make($this->Input, [
            'action' => 'required | in:enabled,disable',
        ], [
            'required' => '为必填项',
            'in' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['address_list']=$this->Address::getAllAddressApi($this->Input);
        return apiReturn(0,'获取成功',$data);
    }



    /**
     * 删除提货地址
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAddress()
    {
        $validator = $this->Validator::make($this->Input, [
            'address_id' => 'required | numeric',
        ], [
            'required' => ':attribute为必填项',
            'number' => ':attribute为数字',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $goods_address_status = $this->Address::getGoodsByAddress($this->Input['address_id']);
        if($goods_address_status){
            return apiReturn(-40012, '对不起，您无法删除此提货地址，在商品表里有使用到此提货地址！');
        }

        if($this->Address::deleteGoodsAddressById($this->Input['address_id'])){
            return apiReturn(0, '删除成功！');
        }

        return apiReturn(-40011, '删除失败！');
    }
}
