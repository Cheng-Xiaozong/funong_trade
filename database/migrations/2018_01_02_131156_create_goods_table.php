<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGoodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('goods', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('seller_id')->default(0)->comment('经销商ID（加索引）');
            $table->integer('account_employee_id')->default(0)->comment('员工ID（加索引）');
            $table->integer('account_business_id')->comment('企业ID（加索引）');
            $table->integer('category_id')->comment('商品所属分类');
            $table->string('delivery_address_id')->comment('提货地址ID 多条用，隔开');
            $table->string('category_code')->comment('品类编号');
            $table->string('name')->comment('商品全称（加索引）');
            $table->string('short_name')->comment('商品简称');
            $table->string('search_keywords')->comment('商品搜索关键字');
            $table->char('code')->comment('商品编码');
            $table->text('goods_attrs')->comment('商品属性 json字符串 属性名称 英文名 属性值 排序值');
            $table->text('custom_attrs')->comment('自定义属性 json字符串 属性名称 属性值 排序值');
            $table->tinyInteger('status')->default(1)->comment('商品状态 0-禁用1-启用');
            $table->tinyInteger('review_status')->comment('审核状 待审核 通过 拒绝');  //卖家添加商品，由卖家的审核状态来定  如果卖家的审核状态为不需要审核就为通过
            $table->text('review_log')->comment('审核日志 ：（账户名称 审核时间 审核详情）');
            $table->text('review_details')->comment('审核详情（当前最新审核结果）');
            $table->string('faces')->comment('商品图片（用逗号分割，存字符串）');
            $table->text('details')->comment('商品描述（富文本）');
            $table->string('vedios')->comment('视频');
            $table->char('bar_code')->comment('条形码');
            $table->timestamps();
            $table->index('seller_id');
            $table->index('account_employee_id');
            $table->index('account_business_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('goods');
    }
}
