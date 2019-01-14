<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccountLog extends Model
{
    const TYPE = array(
        'login'               =>  0,
        'change_password'     =>  1,
        'shopping'            =>  2,
        'pricing'             =>  3,
    );

    const TYPE_DESCRIBE = array(
        0     =>      '登陆系统',
        1     =>      '修改密码',
        2     =>      '购买商品',
        3     =>      '商品报价'
    );
}
