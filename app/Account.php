<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{

    /*
     * 账户状态
     */
    const STATUS = array(
        'enable'     =>      1,
        'disable'    =>      0
    );

   /*
    * 账户状态描述
    */
    const STATUS_DESCRIBE = array(
        0   =>    '禁用',
        1   =>    '启用',
    );

    /*
     * 账户状态
     */
    const ACCOUNT_TYPE = array(
        'buyer'     =>      0,
        'seller'    =>      1
    );

    //账户类描述
    const ACCOUNT_TYPE_DESCRIBE = array(
        0     =>      '买家',
        1     =>      '卖家'
    );

    //注册状态
    const REGISTER_STATUS = array(
        'unregistered ' =>      -1,
        'mobile'        =>      0,
        'identity'      =>      1,
        'identity_type' =>      2,
        'password'      =>      3,
        'inforamtion'   =>      4
    );


    const REGISTER_STATUS_DESCRIBE = array(
        -1     =>     '未注册',
        0     =>      '已绑定手机号',
        1     =>      '已选择身份',
        2     =>      '已选择身份类型',
        3     =>      '已填写密码',
        4     =>      '已完善企业/个人信息'
    );


    /*
     * 注册来源
     */
    const SOURCE = array(
        'wechat'     =>      1,
        'android'    =>      2
    );

    /*
     * 注册来源描述
     */
    const SOURCE_DESCRIBE = array(
        1   =>    '微信端',
        2   =>    '安卓端',
    );

    /*
     * 允许批量赋值字段
     */
    protected $fillable = [
        'id',
        'account_number',
        'phone',
        'password',
        'account_type',
        'status',
        'register_status',
        'role',
        'nickname',
        'openid',
        'public_openid',
        'public_appid',
        'wechat_info',
        'android_appid',
        'source',
        'unionid'
    ];

    /*
     * 保护的属性
     */
    protected $hidden=[
        'password'
    ];

    /**
     * 关联企业信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function AccountInfo()
    {
        return $this->hasOne('App\AccountBusiness');
    }


    /**
     * 关联提货地址
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function GoodsDeliveryAddress()
    {
        return $this->hasMany('App\GoodsDeliveryAddress','id','account_id');
    }


    /**
     * 关联卖家
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function AccountSeller()
    {
        return $this->hasOne('App\AccountSeller');
    }


    /**
     * 关联买家
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function AccountBuyer()
    {
        return $this->hasOne('App\AccountBuyer');
    }

    /**
     * 关联员工表
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function accountEmployee()
    {
        return $this->hasOne('App\AccountEmployee');
    }

    /**
     * 注册状态
     * @param $key
     * @return mixed|string
     */
    public static function registerStatus($key=null)
    {
        if(is_null($key))  return self::REGISTER_STATUS_DESCRIBE;
        return array_key_exists($key,self::REGISTER_STATUS_DESCRIBE) ? self::REGISTER_STATUS_DESCRIBE[$key] : "未知";
    }

    /**
     * 账户类型
     * @param $key
     * @return mixed|string
     */
    public static function accountType($key=null)
    {
        if(is_null($key))  return self::ACCOUNT_TYPE_DESCRIBE;
        return array_key_exists($key,self::ACCOUNT_TYPE_DESCRIBE) ? self::ACCOUNT_TYPE_DESCRIBE[$key] : "未知";
    }

    /**
     * 查询是否注册完成
     * @param $key
     * @return string
     */
    public static function isComplete($key=null)
    {
        return $key>=self::REGISTER_STATUS['inforamtion'] ? '已完成' : '未完成';
    }

    /**
     * 账户状态
     * @param $key
     * @return mixed|string
     */
    public static function status($key=null)
    {
        if(is_null($key))  return self::STATUS_DESCRIBE;
        return array_key_exists($key,self::STATUS_DESCRIBE) ? self::STATUS_DESCRIBE[$key] : "未知";
    }


}
