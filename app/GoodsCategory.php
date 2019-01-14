<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GoodsCategory extends Model
{
    /*
     * 商品品类状态
     */
    const STATUS = array(
        'enable'     =>      1,
        'disable'    =>      0
    );

    /*
     * 商品品类状态描述
     */
    const STATUS_DESCRIBE = array(
        '1'   =>    '启用',
        '0'   =>    '禁用'
    );
    //是否允许上传图片
    const IS_UPLOAD_IMAGE = array(
        'enable'     =>      1,
        'disable'    =>      0
    );
    //是否允许上传图片描述
    const IS_UPLOAD_IMAGE_DESCRIBE = array(
        '1'   =>    '启用',
        '0'   =>    '禁用'
    );
    //是否允许上传视频
    const IS_UPLOAD_VEDIO = array(
        'enable'     =>      1,
        'disable'    =>      0
    );
    //是否允许上传视频描述
    const IS_UPLOAD_VEDIO_DESCRIBE = array(
        '1'   =>    '启用',
        '0'   =>    '禁用'
    );

    /*
     * 商品品类名称
     */
    const NAME = array(
        'soybean_meal'  =>  '豆粕',
        'corn'          =>  '玉米',
    );

    /*
     * 商品属性字段
     */
    const GOODS_ATTR = array(
        'brand'            =>  'brand',
        'product_area'     =>  'product_area',
        'protein'          =>  'protein',
        'unit'             =>  'unit',
    );

    /*
     * 商品属性字段描述
     */
    const GOODS_ATTR_DESCRIBE = array(
        'brand'            =>  '品牌',
        'product_area'     =>  '产地',
        'protein'          =>  '蛋白含量',
        'unit'             =>  '規格',
    );

    //赋值字段
    protected $fillable = [
        'name',
        'code',
        'offer_type',
        'status',
        'default_image',
        'is_upload_image',
        'is_upload_vedio'
    ];

    /**
     * 关联商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function Goods()
    {
        return $this->hasMany('App\Goods','category_id');
    }

    /**
     * 关联品类属性
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function GoodsCategoryAttribute()
    {
        return $this->hasMany('App\GoodsCategoryAttribute','category_id');
    }

    /**
     * 关联交易时间
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function GoodsCategoryTrade()
    {
        return $this->hasOne('App\GoodsCategoryTrade','category_id');
    }
}
