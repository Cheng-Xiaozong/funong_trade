<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountSellersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_sellers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('account_id')->comment('账户ID');
            $table->integer('account_business_id')->comment('企业信息ID');
            $table->tinyInteger('release_type')->default(0)->comment('商品审核方式 默认为平台审核 0平台审核 1系统审核');
            $table->tinyInteger('quote_type')->default(0)->comment('报价审核方式 默认为平台审核 0平台审核 1系统审核');
            $table->integer('account_point')->comment('账户积分');
            $table->string('account_level')->comment('账户等级');
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
        Schema::drop('account_sellers');
    }
}
