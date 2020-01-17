<?php

namespace App\Http\DTO;

class RoomResponseDto
{
    public $id;
    public $name;
    public $type;
    public $logo;
    public $ownerId;
    public $ownerName;
    public $ownerAvatar;
    public $countPlayList;
    public $countSongs;
    public $created_at;
    public $invitedUsers;
}
