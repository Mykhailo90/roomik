<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlaylistRoom extends Model
{
    protected $table = 'playlist_room';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'playlistId', 'roomId', 'ownerId'
    ];
}
