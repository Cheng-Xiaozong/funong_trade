<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/8/24
 * Time: 11:12
 */
namespace App\Services;


use App\Access;
use App\Admin;
use App\AdminAccess;
use App\AdminMenu;
use App\AdminRole;
use App\Menu;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Account;
use App\AccountBusiness;
use App\AccountLog;
use App\AccountBuyer;
use App\AccountSeller;
use Tymon\JWTAuth\Token;


class AdminUserService
{


    /**
     * 校验登陆
     * @param $username
     * @param $password
     * @return array|null
     */
    public static function attempt($username,$password)
    {
        $user=Admin::where('user_name', $username)->where('password',myEncrypt($password))->first();
        if(!empty($user)){
           $token=self::createToken($user);
           $data['token']=$token;
           $data['user']=self::getUserByToken($token);
           return $data;
        }else{
           return null;
        }

    }

    /**
     * 获取左侧菜单
     * @param $token
     * @return mixed
     */
    public static function nav($token)
    {
        $user=self::getUserByToken($token);
        $menu = AdminMenu::join('admin_access','admin_menu.id','=','admin_access.menu_id')->where('admin_access.role_id',$user->role_id)->where('admin_menu.pid',1)->where('admin_menu.status_at',1)->orderBy('admin_menu.sort', 'asc')->get();
        foreach ($menu as $item) {
            $item['submenu'] = AdminMenu::join('admin_access','admin_menu.id','=','admin_access.menu_id')->where('admin_access.role_id',$user->role_id)->where('admin_menu.pid',$item->id)->where('admin_menu.status_at',1)->orderBy('admin_menu.sort', 'asc')->get();
        }
        return $menu;
    }

    /**
     * 通过ID搜索用户
     * @param $id
     * @return array
     */
    public static function getUserById($id)
    {
        return Admin::where('id',$id)->with('role')->first()->toArray();
    }

    /**
     * 通过ID搜索用户
     * @param $id
     * @return array
     */
    public static function getRoleById($id)
    {
        return AdminRole::find($id)->toArray();
    }

    /**
     * 搜索用户列表
     * @param $where array
     * @param $page_size int
     * @param $page int
     * @return mixed
     */
    public static function searchUser($where,$page_size,$page)
    {
        $position = (($page-1) * $page_size);
        $admin_list=Admin::select('*')->with('role');
        if(isset($where['full_name']))
        {
            $admin_list->where('full_name', 'like', '%'.$where['full_name'].'%');
        }
        $data['total']=$admin_list->count();
        $data['admin_user_list']=$admin_list->limit($page_size)->offset($position)->get();
        return $data;
    }

    /**
     * 获取用户列表
     * @param $page_size
     * @param $page
     * @return array
     */
    public static function userList($page_size=10,$page=1)
    {
        $position = (($page-1) * $page_size);
        $admin_list=Admin::select('*')->with('role')->orderBy('id', 'asc');
        $data['total']=$admin_list->count();
        $data['admin_user_list']=$admin_list->limit($page_size)->offset($position)->get();
        return $data;
    }

    /**
     * 创建用户
     * @param $data array
     * @return mixed
     */
    public static function addUser($data)
    {
        return Admin::create($data);
    }

    /**
     * 修改用户
     * @param $data array
     * @param $admin_id int
     * @return mixed
     */
    public static function updateUser(array $data,$admin_id=0)
    {
        unset($data['admin_id']);
        unset($data['role_id']);
        unset($data['password']);
        unset($data['status_at']);
        return $admin = Admin::where('id', $admin_id)->update($data);
    }

    /**
     * 搜索角色列表
     * @param $where array
     * @param $page_size int
     * @param $page int
     * @return mixed
     */
    public static function searchRole($where,$page_size,$page)
    {
        $position = (($page-1) * $page_size);
        $admin_list=AdminRole::select('*');
        if(isset($where['name']))
        {
            $admin_list->where('name', 'like', '%'.$where['name'].'%');
        }
        $data['total']=$admin_list->count();
        $data['admin_role_list']=$admin_list->limit($page_size)->offset($position)->get();
        return $data;
    }

    /**
     * 获取角色列表
     * @param $page_size int
     * @param $page int
     * @return mixed
     */
    public static function roleList($page_size,$page)
    {
        $position = (($page-1) * $page_size);
        $admin_list=AdminRole::select('*')->orderBy('id', 'asc');
        $data['total']=$admin_list->count();
        $data['admin_user_list']=$admin_list->limit($page_size)->offset($position)->get();
        return $data;

    }

    /**
     * 创建角色
     * @param $data array
     * @return mixed
     */
    public static function addRole($data)
    {
        return AdminRole::create($data);
    }

    /**
     * 修改角色
     * @param $data array
     * @param $role_id int
     * @return mixed
     */
    public static function updateRole(array $data,$role_id=0)
    {
        unset($data['role_id']);
        return $admin = AdminRole::where('id', $role_id)->update($data);
    }
    /**
     * 获取全部角色列表
     * @return mixed
     */
    public static function roleAllList()
    {
        return AdminRole::where('status_at',1)->get();
    }

    /**
     * 获取全部菜单列表
     * @return mixed
     */
    public static function navAllList($page_size)
    {
        return AdminMenu::paginate($page_size);
    }
    /**
     * 刷新用户token
     * @param $old_token
     * @return \Illuminate\Http\JsonResponse
     */
    public static function refreshToken($old_token)
    {
        $token = JWTAuth::refresh($old_token);
        return $token;
    }


    /**
     * 创建token
     * @param $user object
     * @return mixed
     */
    public static function createToken($user)
    {
        return JWTAuth::fromUser($user);
    }


    /**
     * 根据token获取用户
     * @param $token
     * @return mixed
     */
    public static function getUserByToken($token)
    {
        return JWTAuth::toUser($token);
    }

    /**
     * token 拉黑
     * @param $token
     * @return mixed
     */
    public static function addBlacklist($token)
    {
        $token=new Token($token);
        $manager=JWTAuth::manager();
        return $manager->invalidate($token);
    }

    /**
     * action 操作URL
     * @param $action string
     * @param $role_id int
     * @return true|false
     */
    public static function canPermission($action,$role_id)
    {
        $routeinfo=AdminMenu::where('level',3)->where('url',$action)->first();
        if($routeinfo){
            $ct=AdminAccess::where('role_id',$role_id)->where('menu_id',$routeinfo->id)->count();
            if(!empty($ct)){
                return true;
            }else{
                return false;
            }
        }
    }

    /**
     * action 获取权限
     * @param $role_id int
     * @return array
     */
    public static function getPermission($role_id)
    {
        $mlv=AdminMenu::get()->toArray();

        $arrt=self::getTreePermission($mlv,1,self::accessList(),$role_id);
        $data['menu']=$arrt;
        return $data;
    }

    /**
     * action 获取当前所在的权限数组
     * @return array
     */
    public static function  accessList(){
        return AdminAccess::all()->toArray();
    }
    /**
     * action 获取所有的菜单列表
     * @param $data array
     * @param $pid int
     * @param $accessList array
     * @param $role_id int
     * @return array
     */
    public static function getTreePermission($data,$pid=0,$accessList,$role_id)
    {
        $tree = array();
        foreach($data as $k => $v)
        {
            if($v['pid'] == $pid){
                $tmpArr = array();
                $tmpArr['id']=$v['id'];;
                $tmpArr['name']=$v['name'];
                if(self::is_checked($role_id,$v['id'],$accessList)){
                    $tmpArr['is_checked']=true;
                }else{
                    $tmpArr['is_checked']=false;
                }
                $childern=self::getTreePermission($data,$v['id'],$accessList,$role_id);
                if(!empty($childern)){
                    $tmpArr['children'] =$childern;
                }
                $tree[] = $tmpArr;
                unset($data[$k]);
            }
        }
        return $tree;
    }

    /**
     * action 判断用户是否有此权限
     * @param $role_id int
     * @param $menu_id int
     * @param $accessList array
     * @return true|false
     */
    public static function is_checked($role_id,$menu_id,$accessList) {
        $nodeTemp = array('role_id' =>$role_id,'menu_id' =>$menu_id);
        $isExist = in_array($nodeTemp, $accessList);
        if($isExist){
            return true;
        } else {
            return false;
        }
    }

    /**
     * action 设置权限
     * @param $role_id int
     * @param $menu_list array
     * @return true|false
     */
    public static function setPermission($role_id,$menu_list)
    {
        $data=array();
        AdminAccess::where('role_id',$role_id)->delete();
        $menu=AdminMenu::where('status_at',1)->orderBy('sort', 'asc')->get();
        foreach($menu_list as $k => $v){
            $data[$k]['menu_id']=$v;
            $data[$k]['role_id'] = $role_id;
        }
        $res=AdminAccess::insert($data);
        return $res;
    }


    /**
     * action 面包屑
     * @param $action string
     * @return array
     */
    public static function breadCrumb($action)
    {
        $dsta=[];
        $route_info=AdminMenu::where('action',$action)->first()->toArray();
        $bread=AdminMenu::where('status_at',1)->where('id',$route_info->pid)->select('id','name','uri')->first()->toArray();
        $data['route_info']=$route_info;
        $data['bread']=$bread;
        return $data;
    }

    /**
     * 校验用户名称是否重复
     * @param $full_name string
     * @param $admin_id int
     * @return mixed
     */
    public static function uniqueUserName($full_name,$admin_id)
    {
        return Admin::where('id','<>',$admin_id)->where('full_name',$full_name)->first();

    }

    /**
     * 校验角色名称是否重复
     * @param $name string
     * @param $admin_id int
     * @return mixed
     */
    public static function uniqueRoleName($name,$admin_id)
    {
        return AdminRole::where('id','<>',$admin_id)->where('name',$name)->first();

    }

    /**
     * 校验旧密码
     * @param $old_password string
     * @param $admin_id int
     * @return mixed
     */
    public static function verifyPassword($old_password,$admin_id)
    {
        return Admin::where('password',myEncrypt($old_password))->where('id',$admin_id)->first();

    }

    /**
     * 修改
     * @param $password string
     * @param $admin_id int
     * @return mixed
     */
    public static function updateUserPassword($password,$admin_id)
    {
        return Admin::where('id',$admin_id)->update(['password'=>$password]);

    }

    /**
     * 创建菜单
     * @param $data array
     * @return mixed
     */
    public static function addMenu($data)
    {
        return AdminMenu::create($data);
    }

    /**
     * 修改菜单
     * @param $data array
     * @param $menu_id int
     * @return mixed
     */
    public static function updateMenu(array $data,$menu_id=0)
    {
        unset($data['menu_id']);
        return $admin = AdminMenu::where('id', $menu_id)->update($data);
    }

    /**
     * 校验菜单名称是否重复
     * @param $name string
     * @param $menu_id int
     * @return mixed
     */
    public static function uniqueMenuName($name,$menu_id)
    {
        return AdminMenu::where('id','<>',$menu_id)->where('name',$name)->first();

    }

    /**
     * 校验操作URL是否重复
     * @param $url string
     * @param $menu_id int
     * @return mixed
     */
    public static function uniqueMenuUrl($url,$menu_id)
    {
        return AdminMenu::where('id','<>',$menu_id)->where('url',$url)->first();

    }

    /**
     * 通过ID搜索菜单
     * @param $id
     * @return array
     */
    public static function getMenuById($id)
    {
        return AdminMenu::where('id',$id)->first()->toArray();
    }


    /**
     * 搜索菜单
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function searchMenu($where,$page_size)
    {

        $query=AdminMenu::select('*');
        //菜单名称
        if(isset($where['name']))
        {
            $query->where('name','like','%'.$where['name'].'%');
            unset($where['name']);
        }
        return $query->where($where)->paginate($page_size);
    }





}