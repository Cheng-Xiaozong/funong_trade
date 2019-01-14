<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoodsOfferPattern extends Model
{

    /*
     * 报价模式状态
     */
    const STATUS = array(
        'enable'     =>      1,
        'disable'    =>      0
    );

    /*
     * 报价模式状态描述
     */
    const STATUS_DESCRIBE = array(
        '1'   =>    '启用',
        '0'   =>    '禁用'
    );

    /*
     * 报价模式是否可以删除状态
     */
    const DELETED = array(
        'disable'     =>      1,
        'enable'    =>      0
    );

    /*
     * 报价模式是否可以删除状态
     */
    const DELETED_DESCRIBE = array(
        '1'   =>    '不可删除',
        '0'   =>    '可以删除'
    );



    /*
     * 报价方式
     */
    const OFFER_PATTERN = array(
        'spot_goods'    =>  '现货',
        'fixed_price'   =>  '一口价',
        'basis_price'   =>  '基差价',
    );

    /*
     * 报价方式状态
     */
    const OFFER_PATTERN_DESCRIBE= array(
        1    =>  '现货',
        2   =>  '一口价',
        3   =>  '基差价',
    );

    /*
     * 价格变动
     */
    const PRICE_TYPE = array(
        'change'    =>  1,
        'unchange'  =>  0
    );

    /*
     * 价格变动描述
     */
    const PRICE_TYPE_DESCRIBE = array(
        0       =>  '频繁变动',
        1       =>  '不频繁变动'
    );


    /*
     * 允许批量赋值字段
     */
    protected $fillable = [
        'name',
        'status'
    ];

    /**
     * 关联商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function GoodsOffer()
    {
        return $this->hasMany('App\GoodsOffer','id','offer_pattern_id');
    }


    /**
     * 关联报价模板
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attribute()
    {
        return $this->hasMany('App\GoodsOfferAttribute','pattern_id','id');
    }
}
