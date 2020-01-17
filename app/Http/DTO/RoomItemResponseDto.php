<?php

namespace App\Http\DTO;

class RoomItemResponseDto
{
    public $id;
    public $name;
    public $type;
    public $logo;
    public $ownerId;
    public $ownerName;
    public $listOfPlaylist;
    public $adminList;
    public $created_at;
    public $invitedUsers;
}
