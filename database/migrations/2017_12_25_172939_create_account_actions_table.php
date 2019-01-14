<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_actions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id',true);
            $table->integer('parent_id')->index()->default(0)->comment('所属上级id');
            $table->string('action_code')->comment('权限代码');
            $table->string('code_mean')->comment('权限代码意义');
            $table->string('path')->comment('路径');
            $table->tinyInteger('action_type')->comment('权限类型 0:买家 1:卖家');
            $table->tinyInteger('status')->comment('是否启用(0-禁用,1-启用)');
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
        Schema::drop('account_actions');
    }
}
