<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoodsDeliveryAddress extends BaseModel
{

    /*
     * 地址状态
     */
    const STATUS = array(
        'enable'     =>      1,
        'disable'    =>      0
    );

    /*
     * 地址状态描述
     */
    const STATUS_DESCRIBE = array(
        '1'   =>    '启用',
        '0'   =>    '禁用'
    );

    /*
     * 地址删除状态
     */
    const DELETE_STATUS = array(
        'enable'     =>      1,
        'disable'    =>      0
    );

    /*
     * 地址删除状态描述
     */
    const DELETE_STATUS_DESCRIBE = array(
        '1'   =>    '正常',
        '0'   =>    '删除'
    );

    /*
     * 允许批量赋值字段
     */
    protected $fillable = [
        'name',
        'province',
        'city',
        'county',
        'address',
        'address_details',
        'lng',
        'lat',
        'seller_id',
        'account_businesses_id',
        'account_employee_id',
    ];

    /**
     * 关联涨肚
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function Account()
    {
        return $this->belongsTo('App\Account','account_id','id');
    }


    /**
     * 关联报价
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function GoodsOffer()
    {
        return $this->hasMany('App\GoodsOffer','id','delivery_address_id');
    }

    /**
     * 关联省
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function province()
    {
        return $this->hasOne('App\AreaInfo','id','province');
    }

    /**
     * 关联市
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function city()
    {
        return $this->hasOne('App\AreaInfo','id','city');
    }

    /**
     * 关联卖家信息
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function businesses()
    {
        return $this->hasOne('App\AccountBusiness','id','account_businesses_id');
    }



}
