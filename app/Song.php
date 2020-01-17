<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Song extends Model
{
    public $timestamps = false;

    public   $rules = [
        'deezerId' => 'required|int',
        'name' => 'required|string|max:191',
        'authors' => 'required|string|max:191',
        'duration' => 'required|string|max:191',
        'icon' => 'string|max:191'
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'deezerId', 'name', 'authors', 'duration', 'icon'
    ];
}
