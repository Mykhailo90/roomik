<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlaylistSongUserCountActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('playlist_song_user_count_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('songId');
            $table->integer('playlistId');
            $table->integer('userId');
            $table->integer('hasLike')->default(0);
            $table->integer('countListens')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('playlist_song_user_count_actions');
    }
}
