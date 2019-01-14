<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    const PAGE_NUM = 5;
    
    const STATUS = [
        'DISABLE' => 0,
        'ENABLE' => 1,
    ];
    
    const STATUS_TRANSLATION = [
        0 => '禁用',
        1 => '启用',
    ];
    
    const IS_UPDATE_ANNWAY_YES = 1;
    const IS_UPDATE_ANNWAY_NO = 0;
    
    const IS_UPDATE_ANYWAY_TRANSLATION = [
        0 => '否',
        1 => '是',
    ];
    
    protected $fillable = [
        'version_code',
        'version_name',
        'update_title',
        'app_name',
        'is_update_anyway',
        'status',
        'file_name',
        'update_info'
    ];


    /**
     * 返回当前状态
     * @param $val
     * @return mixed
     */
    public function getStatus($val)
    {
        return self::STATUS_TRANSLATION[$val];
    }
    
    /**
     * 是否强制更新
     * @param $val
     * @return mixed
     */
    public function isUpdateAnyway($val)
    {
        return self::IS_UPDATE_ANYWAY_TRANSLATION[$val];
    }
}
