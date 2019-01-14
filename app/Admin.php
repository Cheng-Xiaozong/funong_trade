<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{

    protected $table = 'admin';

    protected $guarded = ['id'];

    /*
     * 用户状态
     */
    const STATUS = array(
        'enable'     =>      1,
        'disable'    =>      2
    );

    /*
    * 用户状态描述
    */
    const STATUS_DESCRIBE = array(
        1   =>    '启用',
        2   =>    '禁用',
    );


    /*
         * 允许批量赋值字段
         */
    protected $fillable = [
        'user_name',
        'full_name',
        'password',
        'role_id',
        'status_at',
        'image',

    ];
    protected $dates = ['deleted_at'];
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];


    /**
     * 用户角色
     */
    public function role()
    {
        return $this->belongsTo('App\AdminRole');
    }





}
