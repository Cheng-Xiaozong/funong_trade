<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoodsOfferAttribute extends Model
{

    const IS_NECESSARY =array(
        'yes'   =>  1,
        'no'   =>  0
    );

    const CONTROL_TYPE = array(
        'input'     =>  1,
        'select'    =>  2,
        'input_select'    =>  3
    );

    const CONTROL_TYPE_DESCRIBE = array(
        1   =>  '输入框',
        2   =>  '选择框',
        3   =>  '输入框，提供选择项'
    );


    const IS_NECESSARY_DESCRIBE = array(
        0   =>  '非必填',
        1   =>  '必填',
    );


    const NAME = array(
        'DSM'  =>  '主力合约',
    );


    /*
     * 允许批量赋值字段
     */
    protected $fillable = [
        'pattern_id',
        'name',
        'describe',
        'sort',
        'english_name',
        'is_necessary',
        'avaliable_value',
        'default_value',
        'type',
        'control_type',
    ];
}
