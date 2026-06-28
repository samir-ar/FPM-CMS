<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RecreatePersonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            $table->string("image");
            $table->text("name");

            $table->unsignedBigInteger("dynamic_representative_id");
            $table->foreign('dynamic_representative_id')->references('id')->on("dynamic_representatives")->onDelete('cascade')->onUpdate('cascade');

            $table->integer('order');

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
        
    }
}
