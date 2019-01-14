<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGoodsCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('goods_categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->comment('品类名称');
            $table->string('code')->comment('品类编号');
            $table->string('offer_type')->comment('报价模式ID 多种报价模式用,隔开');
            $table->tinyInteger('status')->default(1)->comment('状态,0-禁用, 1-启用');
            $table->string('default_image')->comment('品类图片');
            $table->tinyInteger('is_upload_image')->default(0)->comment('是否允许上传图片,0-否, 1-是');
            $table->tinyInteger('is_upload_vedio')->default(0)->comment('是否允许上传视频,0-否, 1-是');
            $table->index('name');
            $table->index('id');
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
        Schema::drop('goods_categories');
    }
}
