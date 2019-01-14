<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAppVersionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('version_code')->comment('版本号');
            $table->string('version_name')->comment('版本名称');
            $table->string('update_title')->comment('标题');
            $table->string('app_name')->comment('app名称');
            $table->string('file_name')->comment('文件名');
            $table->tinyInteger('is_update_anyway')->comment('是否强制更新');
            $table->string('update_info')->comment('更新内容');
            $table->tinyInteger('status')->default(0)->comment('状态0-禁用1-启用');
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
        Schema::drop('app_versions');
    }
}
