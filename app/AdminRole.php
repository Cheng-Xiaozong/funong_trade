<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdminRole extends Model
{

    /*
    * ½ÇÉ«×´Ì¬
    */
    const STATUS = array(
        'enable'     =>      1,
        'disable'    =>      2
    );

    /*
    * ½ÇÉ«×´Ì¬ÃèÊö
    */
    const STATUS_DESCRIBE = array(
        1   =>    'ÆôÓÃ',
        2   =>    '½ûÓÃ',
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
