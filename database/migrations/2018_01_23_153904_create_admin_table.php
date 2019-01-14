<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdminTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admin', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_name',30)->comment('后台账户名');
            $table->string('full_name',30)->comment('姓名');
            $table->string('password',60)->comment('密码');
            $table->integer('role_id')->comment('角色ID');
            $table->tinyInteger('status_at')->default(1)->comment('1 启用 2禁用');
            $table->softDeletes();
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
        Schema::drop('admin');
    }
}
