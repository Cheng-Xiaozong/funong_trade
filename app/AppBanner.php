<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppBanner extends Model
{
    const PAGE_NUM = 6;
    const STATUS_ENABLE = 1;//显示
    const STATUS_DISABLE = 0;//不显示
    
    const TYPE = [
        'INDEX' => 1,
        'FINANCING' => 2,
        'INSURANCE' => 3,
        'START' => 4,
    ];
    
    const TYPE_MEAN = [
        '1' => '首页',
        '2' => '融资',
        '3' => '保险',
        '4' => '启动页',
    ];
    
    const ACTION_TYPE = [
        "0" => '跳转到报价',
        "1" => '跳转到h5',
        '2' => '跳转到第三方链接',
    ];
    
    const ACTION_TYPE_TRANSLATION = [
        'goods' => 0,
        'h5' => 1,
        'other' => 2,
    ];
    
    protected $fillable = [
        'id',
        'img_path',
        'link',
        'describe',
        'sort',
        'link_id',
        'status',
        'type',
        'action_type',
        'content',
        'goods_id',
    ];
    
    
    public function getType($val)
    {
        return self::TYPE_MEAN[$val];
    }
    
    public function getACTIONType($val)
    {
        return self::ACTION_TYPE[$val];
    }
}
