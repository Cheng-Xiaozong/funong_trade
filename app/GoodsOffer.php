<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Elasticquent\ElasticquentTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsOffer extends BaseModel
{
    use ElasticquentTrait;
    use SoftDeletes;

    const PAGE_NUM = 10;
    protected $dates = ['delete_at'];

    /*
     * 报价状态
     */
    const STATUS = array(
        'enable'     =>      1,
        'disable'    =>      0
    );


    /*
     * 报价状态描述
     */
    const STATUS_DESCRIBE = array(
        '1'   =>    '启用',
        '0'   =>    '禁用'
    );


    /*
     * 审核状态
     */
    const REVIEW_STATUS = array(
        'waiting'   =>  0,
        'passed'    =>  1,
        'failed'    =>  2
    );


    /*
     * 审核状态描述
     */
    const REVIEW_STATUS_DESCRIBE = array(
        0       =>  '等待审核',
        1       =>  '审核通过',
        2       =>  '审核失败'
    );


    /*
     * 报价类型
     */
    const TYPE = array(
        'waiting'   =>  0,
        'selling'   =>  1,
        'off_sell'  =>  2
    );


    /*
     * 报价类型描述
     */
    const TYPE_DESCRIBE = array(
        0       =>  '待审核',
        1       =>  '在售中',
        2       =>  '已下架'
    );


    /*
     * 允许批量赋值字段
     */
    protected $fillable = [
        'goods_id',
        'seller_id',
        'goods_name',
        'account_employee_id',
        'account_businesses_id',
        'offer_pattern_id',
        'delivery_address_id',
        'order_unit',
        'offer_info',
        'price',
        'describe',
        'offer_starttime',
        'offer_endtime',
        'delivery_starttime',
        'delivery_endtime',
        'stock',
        'single_number',
        'moq_number',
        'lock_number',
        'search_keywords',
        'offer_pattern_name',
        'brand_name',
        'category_name',
        'product_area',
        'status',
        'review_status',
        'review_details',
        'name',
        'province',
        'city',
        'address',
        'address_details',
        'lng',
        'lat',
        'review_log',
        'tag_ids'
    ];

    /**
     * 关联商品
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function Goods()
    {
        return $this->belongsTo('App\Goods');
    }

    /**
     * 关联报价类型
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function goodsOfferPattern()
    {
        return $this->belongsTo('App\GoodsOfferPattern','offer_pattern_id','id');
    }


    /**
     * 关联提货地址
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function GoodsDeliveryAddress()
    {
        return $this->belongsTo('App\GoodsDeliveryAddress','delivery_address_id','id');
    }


    /**
     * 关联订单表
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function order()
    {
        return $this->hasMany('App\Order');
    }

    /**
     * 关联提货地址
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function address()
    {
        return $this->hasOne('App\GoodsDeliveryAddress','id','delivery_address_id');
    }

    /**
     * 关联商品ID
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function good()
    {
        return $this->hasOne('App\Goods','id','goods_id');
    }


    /**
     * 关联商品模式
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function pattern()
    {
        return $this->hasOne('App\GoodsOfferPattern','id','offer_pattern_id');
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
