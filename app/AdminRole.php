<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdminRole extends Model
{

    /*
    * ��ɫ״̬
    */
    const STATUS = array(
        'enable'     =>      1,
        'disable'    =>      2
    );

    /*
    * ��ɫ״̬����
    */
    const STATUS_DESCRIBE = array(
        1   =>    '����',
        2   =>    '����',
    );

    protected $table = 'admin_role';

    protected $guarded = ['id'];

    protected $dates = ['deleted_at'];
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];



}
