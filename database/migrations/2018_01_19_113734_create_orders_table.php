<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_number')->comment('订单编号');
            $table->integer('goods_offer_id')->comment('报价id');
            $table->integer('goods_id')->comment('商品id');
            $table->integer('account_buyer_id')->comment('买家ID');
            $table->integer('buyer_business_id')->comment('买家ID');
            $table->integer('account_employee_id')->default(0);
            $table->integer('account_seller_id')->comment('供应商ID');
            $table->string('seller_name')->comment('卖家名称');
            $table->string('buyer_name')->comment('买家名称');
            $table->string('goods_name')->comment('商品名称');
            $table->string('offer_name')->comment('报价名称');
            $table->string('category_name')->comment('品类名称');
            $table->string('order_unit')->comment('定价单位 吨 车');
            $table->decimal('price',9,2)->comment('单价');
            $table->string('address_details')->comment('提货地址完整地址');
            $table->string('lng')->comment('提货地址经度')->nullable();
            $table->string('lat')->comment('提货地址纬度')->nullable();
            $table->timestamp('delivery_starttime')->comment('提货开始日期');
            $table->timestamp('delivery_endtime')->comment('提货结束日期');
            $table->text('offer_info')->comment('报价信息 json字符串：报价模式的自定义属性扩展字段 根据不同的报价模式所填写的不同字段{key:value}')->nullable();
            $table->text('goods_info')->comment('商品信息 json字符串：报价模式的自定义属性扩展字段 根据不同的报价模式所填写的不同字段{key:value}')->nullable();
            $table->decimal('num',9,4)->comment('购买数量');
            $table->double('total_price')->comment('总价');
            $table->double('discount_price')->comment('优惠金额');
            $table->string('image');
            $table->tinyInteger('order_status')->default(0)->comment('订单状态（0待处理订单 1未完成 2已完成订单 3已作废订单）');
            $table->tinyInteger('operation_status')->default(0)->comment('操作状态（0合同未确认 1草拟合同 2待审核 3待收保证金 4执行中 5已完结）');
            $table->string('source')->default('wechat')->comment('订单来源');
            $table->string('id_card')->comment('身份证')->nullable();
            $table->index('order_number');
            $table->index('goods_offer_id');
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
        Schema::drop('orders');
    }
}
