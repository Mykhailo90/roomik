<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateActorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('actors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('firstName');
            $table->string('lastName');
            $table->string('fatherName')->nullable();
            $table->integer('age');
            $table->smallInteger('gender');
            $table->string('email')->unique();
            $table->string('fbProfile')->nullable();
            $table->string('vkProfile')->nullable();
            $table->string('instagramProfile')->nullable();
            $table->string('phoneNumber')->unique();
            $table->string('mainPhoto')->nullable();
            $table->bigInteger('cityId');
            $table->integer('height')->nullable();
            $table->integer('weight')->nullable();
            $table->integer('clothesSizeId')->nullable();
            $table->integer('breastSize')->nullable();
            $table->integer('waistSize')->nullable();
            $table->integer('thighSize')->nullable();
            $table->integer('euShoesze')->nullable();
            $table->integer('heirLength')->nullable();
            $table->string('hairColor')->nullable();
            $table->string('eyeColor')->nullable();
            $table->string('typeOfAppearance')->nullable();
            $table->integer('internationalPassportType')->default(0);
            $table->integer('filmingExperience')->nullable(0);
            $table->text('education')->nullable();
            $table->text('filmographyList')->nullable();

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
        Schema::dropIfExists('actors');
    }
}
