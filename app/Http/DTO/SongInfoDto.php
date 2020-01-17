<?php

namespace App\Http\DTO;

class SongInfoDto
{
    public $songId;
    public $deezerId;
    public $name;
    public $authors;
    public $icon;
    public $duration;
    public $hasLikeFromUser;
    public $countLikesInPlaylist;
    public $countTotalLikes;
    public $countListensInPlaylist;
    public $countTotalListens;
    public $countListensByUser;
    public $countUniqueListeners;
}
