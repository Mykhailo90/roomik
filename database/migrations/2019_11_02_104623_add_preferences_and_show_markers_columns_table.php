<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreferencesAndShowMarkersColumnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->smallInteger('isShowPhone')->default(0);
            $table->smallInteger('isShowEmail')->default(0);
            $table->smallInteger('isShowPreferences')->default(1);
            $table->string('preferences', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('isShowPhone');
            $table->dropColumn('isShowEmail');
            $table->dropColumn('preferences');
            $table->dropColumn('isShowPreferences');
        });
    }
}
