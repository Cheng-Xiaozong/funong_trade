<?php

namespace App\Http\Controllers\Admin;

use App\AccountBusiness;
use App\Services\AccountService;
use App\Services\AddressLocateService;
use App\Services\AdminUserService;
use App\Services\SendMsgService;
use App\Services\AreaInfoService;
use App\Services\OrderService;;
use App\Services\MqttService;
use App\Services\SendWeChatMsgService;
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


class AccountController extends BaseController
{
    
    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Account;
    protected $Msg;
    protected $WeChatMsg;
    protected $Validator;
    protected $Area;
    protected $Locate;
    protected $AdminUser;
    protected $Mqtt;
    protected $Order;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AccountService $Account,
        SendMsgService $msg,
        SendWeChatMsgService $weChatMsg,
        Validator $validator,
        AreaInfoService $area,
        AddressLocateService $locate,
        OrderService $order,
        AdminUserService $admin_user,
        MqttService $mqtt
    ){
        parent::__construct($request, $log, $redis);
        $this->Account = $Account;
        $this->Msg = $msg;
        $this->WeChatMsg = $weChatMsg;
        $this->Validator = $validator;
        $this->Area = $area;
        $this->Locate = $locate;
        $this->Order = $order;
        $this->AdminUser = $admin_user;
        $this->Mqtt = $mqtt;
    }

    /**
     * 获取全部账户
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllAccount()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer'
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['accounts']=$this->Account::getAllAccount($this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }

    /**
     * 获取卖家买家账户
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllAccountApi()
    {
        $validator = $this->Validator::make($this->Input, [
            'action' => 'required | in:all,wating,passed,failed,expired',
            'type' => 'required | in:all,buyer,seller',
            'page_size' => 'required | integer',
            'page' => 'required | integer'
        ], [
            'required' => '为必填项',
            'in' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $data['business_list']=$this->Account::getAllAccountApi($this->Input,$this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }

    /**
     * 获取全部企业
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllBusiness()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer'
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['businesses']=$this->Account::getAllBusiness($this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }



    /**
     * 搜索账户
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchAccount()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
            'where' => 'array|required',
            'where.role' => 'integer',
            'where.status' => 'integer',
            'where.register_status' => 'integer',
            'where.account_type' => 'integer',
            'is_export' => 'integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['accounts']=$this->Account::searchAccount($this->Input['where'],$this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }

    /**
     * 账户启用禁用
     * @return \Illuminate\Http\JsonResponse
     */
    public function editAccountStatus()
    {
        $validator = $this->Validator::make($this->Input, [
            'account_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数'
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        if($this->Account::editAccountStatus($this->Input['account_id']))
        {
           return apiReturn(0,'操作成功');
        }else{
           return apiReturn(-20001,'操作失败');
        }
    }

    /**
     * 创建账户
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAccount()
    {
        //检测redis是否开启
        try{
            $this->Redis::ping();
        }catch(\Exception $exception)
        {
            return apiReturn(-500, '服务器内部错误，redis未开启！');
        }

        //验证文件
        $files=$this->Request->file();
        foreach ($files as $key=>$val)
        {
            $error=[];
            //判断文件是否出错
            if($val->getError()!=0)
            {
                $error[$key][]='上传失败，请检查系统配置！';
            }
            //判断的文件是否超出2M
            if( $val->getClientSize()>2097152)
            {
                $error[$key][]='超出文件最大限制2M';
            }
            if($error){
                $data['errors']=$error;
                return apiReturn(-105, '表单验证失败', $data);
            }
        }

        //验证数据
        $this->Input=(array)json_decode($this->Request->all()['form_data'],JSON_UNESCAPED_UNICODE);
        $validator=$this->accountDataValidation();
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-105, '表单验证失败', $data);
        }


        //创建账户
        DB::beginTransaction();
        $account_data=$this->Input['account_info'];
        $account_data['register_status']=Account::REGISTER_STATUS['inforamtion'];
        $account_data['password']=bcrypt($account_data['password']);
        $account=$this->Account::createAccount($account_data);
        if(!$account)
        {
            DB::rollBack();
            return apiReturn(-20002, '添加账户失败！');
        }

        //创建企业
        $files_name=[];
        foreach ($files as $key=>$val)
        {
            if($val->isValid()){
                $ext = $val->getClientOriginalExtension();     // 扩展名
                $realPath = $val->getRealPath();   //临时文件的绝对路径
                $filename = date('Y-m-d-H-i-s') . '-' . uniqid() . '.' . $ext;// 生成的文件名
                $result = Storage::disk('account_imgs')->put(date('Y-m-d').'/'.$filename, file_get_contents($realPath));
                if($result){
                    $files_name[$key]=$filename;
                }else{
                    return apiReturn(-20003, '文件保存失败，请检查服务器配置！');
                }
            }
        }
        $businesses_data=$this->Input['businesses_info'];
        $businesses_data['account_id']=$account->id;
        //计算经纬度
        $locate = $this->Locate->addressLocate($businesses_data['address_details']);
        if(!$locate)
        {
            $locate = $this->Locate->addressLocate($this->Area->getFullAddress($businesses_data));
        }
        $businesses_data['lng']=$locate['lng'];
        $businesses_data['lat']= $locate['lat'];
        if(isset($businesses_data['foreign_contacts']))
        {
            $businesses_data['foreign_contacts']=json_encode($businesses_data['foreign_contacts'],JSON_UNESCAPED_UNICODE);
        }
        if(isset($businesses_data['attributes']))
        {
            $businesses_data['attributes']=json_encode($businesses_data['attributes'],JSON_UNESCAPED_UNICODE);
        }
        if($files_name)
        {
            $businesses_data['review_status'] = AccountBusiness::REVIEW_STATUS['wating'];
            $businesses_data=array_merge($businesses_data,$files_name);
        }
        $businesses=$this->Account::createAccountInfo($businesses_data);
        if(!$businesses)
        {
            DB::rollBack();
            return apiReturn(-20004, '添加企业失败！');
        }

        //创建买家
        if($account->account_type==Account::ACCOUNT_TYPE['buyer'])
        {
            $buyer_data=$this->Input['buyer_info'];
            $buyer_data['account_id']=$account->id;
            $buyer_data['account_business_id']=$businesses->id;
            $buyer=$this->Account::createByuer($buyer_data);
            if(!$buyer)
            {
                DB::rollBack();
                return apiReturn(-20005, '添加买家失败！');
            }
            $account_object=$buyer;
        }

        //创建卖家
        if($account->account_type==Account::ACCOUNT_TYPE['seller'])
        {

            $seller_data=$this->Input['seller_info'];
            $seller_data['account_id']=$account->id;
            $seller_data['account_business_id']=$businesses->id;
            $seller=$this->Account::createSeller($seller_data);
            if(!$seller)
            {
                DB::rollBack();
                return apiReturn(-20006, '添加卖家失败！');
            }
            $account_object=$seller;
        }

        //提交数据
        if($account&&$businesses&&$account_object)
        {
            DB::commit();
            return apiReturn(0, '添加成功');
        }else{
            DB::rollBack();
            return apiReturn(-20007, '添加失败');
        }
    }

    /**
     * 检查账户是否重复
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAccountNumber()
    {
        $validator = $this->Validator::make($this->Input, [
            'account_number' => 'required ',
            'action' => 'required | in:edit,create',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
            'in'=>'不合法'
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $account_num=count($this->Account::getAccountsByNumber($this->Input['account_number']));
        if($this->Input['action']=='edit')
        {
            return $account_num>1 ? apiReturn(-20008, '该账户已存在') : apiReturn(0, '该账户可用！');
        }

        if($this->Input['action']=='create')
        {
            return $account_num>=1 ? apiReturn(-20008, '该账户已存在') : apiReturn(0, '该账户可用！');
        }
    }

    /**
     * 检查手机号是否重复
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAccountPhone()
    {
        $validator = $this->Validator::make($this->Input, [
            'phone' => 'required|digits:11 ',
            'action' => 'required | in:edit,create',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
            'in'=>'不合法',
            'digits'=>'不合法'
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $account_num=count($this->Account::getAccountsByPhone($this->Input['phone']));
        if($this->Input['action']=='edit')
        {
            return $account_num>1 ? apiReturn(-20009, '该手机已存在') : apiReturn(0, '该手机可用！');
        }

        if($this->Input['action']=='create')
        {
            return $account_num>=1 ? apiReturn(-20009, '该手机已存在') : apiReturn(0, '该手机可用！');
        }
    }

    /**
     * 编辑账户
     * @return \Illuminate\Http\JsonResponse
     */
    public function editAccount()
    {

        //检测redis是否开启
        try{
            $this->Redis::ping();
        }catch(\Exception $exception)
        {
            return apiReturn(-500, '服务器内部错误，redis未开启！');
        }

        //验证文件
        $files=$this->Request->file();

        foreach ($files as $key=>$val)
        {

            $error=[];
            //判断文件是否出错
            if($val->getError()!=0)
            {
                $error[$key][]='上传失败，请检查系统配置！';
            }
            //判断的文件是否超出2M
            if( $val->getClientSize()>2097152)
            {
                $error[$key][]='超出文件最大限制2M';
            }
            if($error){
                $data['errors']=$error;
                return apiReturn(-105, '表单验证失败', $data);
            }
        }

        //验证数据
        $this->Input=(array)json_decode($this->Request->all()['form_data'],JSON_UNESCAPED_UNICODE);
        $validator=$this->accountDataValidation('edit');
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-105, '表单验证失败', $data);
        }

        //编辑账户
        DB::beginTransaction();
        $account_data=$this->Input['account_info'];
        if(isset($account_data['password'])&&!empty($account_data['password']))
        {
            $account_data['password']=bcrypt($account_data['password']);
        }
        $account_id=$account_data['account_id'];
        unset($account_data['account_id']);
        $result=$this->Account::updateAccountById($account_id,$account_data);
        if($result==0)
        {
            DB::rollBack();
            return apiReturn(-20010, '更新账户失败！');
        }
        $account=$this->Account::getAccountById($account_id);

        //编辑企业
        $files_name=[];
        foreach ($files as $key=>$val)
        {
            if($val->isValid()){
                $ext = $val->getClientOriginalExtension();     // 扩展名
                $realPath = $val->getRealPath();   //临时文件的绝对路径
                $toDir = date('Y-m-d');
                $filename = date('Y-m-d-H-i-s') . '-' . uniqid() . '.' . $ext;// 生成的文件名
                $result = Storage::disk('account_imgs')->put($toDir . '/' . $filename, file_get_contents($realPath));
                if($result){
                    $files_name[$key]=$filename;
                }else{
                    return apiReturn(-20011, '文件保存失败，请检查服务器配置！');
                }
            }
        }

        $businesses_data=$this->Input['businesses_info'];
        $businesses_data['account_id']=$account_id;
        //查询经纬度
        $locate = $this->Locate->addressLocate($businesses_data['address_details']);
        if(!$locate)
        {
            $locate = $this->Locate->addressLocate($this->Area->getFullAddress($businesses_data));
        }
        $businesses_data['lng']=$locate['lng'];
        $businesses_data['lat']= $locate['lat'];
        //对外联系人
        if(isset($businesses_data['foreign_contacts']))
        {
            $businesses_data['foreign_contacts']=json_encode($businesses_data['foreign_contacts'],JSON_UNESCAPED_UNICODE);
        }
        //自定义属性
        if(isset($businesses_data['attributes']))
        {
            $businesses_data['attributes']=json_encode($businesses_data['attributes'],JSON_UNESCAPED_UNICODE);
        }
        //文件（身份证、营业执照等）
        if($files_name)
        {
            $businesses_data=array_merge($businesses_data,$files_name);
        }
        //判断注册状态
        $businesses=$this->Account::getAccountBusinessByAccountId($account_id);
        if($businesses)
        {
            $result=$this->Account::updateAccountInfoByAccountId($account_id,$businesses_data);
        }else{
            $businesses=$this->Account::createAccountInfo($businesses_data);
        }

        if($result==0||is_null($businesses))
        {
            DB::rollBack();
            return apiReturn(-20012, '更新企业失败！');
        }

        //编辑买家
        if($account->account_type==Account::ACCOUNT_TYPE['buyer'])
        {
            $buyer_data=$this->Input['buyer_info'];

            //判断注册状态
            $buyer=$this->Account::getBuyerByAccountId($account_id);

            if($buyer)
            {
                $result=$this->Account::updateBuyerByAccountId($account_id,$buyer_data);
            }else{
                $buyer_data['account_id']=$account->id;
                $buyer_data['account_business_id']=$businesses->id;
                $buyer=$this->Account::createByuer($buyer_data);
            }
            if($result==0||is_null($buyer))
            {
                DB::rollBack();
                return apiReturn(-20013, '更新买家失败！');
            }
            $account_object=true;
        }

        //编辑卖家
        if($account->account_type==Account::ACCOUNT_TYPE['seller'])
        {
            $seller_data=$this->Input['seller_info'];
            //判断注册状态
            $seller=$this->Account::getSellerByAccountId($account_id);
            if($seller)
            {
                $result=$this->Account::updateSellerByAccountId($account_id,$seller_data);
            }else{
                $seller_data['account_id']=$account->id;
                $seller_data['account_business_id']=$businesses->id;
                $seller=$this->Account::createSeller($seller_data);
            }

            if($result==0||is_null($seller))
            {
                DB::rollBack();
                return apiReturn(-20013, '更新买家失败！');
            }
            $account_object=true;
        }

        //提交数据
        if($account&&$businesses&&$account_object)
        {
            DB::commit();
            return apiReturn(0, '更新成功');
        }else{
            DB::rollBack();
            return apiReturn(-20015, '更新失败');
        }
    }

    /**
     * 创建账户字段验证
     * @param $type
     * @return \Illuminate\Validation\Validator
     */
    public function accountDataValidation($type=null)
    {
        $validation_rules=[
            'account_info' => 'required | array',
            'account_info.account_number' => 'required | max:10|min:2 | unique:accounts,account_number',
            'account_info.phone' => 'required|digits:11| unique:accounts,phone',
            'account_info.password' => 'required',
            'account_info.account_type' => 'required|integer',
            'account_info.status' => 'required|integer',
            'account_info.nickname' => 'max:30|min:2 ',
            'businesses_info' => 'required | array',
            'businesses_info.type' => 'required | integer',
            'businesses_info.name' => 'required',
            'businesses_info.contact_name' => 'required',
            'businesses_info.contact_phone' => 'required | digits:11',
            'businesses_info.legal_person' => 'required',
            'businesses_info.industry_id' => 'required|integer',
            'businesses_info.province' => 'required|integer',
            'businesses_info.city' => 'required|integer',
            'businesses_info.county' => 'required|integer',
            'businesses_info.address' => 'required',
            'businesses_info.address_details' => 'required',
            'businesses_info.postcode' => 'integer',
            'businesses_info.company_website' => 'url',
            'businesses_info.qq' => 'integer',
            'businesses_info.email' => 'email',
            'businesses_info.registered_capital' => 'numeric',
            'businesses_info.foreign_contacts' => 'array',
            'businesses_info.foreign_contacts.*.name' => 'required',
            'businesses_info.foreign_contacts.*.position' => 'required',
            'businesses_info.foreign_contacts.*.phone' => 'required',
            'businesses_info.foreign_contacts.*.qq' => 'integer',
            'businesses_info.foreign_contacts.*.email' => 'email',
            'businesses_info.attributes' => 'array'
        ];

        //更新排除当前记录
        if($type=="edit")
        {
            $validation_rules['account_info.account_id']='required | integer';
            $validation_rules['account_info.account_number']='required | max:10|min:2 ';
            $validation_rules['account_info.phone']='required|digits:11';
            $validation_rules['account_info.password']='';
        }

        $validation_rules_describe= [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
            'unique'=>'必须唯一，已存在',
            'email'=>'不合法',
            'between'=>'长度不合法',
            'digits'=>'必须为数字且长度不合法',
            'digits_between'=>'必须为指定数字',
            'max'=>'超过限制最大位数',
            'min'=>'不足限制最小位数',
        ];

        //买家
        if($this->Input['account_info']['account_type']==0)
        {
            $validation_rules['buyer_info']='array';
        }

        //卖家
        if($this->Input['account_info']['account_type']==1)
        {
            $validation_rules['seller_info']='required | array';
            $validation_rules['seller_info.release_type']='required | integer';
            $validation_rules['seller_info.quote_type']='required | integer';
        }

        return $this->Validator::make($this->Input,$validation_rules ,$validation_rules_describe);
    }


    /**
     * 根据ID获取账户
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAccountById()
    {
        $validator = $this->Validator::make($this->Input, [
            'account_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $account=$this->Account::getAccountById($this->Input['account_id']);
        if(is_null($account))
        {
            return apiReturn(-20016, '账户不存在，非法请求！');
        }
        $account_info=$account->AccountInfo;
        if($account_info)
        {
            $account_info['legal_id_positive']=getImgUrl($account_info->legal_id_positive,'account_imgs','');
            $account_info['legal_id_reverse']=getImgUrl($account_info->legal_id_reverse,'account_imgs','');
            $account_info['business_license']=getImgUrl($account_info->business_license,'account_imgs','');
            $account_info['company_logo']=getImgUrl($account_info->company_logo,'account_imgs','');
        }
        $data['account']=$account;
        $data['account']['account_info']= $account_info;
        $data['account']['account_buyer']= $account->AccountBuyer;
        $data['account']['account_seller']= $account->AccountSeller;
        return apiReturn(0,'获取成功！',$data);
    }


    /**
     * 根据ID获取企业
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBusinessById()
    {
        $validator = $this->Validator::make($this->Input, [
            'business_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $business=$this->Account::getBusinessById($this->Input['business_id']);
        $business['legal_id_positive']=getImgUrl($business->legal_id_positive,'account_imgs','');
        $business['legal_id_reverse']=getImgUrl($business->legal_id_reverse,'account_imgs','');
        $business['business_license']=getImgUrl($business->business_license,'account_imgs','');
        $business['company_logo']=getImgUrl($business->company_logo,'account_imgs','');
        $data['business']=$business;
        $data['business']['account']= $business->Account;
        return apiReturn(0,'获取成功！',$data);
    }


    /**
     * 搜索企业
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchBusiness()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
            'where' => 'required|array',
            'where.province' => 'integer',
            'where.city' => 'integer',
            'where.county' => 'integer',
            'where.review_status' => 'integer',
            'where.type' => 'integer',
            'where.contact_phone' => '',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $data['businesses']=$this->Account::searchBusiness($this->Input['where'],$this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 修改名称
     * @return \Illuminate\Http\JsonResponse
     */
    public function modifyName()
    {
        $validator = $this->Validator::make($this->Input, [
            'name' => 'required',
            'account_id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $account = $this->Account::getAccountById($this->Input['account_id']);
        $business = $account->AccountInfo;

        $account_data['nickname'] = $this->Input['name'];
        $business_data['contact_name'] = $this->Input['name'];
        $business_data['name'] = $this->Input['name'];

        $account_result = $this->Account::updateAccountById($account->id,$account_data);
        $business_result = $this->Account::updateAccountInfoById($business->id,$business_data);
        DB::beginTransaction();

        if($account_result && $business_result)
        {
            DB::commit();
            return apiReturn(0, '更新成功');
        }else{
            DB::rollBack();
            return apiReturn(-20015, '更新失败');
        }
    }

    /**
     * 企业审核
     * @return \Illuminate\Http\JsonResponse
     */
    public function businessAudit()
    {
        $content=$this->Input;
        $validator = $this->Validator::make($content, [
            'business_id' => 'required | integer',
            'legal_id_positive' => 'required | array',
            'legal_id_positive.status' => 'required | integer',
            'legal_id_reverse' => 'required | array',
            'legal_id_reverse.status' => 'required | integer',
            'business_license' => 'required | array',
            'business_license.status' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $data['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $data);
        }
        //营业执照通过必须输入过期时间
        if($content['business_license']['status']==0)
        {
            if(!isset($content['business_license']['license_expiry_time'])||empty($content['business_license']['license_expiry_time']))
            {
                $data['errors']['business_license']['license_expiry_time'] = '营业执照过期时间不能为空！';
                return apiReturn(-104, '数据验证失败',$data);
            }else{
                $data['license_expiry_time']=$content['business_license']['license_expiry_time'];
            }
        }
        //准备数据
        if($content['legal_id_positive']['status']==0&&$content['legal_id_reverse']['status']==0&&$content['business_license']['status']==0)
        {
            $data['review_status']=AccountBusiness::REVIEW_STATUS['passed'];
        }else{
            $data['review_status']=AccountBusiness::REVIEW_STATUS['failed'];
        }
        //TODO 审核日志
        $business=$this->Account::getBusinessById($content['business_id']);
        $account = $business->Account;
        unset($content['business_id']);
        $data['review_detail']=json_encode($content,JSON_UNESCAPED_UNICODE);
        if($business->review_log)
        {
             $review_log=json_decode($business->review_log,true);
        }
        $review_data['review_detail']=$data['review_detail'];
        $admin_user=$this->AdminUser::getUserByToken($this->Request->header('token'));
        $review_data['review_reviewer']=$admin_user->full_name;
        $review_data['review_time']=date('Y-m-d H:i:s');
        $review_log[]=$review_data;
        $data['review_log']=json_encode($review_log,JSON_UNESCAPED_UNICODE);
        if($this->Account::updateAccountInfoById($business->id,$data))
        {

            if($data['review_status']==AccountBusiness::REVIEW_STATUS['passed']){
                $this->Mqtt->sendCommonMsg('account','审核通过!',$account->id,$account->id);
            }else{
                $this->Mqtt->sendCommonMsg('account','审核未通过!',$account->id,$account->id);
            }
            return apiReturn(0,'审核成功');
        }else{
            return apiReturn(-20016, '审核失败！');
        }
    }
}
