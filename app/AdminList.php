<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdminList extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'user_id',
        'room_id',
    ];

    public $timestamps = false;

    protected $table = 'admin_list';
}
