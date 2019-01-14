<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Goods extends Model
{
    //商品状态描述
    const STATUS = array(
        'disable'    =>      0,
        'enable'     =>      1
    );
    const STATUS_DESCRIBE = array(
        0   =>    '禁用',
        1   =>    '启用',
    );

    //商品审核状态
    const REVIEW_STATUS = array(
        'waiting'   =>  0,
        'passed'    =>  1,
        'failed'    =>  2
    );

    const REVIEW_STATUS_DESCRIBE = array(
        0       =>  '待审核',
        1       =>  '通过',
        2       =>  '拒绝'
    );

    /*
     * 允许批量赋值字段
     */
    protected $fillable = [
        'seller_id',
        'account_business_id',
        'account_employee_id',
        'category_id',
        'delivery_address_id',
        'category_code',
        'name',
        'short_name',
        'search_keywords',
        'code',
        'goods_attrs',
        'custom_attrs',
        'status',
        'review_status',
        'review_log',
        'review_details',
        'faces',
        'details',
        'vedios',
        'bar_code',
    ];


    /**
     * 关联报价
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function GoodsOffer()
    {
        return $this->hasMany('App\GoodsOffer');
    }


    /**
     * 关联企业
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function AccountBusiness()
    {
        return $this->belongsTo('App\AccountBusiness');
    }


    /**
     * 关联商品品类
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function GoodsCategory()
    {
        return $this->belongsTo('App\GoodsCategory','category_id','id');
    }


    /**
     * 关联商品品类并且获取对应的报价模式
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function category()
    {
        return $this->hasOne('App\GoodsCategory','id','category_id');
    }
}
