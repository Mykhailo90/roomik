<?php

namespace App\Http\DTO;

class PlaylistResponseDto
{
    public $id;
    public $name;
    public $type;
    public $logo;
    public $ownerId;
    public $roomId;
    public $countSongs;
    public $countUniqueListeners;
    public $created_at;
}
