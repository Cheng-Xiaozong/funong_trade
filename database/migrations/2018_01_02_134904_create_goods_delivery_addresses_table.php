<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGoodsDeliveryAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('goods_delivery_addresses', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('seller_id')->comment('卖家ID');
            $table->integer('account_businesses_id')->comment('企业ID');
            $table->integer('account_employee_id')->default(0)->comment('员工ID');
            $table->string('name')->comment('名称');
            $table->smallInteger('province')->comment('省');
            $table->smallInteger('city')->comment('市');
            $table->smallInteger('county')->comment('县')->nullable();
            $table->string('address')->comment('详细地址')->nullable();
            $table->string('address_details')->comment('完整地址');
            $table->string('lng')->comment('经度')->nullable();
            $table->string('lat')->comment('纬度')->nullable();
            $table->tinyInteger('status')->default(1)->comment('状态 由卖家控制 1启用 0禁用');
            $table->tinyInteger('delete_status')->default(1)->comment('删除状态 1正常 0删除');
            $table->timestamps();
            $table->index('seller_id');
            $table->index('account_employee_id');
            $table->index('account_businesses_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('goods_delivery_addresses');
    }
}
