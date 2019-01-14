<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGoodsCategoryTradesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('goods_category_trades', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('category_id');
            $table->string('trading_day')->comment('交易日');
            $table->timestamp('start_time')->comment('开始日期')->nullable();
            $table->timestamp('end_time')->comment('结束日期')->nullable();
            $table->string('exclude_time')->comment('排除日期 json字符串[2015-12-22,2017-12-20]')->nullable();
            //[{"start time":"09:00:00","end time":"12:00:00"},]
            $table->string('time_slot')->comment('时间段 json字符串');
            $table->tinyInteger('status')->default(1)->comment('默认状态禁用,0-禁用, 1-启用 2-过期');
            $table->index('category_id');
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
        Schema::drop('goods_category_trades');
    }
}
