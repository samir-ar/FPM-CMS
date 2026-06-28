<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDistrictCoordinatorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('district_coordinators', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('tracking_code');

            $table->unsignedBigInteger("candidate_id");
            $table->foreign('candidate_id')->references('id')->on("candidates")->onDelete('cascade')->onUpdate('cascade');

            $table->string('district');

            $table->date('phase_1')->nullable();
            $table->date('phase_2')->nullable();
            $table->date('phase_3')->nullable();
            $table->date('phase_4')->nullable();
            $table->date('phase_5')->nullable();
            $table->date('phase_6')->nullable();
            $table->date('phase_7')->nullable();

            $table->string('popularization_no')->nullable();
            
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
        Schema::dropIfExists('district_coordinators');
    }
}
