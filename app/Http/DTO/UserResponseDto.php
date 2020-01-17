<?php

namespace App\Http\DTO;

class UserResponseDto
{
    public $id;
    public $name;
    public $avatar;
    public $email;
    public $phone;
    public $preferences;
    public $isShowPhone;
    public $isShowEmail;
    public $isShowPreferences;
    public $token;
    public $socialAccounts;
    public $isNeedVerificationEmail;
    public $rooms;
    public $invitingRooms;
    public $roomsWhereAdminList;
}
