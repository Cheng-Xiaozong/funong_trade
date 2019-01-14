<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AccountBusiness extends Model
{
    //企业类型
    const TYPE=[
        'personal'=>0,
        'enterprise'=>1,
    ];

    const TYPE__DESCRIBE=[
        0=>'个人',
        1=>'企业',
    ];

    //审核状态
    const REVIEW_STATUS = array(
        'unupload'  =>  -1,
        'wating'    =>  0,
        'passed'    =>  1,
        'failed'    =>  2,
        'expired'   =>  3
    );

    const REVIEW_STATUS_DESCRIBE = array(
        -1      =>  '资料待上传',
        0       =>  '待审核',
        1       =>  '审核通过',
        2       =>  '审核拒绝',
        3       =>  '已过期',
    );

    //所属行业
    const INDUSTRY_DESCRIBE = array(
        0    =>   "汽车用品",
        1    =>   "手机数码",
        2    =>   "食品酒水饮料",
        3    =>   "电子机电",
        4    =>   "家用电器",
        5    =>   "个护化妆品",
        6    =>   "钟表珠宝",
        7    =>   "家具家装",
        8    =>   "服装鞋帽箱包",
        9    =>   "其他行业",
        10   =>   "日用百货",
        11   =>   "母婴用品",
        12   =>   "生鲜农贸",
        13   =>   "餐饮连锁",
        14   =>   "医药行业"
            );


    protected $fillable = [
        'id',
        'account_id',
        'type',
        'name',
        'contact_name',
        'contact_phone',
        'contact_telephone',
        'legal_person',
        'legal_id_positive',
        'legal_id_reverse',
        'legal_cn_id',
        'business_license',
        'id_expiry_time',
        'license_expiry_time',
        'review_log',
        'review_status',
        'review_detail',
        'company_logo',
        'industry_id',
        'province',
        'city',
        'county',
        'address',
        'address_details',
        'telephone',
        'postcode',
        'fax',
        'company_website',
        'wechat',
        'wechat_info',
        'qq',
        'email',
        'registered_capital',
        'tax_number',
        'invoice',
        'foreign_contacts',
        'hotline',
        'remarks',
        'lng',
        'lat',
        'attributes'
    ];

    /**
     * 关联账户
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function Account()
    {
        return $this->belongsTo('App\Account');
    }


    /**
     * 关联卖家(企业信息ID)
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function AccountSeller()
    {
        return $this->hasOne('App\AccountSeller');
    }

    /**
     * 关联买家(企业信息ID)
     * @return \Illuminate\Database\Eloquent\Relations\hasOne
     */
    public function AccountBuyer()
    {
        return $this->hasOne('App\AccountBuyer');
    }


    /**
     * 关联商品
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function Goods()
    {
        return $this->hasMany('App\Goods');
    }


    /**
     * 管类员工表
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function accountEmployee()
    {
        return $this->hasMany('App\AccountEmployee');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function Orders()
    {
        return $this->hasMany('App\Orders','id','buyer_business_id');
    }

}
