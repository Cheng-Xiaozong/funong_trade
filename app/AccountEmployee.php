<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccountEmployee extends Model
{

    /**
     * 关联账户表
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Account');
    }


    /**
     * 关联企业表
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function accountBusiness()
    {
        return $this->belongsTo('App\AccountBusiness');
    }
}
