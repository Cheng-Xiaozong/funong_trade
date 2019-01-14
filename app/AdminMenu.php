<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdminMenu extends Model
{

    /*
    * 菜单状态
    */
    const STATUS = array(
        'enable'     =>      1,
        'disable'    =>      2
    );

    /*
    * 菜单状态描述
    */
    const STATUS_DESCRIBE = array(
        1   =>    '启用',
        2   =>    '禁用',
    );

    /*
    * 菜单等级状态
    */
    const LEVEL = array(
        'one'     =>      1,
        'two'    =>      2,
        'three'    =>      3
    );

    /*
    * 菜单等级状态描述
    */
    const LEVEL_DESCRIBE = array(
        1   =>    '一级(左侧菜单项)',
        2   =>    '二级(左侧菜单项)',
        3   =>    '三级(具体操作项)',
    );

    protected $table = 'admin_menu';

    protected $guarded = ['id'];
    /*
             * 允许批量赋值字段
             */
    protected $fillable = [
        'name',
        'url',
        'pid',
        'level',
        'icon',
        'status_at',

    ];
    protected $dates = ['deleted_at'];
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];


    /**
     * 关联菜单
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function menu()
    {
        return $this->hasOne('App\AdminMenu','pid','id');
    }


}
