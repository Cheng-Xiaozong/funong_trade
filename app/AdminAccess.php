<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdminAccess extends Model
{

    protected $table = 'admin_access';

    protected $guarded = ['id'];

    protected $dates = ['deleted_at'];

}
