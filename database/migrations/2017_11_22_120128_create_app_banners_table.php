<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppBannersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('app_banners', function (Blueprint $table) {
            $table->increments('id');
            $table->string('img_path')->comment('广告图片');
            $table->string('link')->comment('跳转链接');
            $table->text('describe')->comment('图片描述');
            $table->integer('sort')->default(0)->comment('排序，越小越靠前');
            $table->integer('link_id')->comment('关联链接id');
            $table->text('content')->comment('富文本');
            $table->tinyInteger('status')->default(0)->comment('状态 1启用0禁用');
            $table->tinyInteger('type')->comment('显示位置');
            $table->tinyInteger('action_type')->default(0)->comment('行为');
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
        Schema::drop('app_banners');
    }
}
