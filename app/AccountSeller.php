<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccountSeller extends Model
{
    const RELEASE_TYPE = array(
        'platform '     =>  0,
        'system'        =>  1
    );

    const RELEASE_TYPE_DESCRIBE = array(
        0     =>    '平台审核',
        1     =>    '系统审核'
    );

    const QUOTE_TYPE = array(
        'platform '     =>  0,
        'system'        =>  1
    );

    const QUOTE_TYPE_DESCRIBE = array(
        0     =>    '平台审核',
        1     =>    '系统审核'
    );


    protected $fillable = [
        'account_id',
        'account_business_id',
        'review_status',
        'release_type',
        'quote_type',
        'account_point',
        'account_level',
    ];

    /**
     * 关联账户表
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
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
