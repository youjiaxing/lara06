<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstallmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('用户Id');
            $table->unsignedBigInteger('order_id')->comment('订单Id');
            $table->string('no')->unique()->comment('分期流水号');
            // 这个冗余字段存在的意义?
            $table->decimal('base_amount', 10,2)->comment('订单应支付的原始金额');
            $table->unsignedInteger('count')->comment('分期数');
            // 用 float 真的好吗?
            $table->float('fee_rate')->comment('手续费率');
            $table->float('fine_rate')->comment('逾期费率');
            $table->string('status')->comment('状态')->default(\App\Models\Installment::STATUS_PENDING);
            $table->timestamps();

            $table->foreign('user_id')->on('users')->references('id')->onDelete('cascade');
            $table->foreign('order_id')->on('orders')->references('id')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('installments');
    }
}
