<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountBusinessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_businesses', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('account_id')->comment('账户ID');
            $table->tinyInteger('type')->default(0)->comment('0:个人 1:企业');
            $table->string('name')->comment('企业名称/个人姓名');
            $table->string('contact_name')->comment('联系人姓名');
            $table->char('contact_phone',11)->comment('联系手机');
            $table->char('contact_telephone')->comment('联系电话');
            $table->string('legal_person')->comment('法人姓名');
            $table->string('legal_id_positive')->comment('法人/个人身份证正面');
            $table->string('legal_cn_id')->comment('法人/个人身份证号');
            $table->string('legal_id_reverse')->comment('法人/个人身份证反面');
            $table->string('business_license')->comment('企业/个人营业执照');
            $table->timestamp('id_expiry_time')->comment('法人/个人身份-过期时间')->nullable();
            $table->timestamp('license_expiry_time')->comment('企业/个人营业执照-过期时间')->nullable();
            $table->text('review_log')->comment('审核日志 审核人 时间 审核详情');
            $table->tinyInteger('review_status')->default(-1)->comment('审核状态 -1未上传信息 0:待审核 1:通过 2:拒绝 3:过期');
            //{"法人/个人身份证正面"："通过/过期"，"法人/个人身份证正面"："不通过"，"企业/个人营业执照":"通过"}
            $table->text('review_detail')->comment('审核详情 json key-value');
            $table->string('company_logo')->comment('公司logo');
            $table->integer('industry_id')->comment('行业ID');
            $table->smallInteger('province')->comment('经营所在省');
            $table->smallInteger('city')->comment('经营所在市');
            $table->smallInteger('county')->comment('经营所在县');
            $table->string('address')->comment('详细地址');
            $table->string('address_details')->comment('完整地址');
            $table->char('telephone')->comment('电话');
            $table->char('postcode')->comment('邮编');
            $table->char('fax')->comment('传真');
            $table->string('company_website')->comment('公司网址');
            $table->string('wechat')->comment('微信');
            $table->char('qq')->comment('qq');
            $table->string('email');
            $table->decimal('registered_capital')->comment('注册资本');
            $table->string('tax_number')->comment('纳税人识别号');
            $table->string('invoice')->comment('发票抬头');
            $table->text('foreign_contacts')->comment('对外联系人 姓名 手机 QQ 邮箱 职务');
            $table->char('hotline',11)->comment('服务热线');
            $table->string('remarks');
            $table->string('attributes')->comment('自定义属性 （key-value-json字符串存储）');
            $table->string('lng')->comment('经度')->nullable();
            $table->string('lat')->comment('纬度')->nullable();
            $table->index('account_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('account_businesses');
    }
}
