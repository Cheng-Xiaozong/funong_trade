<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountBranchFrameworksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_branch_frameworks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('account_id')->comment('账户ID');
            $table->string('branch_name')->comment('部门名称');
            $table->integer('parent_id')->index()->default(0)->comment('所属上级id');
            $table->string('path')->comment('路径');
            $table->tinyInteger('status')->comment('是否启用(0-禁用,1-启用)');
            $table->index('account_id');
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
        Schema::drop('account_branch_frameworks');
    }
}
