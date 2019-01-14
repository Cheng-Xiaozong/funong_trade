<?php

namespace App\Http\Controllers\Admin;
use App\Admin;
use App\Http\Controllers\BaseController;
use App\Menu;
use App\Services\AdminUserService;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Predis\PredisException;

class UserController extends BaseController
{
    protected $Request;
    protected $Log;
    protected $Redis;
    protected $Msg;
    protected $Validator;
    protected $Area;
    protected $user;

    public function __construct(
        Request $request,
        Log $log,
        Redis $redis,
        AdminUserService $user,
        Validator $validator
    ){
        parent::__construct($request, $log, $redis);
        $this->Validator = $validator;
        $this->user = $user;
    }

    /**
     * 登陆
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        try{
            $this->Redis::ping();
        }catch(PredisException $exception)
        {
            return apiReturn(-500, '服务器内部错误，redis未开启！');
        }
        $validator = $this->Validator::make($this->Input, [
            'user_name' => 'required',
            'password' => 'required'
        ], [
            'required' => '为必填项',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        //校验数据
        $result=$this->user::attempt($this->Input['user_name'],$this->Input['password']);
        if(!empty($result)){
            if(!empty($result['user']->image)){
                $result['user']->image=getImgUrl($result['user']->image,'admin_imgs','');
            }
//            //记录token
//            $token = $this->Redis::get('admin_'.$this->Input['user_name'].'token');
//            if($token){
//                $this->user::addBlacklist($token);
//                $this->Redis::del('admin_'.$this->Input['user_name'].'token');
//            }else{
//                 $this->Redis::set('admin_'.$this->Input['user_name'].'token',$result['token']);
//            }
            return apiReturn(0,'登陆成功',$result);
        }else{
            return apiReturn(-10001,'登陆名或者密码错误');
        }
    }
    /**
     * 退出
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginOut()
    {
        $validator = $this->Validator::make($this->Input, [
            'token' => 'required'
        ], [
            'required' => '为必填项',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        //校验数据
        $result=$this->user::addBlacklist($this->Input['token']);
        if(!empty($result)){
            return apiReturn(0,'退出成功');
        }else{
            return apiReturn(-10003, '退出失败');
         }

    }
    /**
     * 获取左侧菜单 包括权限控制
     * @return \Illuminate\Http\JsonResponse
     */
    public function navList()
    {
        $result=$this->user::nav($this->Request->input('token'));
        if(!empty($result)){
            return apiReturn(0,'获取成功',['nav_list',$result]);
        }else{
            return apiReturn(-10002,'无权限');
        }
    }
    /**
     * 获取全部角色
     * @return \Illuminate\Http\JsonResponse
     */
    public function roleAllList()
    {
        $result=$this->user::roleAllList();
        if(!empty($result)){
            return apiReturn(0,'获取成功',['role_list',$result]);
        }else{
            return apiReturn(-10007,'获取角色列表失败');
        }
    }
    /**
     * 搜索用户列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchUser()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
            'where' => 'array',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->user::searchUser($this->Input['where'],$this->Input['page_size'],$this->Input['page']);
        if(!empty($result)){
            return apiReturn(0,'获取成功',$result);
        }else{
            return apiReturn(-10004,'获取用户列表失败');
        }
    }

    /**
     * 获取全部菜单
     * @return \Illuminate\Http\JsonResponse
     */
    public function navAllList()
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
        $data['nav_list']=$this->user::navAllList($this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 获取单个用户信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserById()
    {
        $validator = $this->Validator::make($this->Input, [
            'id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->user::getUserById($this->Input['id']);
        if(!empty($result)){
            return apiReturn(0,'获取用户成功',$result);
        }else{
            return apiReturn(-10014,'获取用户失败');
        }
    }
    /**
     * 用户列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function userList()
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
        $result=$this->user::userList($this->Input['page_size'],$this->Input['page']);
        if(!empty($result)){
            return apiReturn(0,'获取用户成功',$result);
        }else{
            return apiReturn(-10004,'获取用户列表失败');
        }
    }

    /**
     * 获取单个角色信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRoleById()
    {
        $validator = $this->Validator::make($this->Input, [
            'id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->user::getRoleById($this->Input['id']);
        if(!empty($result)){
            return apiReturn(0,'获取用户成功',$result);
        }else{
            return apiReturn(-10014,'获取用户失败');
        }
    }

    /**
     * 搜索角色列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchRole()
    {
        $validator = $this->Validator::make($this->Input, [
            'page_size' => 'required | integer',
            'page' => 'required | integer',
            'where' => 'array',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'array' => '不合法',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $result=$this->user::searchRole($this->Input['where'],$this->Input['page_size'],$this->Input['page']);
        if(!empty($result)){
            return apiReturn(0,'获取成功',$result);
        }else{
            return apiReturn(-10010,'获取角色列表失败');
        }
    }
    /**
     * 角色列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function roleList()
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
        $result=$this->user::roleList($this->Input['page_size'],$this->Input['page']);
        if(!empty($result)){
            return apiReturn(0,'获取角色列表成功',$result);
        }else{
            return apiReturn(-10010,'获取角色列表失败');
        }
    }
    /**
     * 添加后台用户
     * @return \Illuminate\Http\JsonResponse
     */
    public function addUser()
    {
        $this->Input=(array)json_decode($this->Request->all()['form_data'],JSON_UNESCAPED_UNICODE);

        $validator = $this->Validator::make($this->Input, [
            'user_name' => 'required | unique:admin,user_name',
            'full_name' => 'required | unique:admin,full_name|regex:/^[\x7f-\xff]+$/',
            'role_id'=>'required | integer',
            'status_at'=>'required | integer',
            'password'=>'required',
        ], [
            'required' => '为必填项',
            'full_name.regex' => '必须为中文',
            'user_name.unique'=>'用户名已存在',
            'full_name.unique'=>'姓名已存在',
            'integer' => '必须为整数',
            'role_id.required'=>'未选择角色用户',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $this->Input['image']='';
        $this->Input['password']=myEncrypt($this->Input['password']);
        $res=$this->user::addUser($this->Input);
        if($res){
            return apiReturn(0,'创建用户成功！');
        }else{
            return apiReturn(-10005,'创建用户失败！');
        }
    }


    /**
     * 重置密码
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetUserPassword()
    {
        $validator = $this->Validator::make($this->Input, [
            'old_password' => 'required',
            'password'=>'required|min:6|confirmed',
            'password_confirmation' => 'required|min:6',
            'admin_id'=>'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
            'min' => '至少6位数',
            'password.confirmed' => '密码不一致',
        ]);
        $validator->after(function($validator) {
            $verify_password=$this->user::verifyPassword($this->Input['old_password'],$this->Input['admin_id']);
            if(empty($verify_password)){
                $validator->errors()->add('old_password', '旧密码输入错误！');
            }
        });
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $this->Input['password']=myEncrypt($this->Input['password']);
        $res=$this->user::updateUserPassword($this->Input['password'],$this->Input['admin_id']);
        if($res){
            return apiReturn(0,'修改密码成功！');
        }else{
            return apiReturn(-10005,'修改密码失败！');
        }
    }

    /**
     * 修改后台用户
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUser()
    {
        $this->Input=(array)json_decode($this->Request->all()['form_data'],JSON_UNESCAPED_UNICODE);

        $validator = $this->Validator::make($this->Input, [
            'role_id'=>'required | integer',
            'status_at'=>'required | integer',
            'admin_id'=>'required',
        ], [
            'required' => '为必填项',
            'unique'=>'必须唯一，已存在',
            'integer' => '必须为整数',
            'role_id.required'=>'未选择角色用户',
        ]);
        $validator->after(function($validator) {
            $full_name=$this->user::uniqueUserName($this->Input['full_name'],$this->Input['admin_id']);
            if(!empty($full_name)){
                $validator->errors()->add('full_name', '此名称已存在！');
            }
        });

        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $res=$this->user::updateUser($this->Input,$this->Input['admin_id']);
        if($res){
            return apiReturn(0,'修改用户成功！');
        }else{
            return apiReturn(-10006,'修改用户失败！');
        }
    }


    /**
     * 修改后台个人资料
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePersonal()
    {
        $this->Input=(array)json_decode($this->Request->all()['form_data'],JSON_UNESCAPED_UNICODE);

        $validator = $this->Validator::make($this->Input, [
            'admin_id'=>'required',
        ], [
            'required' => '为必填项',
            'unique'=>'必须唯一，已存在',
            'integer' => '必须为整数',
        ]);
        $validator->after(function($validator) {
            $full_name=$this->user::uniqueUserName($this->Input['full_name'],$this->Input['admin_id']);
            if(!empty($full_name)){
                $validator->errors()->add('full_name', '此名称已存在！');
            }
        });

        //修改头像
        if (Input::hasFile('image'))
        {

            $file=Input::file('image');
            if($file -> isValid()){
                $extension = $file -> getClientOriginalExtension(); //上传文件的后缀.
                $newName = date('Y-m-d-H-i-s') . '-' . uniqid() . '.' . $extension;
                //storage_file_name
                $storage_file_name=date('Y-m-d').'/'.$newName;
                //压缩比例
                $image=\Image::make($file)->resize(10, 10);
                //临时图片
                $tmp_image=$image->dirname.'/'.$image->basename;
                $result_image = Storage::disk('admin_imgs')->put($storage_file_name, file_get_contents($tmp_image));
                if(!$result_image){
                    $error['errors']['image']='上传头像失败';
                }else{
                    $this->Input['image']=$newName;
                }
            }
        }
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $res=$this->user::updateUser($this->Input,$this->Input['admin_id']);

        if($res){
            $result=$this->user::getUserById($this->Input['admin_id']);
            $result['image']=getImgUrl($result['image'],'admin_imgs','');
            return apiReturn(0,'修改用户成功！',$result);
        }else{
            return apiReturn(-10006,'修改用户失败！');
        }
    }


    /**
     * 添加后台角色
     * @return \Illuminate\Http\JsonResponse
     */
    public function addRole()
    {
        $this->Input=(array)json_decode($this->Request->all()['form_data'],JSON_UNESCAPED_UNICODE);

        $validator = $this->Validator::make($this->Input, [
            'name' => 'required | unique:admin_role,name',
            'status_at' => 'required',
        ], [
            'required' => '为必填项',
            'name.unique'=>'角色名称已存在！',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $res=$this->user::addRole($this->Input);
        if($res){
            return apiReturn(0,'创建角色成功！');
        }else{
            return apiReturn(-10008,'创建角色失败！');
        }
    }

    /**
     * 修改后台角色
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRole()
    {
        $this->Input=(array)json_decode($this->Request->all()['form_data'],JSON_UNESCAPED_UNICODE);

        $validator = $this->Validator::make($this->Input, [
            'status_at' => 'required',
            'role_id' => 'required',
        ], [
            'required' => '为必填项',
        ]);
        $validator->after(function($validator) {
            $full_name=$this->user::uniqueRoleName($this->Input['name'],$this->Input['role_id']);
            if(!empty($full_name)){
                $validator->errors()->add('name', '此名称已存在！');
            }
        });
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $res=$this->user::updateRole($this->Input,$this->Input['role_id']);
        if($res){
            return apiReturn(0,'修改角色成功！');
        }else{
            return apiReturn(-10009,'修改角色失败！');
        }
    }

    /**
     * 校验权限
     * @return \Illuminate\Http\JsonResponse
     */
    public function canPermission()
    {
        $validator = $this->Validator::make($this->Input, [
            'action'=>'required',
            'role_id'=>'required',
        ], [
            'required' => '为必填项',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $res=$this->user::canPermission($this->Input['action'],$this->Input['role_id']);
        if($res){
            return apiReturn(0,'校验成功！');
        }else{
            return apiReturn(-10011,'校验失败！');
        }
    }



    /**
     * 获取权限
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPermission()
    {
        $validator = $this->Validator::make($this->Input, [
            'role_id'=>'required',
        ], [
            'required' => '为必填项',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $res=$this->user::getPermission($this->Input['role_id']);
        if($res){
            return apiReturn(0,'获取成功！',$res);
        }else{
            return apiReturn(-10012,'获取失败！');
        }
    }

    /**
     * 设置权限
     * @return \Illuminate\Http\JsonResponse
     */
    public function setPermission()
    {
        $validator = $this->Validator::make($this->Input, [
            'role_id'=>'required',
            'menu_list'=>'required | array',
        ], [
            'required' => '为必填项',
            'array' => '必须传入一个数组',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $res=$this->user::setPermission($this->Input['role_id'],$this->Input['menu_list']);
        if($res){
            return apiReturn(0,'设置成功！',$res);
        }else{
            return apiReturn(-10013,'设置失败！');
        }
    }

    /**
     * 刷新token
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken()
    {
        $validator = $this->Validator::make($this->Input, [
            'token'=>'required',
        ], [
            'required' => '为必填项',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $res=$this->user::refreshToken($this->Input['token']);
        if($res){
            return apiReturn(0,'设置成功！',$res);
        }else{
            return apiReturn(-10013,'设置失败！');
        }
    }

    /**
     * 添加菜单
     * @return \Illuminate\Http\JsonResponse
     */
    public function addMenu()
    {
        $validator = $this->Validator::make($this->Input, [
            'name' => 'required | unique:admin_menu,name',
            'url' => 'unique:admin_menu,url',
            'level'=>'required | integer',
            'status_at'=>'required | integer',
        ], [
            'required' => '为必填项',
            'name.unique'=>'菜单名已存在',
            'url.unique'=>'权限URL已存在',
            'integer' => '必须为整数',
        ]);
        $validator->after(function($validator) {

            if($this->Input['level']==1){
                if(empty($this->Input['icon']))
                $validator->errors()->add('icon', '顶级图标必须填写！');
            }

        });
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $res=$this->user::addMenu($this->Input);
        if($res){
            return apiReturn(0,'创建菜单成功！');
        }else{
            return apiReturn(-10016,'创建菜单失败！');
        }
    }

    /**
     * 修改菜单
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMenu()
    {
        $validator = $this->Validator::make($this->Input, [
            'menu_id'=>'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        $validator->after(function($validator) {
            $name=$this->user::uniqueMenuName($this->Input['name'],$this->Input['menu_id']);
            if(!empty($name)){
                $validator->errors()->add('name', '此菜单名称已存在！');
            }
            //如果是1 就代表不是顶级 是操作项
            if(!empty($this->Input['operate']) && $this->Input['operate']==1){
                $url=$this->user::uniqueMenuUrl($this->Input['url'],$this->Input['menu_id']);
                if(!empty($url)){
                    $validator->errors()->add('url', '此操作URL已存在！');
                }
                unset($this->Input['operate']);
            }

        });
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }

        $res=$this->user::updateMenu($this->Input,$this->Input['menu_id']);
        if($res){
            return apiReturn(0,'修改菜单成功！');
        }else{
            return apiReturn(-10017,'修改菜单失败！');
        }
    }

    /**
     * 获取菜单详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMenuById()
    {
        $validator = $this->Validator::make($this->Input, [
            'id' => 'required | integer',
        ], [
            'required' => '为必填项',
            'integer' => '必须为整数',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $result=$this->user::getMenuById($this->Input['id']);
        return apiReturn(0,'获取菜单成功',$result);

    }


    /**
     * 搜索菜单
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchMenu()
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
        $data['all_menu']=$this->user::searchMenu($this->Input['where'],$this->Input['page_size']);
        return apiReturn(0,'获取成功',$data);
    }


    /**
     * 面包屑
     * @return \Illuminate\Http\JsonResponse
     */
    public function breadCrumb()
    {

        $validator = $this->Validator::make($this->Input, [
            'action'=>'required',
        ], [
            'required' => '为必填项',
        ]);
        if ($validator->fails()) {
            $error['errors'] = $validator->errors();
            return apiReturn(-104, '数据验证失败', $error);
        }
        $res=$this->user::breadCrumb($this->Input['action']);
        if($res){
            return apiReturn(0,'获取成功！',$res);
        }else{
            return apiReturn(-104,'获取失败！');
        }
    }


}
