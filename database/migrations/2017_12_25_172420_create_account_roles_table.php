<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_roles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id',true);
            $table->integer('account_business_id')->comment('企业ID');
            $table->string('role_name')->comment('角色名称');
            $table->text('action_list')->comment('操作权限 全选的ID,用逗号隔开');
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
        Schema::drop('account_roles');
    }
}
