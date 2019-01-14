<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountEmployeesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_employees', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id',true);
            $table->integer('account_id')->comment('账户ID');
            $table->integer('super_id')->comment('添加账户ID');
            $table->integer('account_business_id')->comment('企业信息ID');
            $table->integer('account_branch_framework_id')->comment('所在部门ID');
            $table->index('account_id');
            $table->index('super_id');
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
        Schema::drop('account_employees');
    }
}
