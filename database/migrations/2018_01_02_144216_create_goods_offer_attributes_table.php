<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGoodsOfferAttributesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('goods_offer_attributes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('pattern_id');
            $table->string('name')->comment('属性名');
            $table->string('sort')->comment('排序值');
            $table->string('english_name')->comment('英文名');
            $table->string('avaliable_value')->comment('可选值 json字符串保存')->nullable();
            $table->tinyInteger('is_necessary')->default(0)->comment('是否必填,0-非必填, 1-必填');
            $table->string('default_value')->comment('默认值')->nullable();
            $table->string('describe')->comment('属性描述')->nullable();
            $table->string('type')->comment('数字,字符串,浮点数等...');
            $table->index('pattern_id');
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
        Schema::drop('goods_offer_attributes');
    }
}
