<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AreaInfo extends Model
{
    protected $fillable = [
        'code',
        'name',
        'pid',
        'level'
    ];

}
