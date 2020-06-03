<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstallmentItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('installment_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('installment_id');
            $table->unsignedInteger('sequence')->comment('分期序号');
            $table->decimal('base_amount', 10, 2)->comment('当期订单金额');
            $table->decimal('fee', 10, 2)->comment('手续费')->default(0);
            $table->decimal('fine', 10, 2)->comment('逾期费')->nullable();
            $table->dateTime('due_date')->comment('当期支付截至日期');
            $table->dateTime('paid_at')->nullable()->comment('实际支付日期');
            $table->string('payment_method')->nullable()->comment('支付平台');
            $table->string('payment_no')->nullable()->comment('支付平台订单号');
            $table->string('refund_status')->comment("退款状态");

            $table->timestamps();

            $table->foreign('installment_id')->on('installments')->references('id')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('installment_items');
    }
}
