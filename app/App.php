<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class App extends Model
{
    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;
    const PAGE_NUM = 15;
    protected $fillable = [
        'name',
        'appid',
        'appsecret',
        'status',
        'remark'
    ];

    /**
     * 状态处理
     * @param $key
     * @return string
     */
    public function status($key)
    {
        $data[self::STATUS_ENABLE]='启用';
        $data[self::STATUS_DISABLE]='禁用';
        return array_key_exists($key,$data) ? $data[$key] : "未知";
    }
}
