<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccountBuyer extends Model
{
    protected $fillable = [
        'account_id',
        'account_business_id',
        'account_point',
        'account_level',
    ];

    public function Account()
    {
        return $this->belongsTo('App\Account');
    }

    /**
     * 关联企业信息
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function AccountBusiness()
    {
        return $this->belongsTo('App\AccountBusiness');
    }

}
