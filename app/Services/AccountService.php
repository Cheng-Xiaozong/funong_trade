<?php
/**
 * Created by PhpStorm.
 * User: cheney
 * Date: 2017/8/24
 * Time: 11:12
 */
namespace App\Services;


use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Token;
use App\Account;
use App\AccountBusiness;
use App\AccountLog;
use App\AccountBuyer;
use App\AccountSeller;
use App\Temp;


class AccountService
{


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
     * @param $account
     * @return mixed
     */
    public static function createToken($account)
    {
        return JWTAuth::fromUser($account);
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
        $token = new Token($token);
        $manager = JWTAuth::manager();
        return $manager->invalidate($token);
    }

    /**
     * 根据手机号获取用户
     * @param $phone
     * @return mixed
     */
    public static function getUserByPhone($phone)
    {
        return Account::where('phone', $phone)->first();
    }


    /**
     * 根据账号获取用户
     * @param $account_number
     * @return mixed
     */
    public static function getUserByNumber($account_number)
    {
        return Account::where('account_number', $account_number)->first();
    }


    /**
     * 根据账号ID获取买家
     * @param $account_id
     * @return mixed
     */
    public static function getBuyerByAccountId($account_id)
    {
        return AccountBuyer::where('account_id', $account_id)->first();
    }

    /**
     * 根据账号ID获取卖家
     * @param $account_id
     * @return mixed
     */
    public static function getSellerByAccountId($account_id)
    {
        return AccountSeller::where('account_id', $account_id)->first();
    }


    /**
     * 通过id查找卖家
     * @param $id
     * @return mixed
     */
    public static function getSellerByid($id)
    {
        return AccountSeller::find($id);
    }


    /**
     * 获取多个卖家
     * @param $ids
     * @return mixed
     */
    public static function getSellerByids($ids)
    {
        return AccountSeller::whereIn('id',$ids)->get();
    }


    /**
     * 根据手机号获取用户列表
     * @param $phone
     * @return mixed
     */
    public static function getAccountsByPhone($phone)
    {
        return Account::where('phone', $phone)->get();
    }

    /**
     * 根据账号获取用户列表
     * @param $account_number
     * @return mixed
     */
    public static function getAccountsByNumber($account_number)
    {
        return Account::where('account_number', $account_number)->get();
    }

    /**
     * 根据openid获取用户
     * @param $openid
     * @return mixed
     */
    public static function getUserByOpenidAndAppId($openid,$appid)
    {
        return Account::where('openid',$openid)
                      ->where('android_appid',$appid)
                      ->first();
    }


    /**
     * 根据openid获取用户
     * @param $openid
     * @return mixed
     */
    public static function getUserByPublicOpenidandAppid($openid,$appid)
    {
        return Account::where('public_openid',$openid)
                      ->where('public_appid',$appid)
                      ->first();
    }


    /**
     * 通过unionid获取用户
     * @param $unionid
     * @return mixed
     */
    public static function getUserByUnionid($unionid)
    {
        return Account::where('unionid',$unionid)->first();
    }


    /**
     * 登录
     * @param $account_number
     * @return mixed
     */
    public static function loginAccount($account_number)
    {
        return Account::where('account_number',$account_number)
                      ->orwhere('phone',$account_number)
                      ->first();
    }


    /**
     * 登录
     * @param $phone
     * @return mixed
     */
    public static function loginAccountByPhone($phone)
    {
        return Account::where('phone',$phone)->first();
    }


    /**
     * 重设密码
     * @param $phone
     * @param $password
     * @return mixed
     */
    public static function forgetPassword($phone, $password)
    {
        return Account::where('phone',$phone)->update(array('password'=>$password));
    }


    /**
     * 创建账户
     * @param $data
     * @return object
     */
    public static function createAccount($data)
    {
        return Account::create($data);
    }


    /**
     * 通过手机号更新账户
     * @param $phone
     * @param $data
     * @return mixed
     */
    public static function updateAccountByPhone($phone, $data)
    {
        return Account::where('phone', $phone)->update($data);
    }


    /**
     * 通过ID更新账户
     * @param $id
     * @param $data
     * @return int
     */
    public static function updateAccountById($id, $data)
    {
        return  Account::where('id', $id)->update($data);

    }

    /**
     * 添加公司信息
     * @param $data
     * @return object
     */
    public static function createAccountInfo($data)
    {
        return AccountBusiness::create($data);
    }

    /**
     * 添加买家信息
     * @param $data
     * @return object
     */
    public static function createByuer($data)
    {
        return AccountBuyer::create($data);
    }


    /**
     * 添加卖家信息
     * @param $data
     * @return object
     */
    public static function createSeller($data)
    {
        return AccountSeller::create($data);
    }


    /**
     * 通过账户id更新企业
     * @param $account_id
     * @param $data
     * @return bool
     */
    public static function updateAccountInfoByAccountId($account_id, $data)
    {
        return  AccountBusiness::where('account_id',$account_id)->update($data);
    }


    /**
     * 通过账户id更新买家
     * @param $account_id
     * @param $data
     * @return bool
     */
    public static function updateBuyerByAccountId($account_id, $data)
    {
        return  AccountBuyer::where('account_id',$account_id)->update($data);
    }

    /**
     * 通过账户id更新卖家
     * @param $account_id
     * @param $data
     * @return bool
     */
    public static function updateSellerByAccountId($account_id, $data)
    {
        return  AccountSeller::where('account_id',$account_id)->update($data);
    }


    /**
     * 通过id更新公司信息
     * @param $id
     * @param $data
     * @return bool
     */
    public static function updateAccountInfoById($id, $data)
    {

        return  AccountBusiness::where('id',$id)->update($data);
    }


    /**
     * 日志
     * @param $data
     * @return object
     */
    public static function createAccountLog($data)
    {
        return AccountLog::create($data);
    }


    /**
     * 获取全部账户
     * @param $page_size
     * @return mixed
     */
    public static function getAllAccount($page_size)
    {
        return Account::orderBy('id', 'desc')->paginate($page_size);
    }


    /**
     * 获取所有账户
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getAllAccounts()
    {
        return Account::all();
    }


    /**
     *  获取卖家买家账户
     * @param $where
     * @return array
     */
    public static function getAllAccountApi($where,$page_size)
    {
        $query = AccountBusiness::select(['account_businesses.id', 'name', 'type', 'review_status', 'contact_name', 'address_details', 'industry_id', 'contact_phone', 'account_sellers.id as seller_id']);


        if($where['action']!='all')
        {
            $query->where('review_status',AccountBusiness::REVIEW_STATUS[$where['action']]);
        }
        if($where['type']=="seller")
        {
            $query = $query->leftJoin('account_sellers', 'account_businesses.id', '=', 'account_sellers.account_business_id');
            if (isset($where['name'])) {
                $query->where('account_businesses.name', 'like', '%' . $where['name'] . '%');
            }
            $query=$query->where('account_sellers.id','!=',0);
        }



//        if($where['type']=="seller")
//        {
//            $query->with('AccountSeller');
//        }
//
//        if($where['type']=="buyer")
//        {
//            $query->with('AccountBuyer');
//        }
//
//        if($where['type']=="all")
//        {
//            $query->with('AccountSeller')->with('AccountBuyer');
//        }


        return $query->paginate($page_size);
    }


    /**
     * 获取全部账户
     * @param $page_size
     * @return mixed
     */
    public static function getAllBusiness($page_size)
    {
        return AccountBusiness::orderBy('id', 'desc')->paginate($page_size);
    }


    /**
     * 账户启用禁用
     * @param $id
     * @return mixed
     */
    public static function editAccountStatus($id)
    {
        $account=Account::find($id);
        if($account){
            if($account->status==Account::STATUS['enable'])
            {
                $account->status=Account::STATUS['disable'];
            }else{
                $account->status=Account::STATUS['enable'];
            }
            return $account->save();
        }
        return false;
    }


    /**
     * 根据ID获取账户
     * @param $account_id
     * @return mixed
     */
    public static function getAccountById($account_id)
    {
        return Account::find($account_id);
    }


    /**
     * 获取账户
     * @param $ids
     * @return mixed
     */
    public static function getAccountsByIds($ids)
    {
        return Account::whereIn('id',$ids)->get();
    }


    /**
     * 根据ID获取企业
     * @param $business_id
     * @return mixed
     */
    public static function getBusinessById($business_id)
    {
        return AccountBusiness::find($business_id);
    }


    /**
     * 根据条件搜索企业
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function searchBusiness($where,$page_size)
    {
        $query=AccountBusiness::select('*')->orderBy('id', 'desc');
        if(isset($where['name']))
        {
            $query->where('name','like','%'.$where['name'].'%');
            unset($where['name']);
        }

        if(isset($where['contact_phone']))
        {
            $query->where('contact_phone','like','%'.$where['contact_phone'].'%');
            unset($where['contact_phone']);
        }
        if(!empty($where)){
            return $query->where($where)->paginate($page_size);
        }else{
            return $query->paginate($page_size);
        }
    }


    /**
     * 根据条件搜索账户
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function searchAccount($where,$page_size)
    {
        $query=Account::select('*')->orderBy('id', 'desc');
        //验证账户身份（超管|员工）
        if(isset($where['role']))
        {
            if($where['role']==0)
            {
                $query->where('role',0);
            }else{
                $query->where('role','!=',0);
            }
            unset($where['role']);
        }

        //帐号模糊查询
        if(isset($where['account_number']))
        {
            $query->where('account_number','like','%'.$where['account_number'].'%');
            unset($where['account_number']);
        }

        if(isset($where['phone']))
        {
            $query->where('phone','like','%'.$where['phone'].'%');
            unset($where['phone']);
        }

        if(isset($where['nickname']))
        {
            $query->where('nickname','like','%'.$where['nickname'].'%');
            unset($where['nickname']);
        }

        return $query->where($where)->paginate($page_size);
    }


    /**
     * 获取账户字段描述
     * @return mixed
     */
    public static function accountFieldDescribe()
    {
        $data['status']=Account::STATUS_DESCRIBE;
        $data['register_status']=Account::REGISTER_STATUS_DESCRIBE;
        $data['account_type']=Account::ACCOUNT_TYPE_DESCRIBE;
        return $data;
    }


    /**
     * 获取账户字段描述
     * @return mixed
     */
    public static function businessFieldDescribe()
    {
        $data['review_status']=AccountBusiness::REVIEW_STATUS_DESCRIBE;
        $data['type']=AccountBusiness::TYPE__DESCRIBE;
        $data['review_status']=AccountBusiness::REVIEW_STATUS_DESCRIBE;
        $data['industry_id']=AccountBusiness::INDUSTRY_DESCRIBE;
        return $data;
    }

    /**
     * 买家卖家列表对外接口
     * @return mixed
     */
    public static function businessListFieldDescribe()
    {
        $data['review_status']=AccountBusiness::REVIEW_STATUS_DESCRIBE;
        $data['type']=AccountBusiness::TYPE__DESCRIBE;
        $data['review_status']=AccountBusiness::REVIEW_STATUS_DESCRIBE;
        $data['industry_id']=AccountBusiness::INDUSTRY_DESCRIBE;
        $data['account_seller']['release_type']=AccountSeller::RELEASE_TYPE_DESCRIBE;
        $data['account_seller']['quote_type']=AccountSeller::RELEASE_TYPE_DESCRIBE;
        return $data;
    }


    /**
     * 获取账户详情字段描述
     * @return mixed
     */
    public static function accountDetailsFieldDescribe()
    {
        $data['status']=Account::STATUS_DESCRIBE;
        $data['register_status']=Account::REGISTER_STATUS_DESCRIBE;
        $data['account_type']=Account::ACCOUNT_TYPE_DESCRIBE;
        $data['account_info']['type']=AccountBusiness::TYPE__DESCRIBE;
        $data['account_info']['review_status']=AccountBusiness::REVIEW_STATUS_DESCRIBE;
        $data['account_info']['industry_id']=AccountBusiness::INDUSTRY_DESCRIBE;
        $data['account_seller']['release_type']=AccountSeller::RELEASE_TYPE_DESCRIBE;
        $data['account_seller']['quote_type']=AccountSeller::RELEASE_TYPE_DESCRIBE;
        return $data;
    }


    /**
     * 获取企业详情字段描述
     * @return mixed
     */
    public static function businessDetailsFieldDescribe()
    {
        $data['type']=AccountBusiness::TYPE__DESCRIBE;
        $data['review_status']=AccountBusiness::REVIEW_STATUS_DESCRIBE;
        $data['industry_id']=AccountBusiness::INDUSTRY_DESCRIBE;
        $data['account']['status']=Account::STATUS_DESCRIBE;
        $data['account']['register_status']=Account::REGISTER_STATUS_DESCRIBE;
        $data['account']['account_type']=Account::ACCOUNT_TYPE_DESCRIBE;
        return $data;
    }


    /**
     * 通过account_id获取企业信息
     * @param $accunt_id
     * @return mixed
     */
    public static function getAccountBusinessByAccountId($accunt_id)
    {
        return AccountBusiness::where('account_id',$accunt_id)->first();
    }


    /**
     * 通过id获取卖家
     * @param $id
     * @return mixed
     */
    public static function getAccountSellerById($id)
    {
        return AccountSeller::find($id);
    }


    /**
     * 通过id获取卖家
     * @param $id
     * @return mixed
     */
    public static function getAccountSellerByIds($id,$page_size)
    {
        return AccountSeller::whereIn('id',$id)->paginate($page_size);
    }


    /**
     * @param $id
     * @param $where
     * @param $page_size
     * @return mixed
     */
    public static function searchAccountSeller($id, $where, $page_size)
    {

         $query = AccountSeller::select('*')->with(['Account' => function ($query) use($where) {

            $query->select('*');

            if(isset($where['account_number'])){
                $query->where('account_number','like','%'.$where['account_number'].'%');
            }

             if(isset($where['status'])){
                 $query->where('status',$where['status']);
             }

             if(isset($where['phone'])){
                 $query->where('phone','like','%'.$where['phone'].'%');
             }
        }]);

        $query->whereIn('id',$id);
        return $query->paginate($page_size);
    }


    /**
     * 通过id获取买家
     * @param $id
     * @return mixed
     */
    public static function getBuyerBuId($id)
    {
        return AccountBuyer::find($id);
    }


    /**
     * 通过$data更新用户
     * @param $unionid
     * @param $data
     * @return mixed
     */
    public static function updateUserByUnionid($unionid, $data)
    {
        return Account::where('unionid',$unionid)->update($data);
    }


    /**
     * 统计
     * @param $where
     * @return mixed
     */
    public static function countAccount($where)
    {

        $result['new_user'] = Account::where('created_at','>',$where['start'])->where('created_at','<',$where['end'])->count();
        $result['all_user'] = Account::all()->count();

        $result['new_wechat_user'] = Account::where('created_at','>',$where['start'])->where('created_at','<',$where['end'])->where('source',Account::SOURCE['wechat'])->count();
        $result['all_wechat_user'] = Account::where('source',Account::SOURCE['wechat'])->count();


        $result['new_android_user'] = Account::where('created_at','>',$where['start'])->where('created_at','<',$where['end'])->where('source',Account::SOURCE['android'])->count();
        $result['all_android_user'] = Account::where('source',Account::SOURCE['android'])->count();

        return $result;
    }


    /**
     * 通过筛选进行统计
     * @param $where
     * @return mixed
     */
    public static function filterCountAccount($where)
    {
        $result['all'] = Account::where('created_at','>',$where['start'])->where('created_at','<',$where['end'])->count();
        $result['wechat'] = Account::where('created_at','>',$where['start'])->where('created_at','<',$where['end'])->where('source',Account::SOURCE['wechat'])->count();
        $result['android'] = Account::where('created_at','>',$where['start'])->where('created_at','<',$where['end'])->where('source',Account::SOURCE['android'])->count();
        return $result;
    }


    /**
     * 计算当天总数
     * @param $time
     * @return mixed
     */
    public static function dailyAccountSum($time)
    {

        $result['all'] = Account::where('created_at','<=',$time)->count();
        $result['wechat'] = Account::where('created_at','<=',$time)->where('source',Account::SOURCE['android'])->count();
        $result['android'] = Account::where('created_at','<=',$time)->where('source',Account::SOURCE['android'])->count();
        return $result;
    }


    public static function getAllTemp()
    {
        return Temp::all();
    }


    public static function getAllSelfTemp()
    {
        return Temp::all();
    }


    public static function delSeller($id)
    {
        return AccountSeller::destroy($id);
    }


}