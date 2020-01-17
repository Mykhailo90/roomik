<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    public $timestamps = false;
    protected $table = 'social_users';

    protected $fillable = [
        'user_id', 'provider', 'provider_id', 'emailVerificated', 'user_provider_token'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id', 'user_id', 'emailVerificated'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
