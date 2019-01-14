<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoodsCategoryTrade extends Model
{
    //商品状态描述
    const STATUS = array(
        'disable'    =>      0,
        'enable'     =>      1,
        'overdue'     =>     2

    );
    const STATUS_DESCRIBE = array(
        0   =>    '禁用',
        1   =>    '启用',
        2   =>    '过期'
    );

    //赋值字段
    protected $fillable = [
        'category_id',
        'trading_day',
        'start_time',
        'end_time',
        'exclude_time',
        'time_slot',
        'status'
    ];

    /**
     * 关联品类
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function GoodsCategory()
    {
        return $this->belongsTo('App\GoodsCategory');
    }
}
