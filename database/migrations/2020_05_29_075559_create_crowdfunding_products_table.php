<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCrowdfundingProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'crowdfunding_products',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('total_amount', 10, 2)->comment('众筹当前金额')->default(0);
                $table->decimal('target_amount', 10, 2)->comment('众筹目标金额');
                $table->dateTime('end_at')->comment('结束时间');
                $table->unsignedInteger('user_count')->default(0)->comment('参与用户数');
                $table->string('status')->comment('众筹状态')->default(\App\Models\CrowdfundingProduct::STATUS_FUNDING);
                $table->timestamps();

                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crowdfunding_products');
    }
}
