<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{

    const PAGE_NUM = 10;

    /*
     * 订单状态
     */
    const ORDER_STATUS = array(
        'waiting' => 0,
        'unfinished' => 1,
        'finished' => 2,
        'disable' => 3
    );

    /*
     * 订单状态描述
     */
    const ORDER_STATUS_DESCRIBE = array(
        0   =>  '待处理',
        1   =>  '未完成',
        2   =>  '已完成',
        3   =>  '作废'
    );

    /*
     * 操作状态
     */
    const OPERATION_STATUS = array(
        'unconfirm' =>     0,
        'draft'     =>     1,
        'waiting'   =>     2,
        'cashing'   =>     3,
        'running'   =>     4,
        'ending'    =>     5,
    );


    /*
     * 操作状态描述
     */
    const OPERATION_STATUS_DESCRIBE = array(
        0   =>    '未确认合同',
        1   =>    '草拟',
        2   =>    '待审核',
        3   =>    '待付保证金',
        4   =>    '执行中',
        5   =>    '已终结',
    );


    /*
     * 合同类型
     */
    const CONTRACT_TYPE = array(
        'basic'     =>     1,
        'pause'   =>       2,
        'cash'   =>     3,
        'point'   =>     4,
        'ending'    =>     5,
    );


    /*
     * 合同类型描述
     */
    const CONTRACT_TYPE_DESCRIBE = array(
        1   =>    '基差合同',
        2   =>    '暂定价合同',
        3   =>    '现货价合同',
        4   =>    '点价合同',
        5   =>    '结价合同',
    );

    /*
     * 允许批量赋值字段
     */
    protected $fillable = [
        'order_number',
        'goods_offer_id',
        'goods_id',
        'account_buyer_id',
        'account_seller_id',
        'account_employee_id',
        'buyer_business_id',
        'seller_name',
        'buyer_name',
        'offer_name',
        'order_unit',
        'price',
        'address_details',
        'lng',
        'lat',
        'delivery_starttime',
        'delivery_endtime',
        'offer_info',
        'goods_info',
        'num',
        'total_price',
        'discount_price',
        'image',
        'order_status',
        'operation_status',
        'goods_name',
        'category_name',
        'source',
        'id_card'
    ];

    /**
     * 关联报价
     */
    public function goodsOffer()
    {
        return $this->belongsTo('App\GoodsOffer')->withTrashed();
    }


    /**
     * 关联企业表
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function AccountBusiness()
    {
        return $this->belongsTo('App\AccountBusiness','buyer_business_id','id');
    }
}
