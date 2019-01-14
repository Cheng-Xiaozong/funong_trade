<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoodsCategoryAttribute extends Model
{
    const IS_NECESSARY =array(
        'yes'   =>  1,
        'no'   =>  0
    );

    const IS_NECESSARY_DESCRIBE =array(
        '1'   =>  '必填项',
        '0'   =>  '非必填'
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

    //赋值字段
    protected $fillable = [
        'category_id',
        'name',
        'describe',
        'sort',
        'english_name',
        'avaliable_value',
        'is_necessary',
        'default_value',
        'type',
        'control_type',
    ];

    /**
     * 关联平类属性
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function GoodsCategory()
    {
        return $this->belongsTo('App\GoodsCategory');
    }

}
