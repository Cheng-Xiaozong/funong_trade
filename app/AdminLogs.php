<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdminLogs extends Model
{

    protected $table = 'admin_logs';

    /*
     * 允许批量赋值字段
     */
    protected $fillable = [
        'admin_id',
        'admin_name',
        'type',
        'log',
    ];


}
