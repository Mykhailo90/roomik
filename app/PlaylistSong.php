<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PlaylistSong extends Model
{
    protected $table = 'playlist_song';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'playlistId', 'songId', 'roomId', 'ownerId'
    ];

    public function playlist()
    {
        return $this->belongsTo('App\Playlist', 'playlistId', 'id');
    }

    public function room()
    {
        return $this->belongsTo('App\Room', 'roomId', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'ownerId', 'id');
    }

    public function song()
    {
        return $this->belongsTo('App\Song', 'songId', 'id');
    }
}
