<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDistrictBodiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('district_bodies', function (Blueprint $table) {
            $table->bigIncrements('id');
            //registerer_id
            $table->string('register_id')->nullable();

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
            $table->date('phase_8')->nullable();
            $table->date('phase_9')->nullable();
            $table->date('phase_10')->nullable();
            $table->date('phase_11')->nullable();
            $table->date('phase_12')->nullable();
            $table->date('phase_13')->nullable();
            $table->date('phase_14')->nullable();
            $table->date('phase_15')->nullable();
            $table->date('phase_16')->nullable();
            $table->date('phase_17')->nullable();
            $table->date('phase_18')->nullable();
            $table->date('phase_19')->nullable();
            $table->date('phase_20')->nullable();
            $table->date('phase_21')->nullable();

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
        Schema::dropIfExists('district_bodies');
    }
}
