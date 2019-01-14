<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGoodsOffersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('goods_offers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('goods_id');
            $table->integer('seller_id')->comment('卖家ID');
            $table->integer('account_businesses_id')->comment('企业ID');
            $table->integer('account_employee_id')->default(0)->comment('员工ID');
            $table->integer('offer_pattern_id')->comment('报价模式ID 只能在该商品的品类下查找拥有的报价模式');
            $table->integer('delivery_address_id')->comment('提货地址ID');
            $table->string('order_unit')->comment('定价单位 吨 车');
            $table->string('goods_name')->comment('商品全称（加索引）');
            $table->text('offer_info')->comment('报价信息 json字符串：报价模式的自定义属性扩展字段 根据不同的报价模式所填写的不同字段{key:value}')->nullable();
            $table->double('price')->comment('价格 地址ID 价格 json字符串不同地址区域,价格不同可以对部分地址报价');
            $table->string('describe')->comment('价格描述')->nullable();
            $table->string('extra')->comment('其他描述')->nullable();
            $table->string('search_keywords')->comment('商品搜索关键字')->nullable();
            $table->string('offer_pattern_name')->comment('报价模式名')->nullable();
            $table->string('brand_name')->comment('品牌名')->nullable();
            $table->string('category_name')->comment('分类名')->nullable();
            $table->string('product_area')->comment('产地')->nullable();
            $table->timestamp('offer_starttime')->comment('报价开始日期')->nullable();
            $table->timestamp('offer_endtime')->comment('报价结束日期')->nullable();
            $table->timestamp('delivery_starttime')->comment('提货开始日期')->nullable();
            $table->timestamp('delivery_endtime')->comment('提货结束日期')->nullable();
            $table->decimal('stock',9,4)->comment('总量 不限量 限制的数量')->nullable();
            $table->decimal('single_number',9,4)->comment('单个用户量 不限量 限制的数量')->nullable();
            $table->decimal('moq_number',9,4)->comment('每单量 不限量 最小量')->nullable();
            $table->decimal('lock_number',9,4)->comment('锁定量 用户下单就锁定相应量,取消订单解锁相应量,确定订单转为已销量')->nullable();
            $table->tinyInteger('status')->default(1)->comment('报价状态 由买家自行控制 0-禁用1-启用');
            $table->tinyInteger('review_status')->comment('审核状 待审核 通过 拒绝');
            $table->text('review_log')->comment('操作/审核日志 账户名称 账户类型-卖家-平台 操作时间 操作详情');
            $table->text('review_details')->comment('审核详情（当前最新审核结果）');
            $table->string('name')->comment('提货地址名称');
            $table->smallInteger('province')->comment('提货地址省');
            $table->smallInteger('city')->comment('提货地址市');
            $table->string('address')->comment('提货地址详细地址')->nullable();
            $table->string('address_details')->comment('提货地址完整地址');
            $table->string('lng')->comment('提货地址经度')->nullable();
            $table->string('lat')->comment('提货地址纬度')->nullable();
            $table->integer('deleted_at')->comment('删除状态')->nullable();
            $table->timestamps();
            $table->index('seller_id');
            $table->index('account_employee_id');
            $table->index('account_businesses_id');
            $table->index('goods_id');
            $table->index('goods_name');
            $table->index('search_keywords');
            $table->index('offer_pattern_name');
            $table->index('brand_name');
            $table->index('category_name');
            $table->index('product_area');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('goods_offers');
    }
}
