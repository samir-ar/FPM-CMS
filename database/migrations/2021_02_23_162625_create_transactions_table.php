<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('app_users');
            $table->string('transaction_id')->nullable();
            $table->index('transaction_id');
            $table->double('amount')->nullable();
            $table->string('currency')->nullable();
            $table->string('resp_code')->nullable();
            $table->string('resp_msg')->nullable();
            $table->string('auth_nb')->nullable();
            $table->string('sub_msg')->nullable();
            $table->boolean('completed')->nullable();
            $table->boolean('success')->nullable();
            $table->string('user_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
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
        Schema::dropIfExists('transactions');
    }
}
