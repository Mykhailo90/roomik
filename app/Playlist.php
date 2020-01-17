<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'name', 'logo', 'description', 'type', 'ownerId', 'roomId', 'created_at', 'updated_at'
    ];

    public $table = 'playlist';

    public function room()
    {
        return $this->belongsTo('App\Room', 'roomId', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'ownerId', 'id');
    }
}
