<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdminMenuTablel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admin_menu', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name',40)->comment('菜单名称');
            $table->string('url',50)->comment('菜单地址');
            $table->string('icon',20)->comment('菜单图标');
            $table->integer('pid')->comment('父级ID');
            $table->integer('sort')->comment('排序');
            $table->tinyInteger('status_at')->default(1)->comment('1 启用 2禁用');
            $table->tinyInteger('level')->default(1)->comment('1 一级菜单 2二级菜单 3 三级对应的操作项');
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
        Schema::drop('admin_menu');
    }
}
