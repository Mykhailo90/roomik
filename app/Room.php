<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'logo',
        'type',
        'ownerId',
        'ownerName',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the playlist list for the room.
     */
    public function playListList()
    {
        return $this->hasMany('App\PlaylistRoom');
    }

    /**
     * Get admin list for the room.
     */
    public function adminList()
    {
        return $this->hasMany('App\AdminList');
    }

    public function owner()
    {
        return $this->hasOne(User::class, 'id', 'ownerId');
    }
}
