<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('account_number')->comment('帐号')->nullable();
            $table->char('phone',11)->comment('手机号码');
            $table->string('password')->comment('密码')->nullable();
            $table->tinyInteger('account_type')->default(0)->comment('0:买家 1:卖家');
            $table->tinyInteger('status')->default(0)->comment('状态(1启用0禁用)');
            $table->tinyInteger('register_status')->default(0)->comment('注册状态(0已绑定手机号 1已选择身份 2已选择身份类型 3已填写密码 4已完善企业信息 5已绑定微信号)');
            $table->tinyInteger('role')->default(0)->comment('角色 默认注册用户为超级管理员，默认为0 非0的角色为员工帐号')->nullable();
            $table->string('nickname')->comment('昵称别称');
            $table->string('openid');
            $table->string('public_openid');
            $table->string('public_appid');
            $table->string('android_appid');
            $table->string('unionid');
            $table->tinyInteger('source')->default(2)->comment('注册来源')->nullable();
            $table->text('wechat_info')->comment('用户微信信息')->nullable();
            $table->index('account_number');
            $table->index('phone');
            $table->index('openid');
            $table->index('public_openid');
            $table->index('created_at');
            $table->index('source');
            $table->unique('account_number');
            $table->unique('phone');
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
        Schema::drop('accounts');
    }
}
