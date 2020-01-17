<?php

namespace App\Http\Services;

use App\AdminList;
use App\Http\DTO\RoomDto;
use App\Http\DTO\RoomItemResponseDto;
use App\Http\DTO\RoomResponseDto;
use App\Http\DTO\UserShortResponseDto;
use App\Playlist;
use App\PlaylistSong;
use App\Room;
use App\Song;
use App\User;
use App\UserRoom;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;

class RoomService
{
    public function prepareRoom($request, $user): RoomDto
    {
        $dto = new RoomDto();
        $input = $request->post();
        $time = time();

        $dto->name = $input['name'];
        $dto->type = $input['type'];
        $dto->ownerId = $user->id;
        $dto->ownerName = $user->name;
        $dto->ownerAvatar = $user->avatar;

        if (isset($request->logo) && !empty($request->logo)) {
            $logo = $request->file('logo');

            $destinationPath = 'logoItems/';
            $profileImage = date('YmdHis') . "." . $logo->getClientOriginalExtension();
            $logo->move($destinationPath, $profileImage);

            $dto->logo = 'https://' . $request->getHost() . '/' . $destinationPath . $profileImage;
        }

        $dto->created_at = $time;
        $dto->updated_at = $time;

        return $dto;
    }

    public function prepareShowRoomList($rooms, $playListCollection = null, $user = null, $songIds = null): array
    {
        $result = [];

        foreach ($rooms as $room) {
            $dto = new RoomResponseDto();

            if (!$user) {
                $user = $room->owner;
            }

            $dto->id = $room->id;
            $dto->name = $room->name;
            $dto->logo = $room->logo;
            $dto->type = $room->type;
            $dto->ownerId = $room->ownerId;
            $dto->ownerName = $room->ownerName;
            $dto->ownerAvatar = $user->avatar ? 'https://'. Request::getHttpHost() . '/' . $user->avatar : '';
            $dto->created_at = $room->created_at;
            $dto->invitedUsers = $this->getInvitingUsersByRoomId($room->id);

            $playlistItems =
                DB::table('playlist_room')
                    ->where('roomId', $room->id)
                    ->pluck('playlistId');

            $dto->countLikes = DB::table('playlist_song_user_count_actions')
                ->whereIn('playlistId', $playlistItems)
                ->where('hasLike', 1)
                ->count();

            $sumListens = DB::table('playlist_song_user_count_actions')
                ->select(DB::raw('SUM(countListens) as countListens'))
                ->whereIn('playlistId', $playlistItems)
                ->first();

            $dto->countListens = (int)($sumListens->countListens ?? 0);

            $uniqueUsers = DB::table('playlist_song_user_count_actions')
                ->select('userId')
                ->whereIn('playlistId', $playlistItems)
                ->where('countListens','>', 0)
                ->distinct()
                ->get();

            $dto->countUniqueListeners = count($uniqueUsers) ?? 0;

            $dto->countPlayList = count($playlistItems);

            if ($dto->countPlayList) {
                $dto->countSongs = DB::table('playlist_song')
                    ->whereIn('playlistId', $playlistItems)
                    ->count();
            } else {
                $dto->countSongs = 0;
            }

            if (!empty($playListCollection)) {
                $collection = [];
                $playlistService = new PlaylistService();
                foreach ($playListCollection as $item) {
                    if ($item->roomId == $room->id) {
                        if ($playlistService->isShowAccepted($user, $item)) {
                            $trackInfoList = $playlistService->addAdditionalInfo($item, $user, $songIds);
                            $item->ownerName = $item->user->name;
                            $item->trackInfoList = $trackInfoList;
                            $collection[] = $item;
                        }
                    }
                }
                $dto->playListCollection = $collection;
            }

            $result[] = $dto;
        }

        return $result;
    }

    public function prepareShowRoomItem(Room $room, RoomService $service): RoomItemResponseDto
    {
        $dto = new RoomItemResponseDto();

        $dto->id = $room->id;
        $dto->name = $room->name;
        $dto->type = $room->type;
        $dto->logo = $room->logo;
        $dto->ownerId = $room->ownerId;
        $dto->ownerName = $room->ownerName;
        $dto->created_at = $room->created_at;
        $dto->invitedUsers = $this->getInvitingUsersByRoomId($room->id);

        $playlistIds =
            DB::table('playlist_room')
                ->where('roomId', $room->id)
                ->pluck('playlistId');

        $dto->listOfPlaylist = Playlist::whereIn('id', $playlistIds)->get();
        $dto->adminList = $service->getAdminList($room);

        return $dto;
    }

    /**
     * @param $user
     * @param $roomId
     * @return bool
     */
    public function checkUserIsOwner($user, $roomId)
    {
        $room = Room::where('id', $roomId)->first();

        return $user->id == $room->ownerId;
    }

    public function getAdminList($room)
    {
        $userIds = AdminList::where('room_id', $room->id)->pluck('user_id')->unique()->toArray();
        $userIds = array_values($userIds);

        return User::whereIn('id', $userIds)->get();
    }

    public function getRoomsByUserId($userId)
    {
        $rooms = Room::where('ownerId', $userId)->get();

        return $this->prepareShowRoomList($rooms);
    }

    public function getInvitingRoomsByUserId($userId)
    {
        $roomIds = UserRoom::where('user_id', $userId)->pluck('roomId')->unique()->toArray();
        $roomIds = array_values($roomIds);
        $rooms = Room::find($roomIds);

        return $this->prepareShowRoomList($rooms);
    }

    /**
     * @param $roomId
     * @return array
     */
    public function getInvitingUsersByRoomId($roomId)
    {
        $userIds = UserRoom::where('roomId', $roomId)->pluck('user_id')->unique()->toArray();
        $userIds = array_values($userIds);
        $users = User::find($userIds);
        $result = [];

        foreach ($users as $user) {
            $result[] = $this->prepareUserResponse($user);
        }

        return $result;
    }

    public function prepareUserResponse(User $user)
    {
        $dto = new UserShortResponseDto();

        $dto->id = $user->id;
        $dto->name = $user->name;
        $dto->avatar = 'https://'. Request::getHttpHost() . '/' . $user->avatar;
        $dto->email = $user->email;
        $dto->phone = $user->phone;
        $dto->preferences = $user->preferences;
        $dto->isShowEmail = $user->isShowEmail;
        $dto->isShowPhone = $user->isShowPhone;
        $dto->isShowPreferences = $user->isShowPreferences;

        return $dto;
    }

    public function getRoomsWhereAdminList($userId)
    {
        $roomIds = AdminList::where('user_id', $userId)->pluck('room_id')->unique()->toArray();
        $roomIds = array_values($roomIds);
        $rooms = Room::find($roomIds);

        return $this->prepareShowRoomList($rooms);
    }

    public function getRoomsByUserName($search)
    {
        $userIds = User::where('name', 'like', "%" . $search . "%")->pluck('id')->toArray();
        $userIds = array_values($userIds);

        return $this->getRoomsByUserIds($userIds);
    }

    public function getRoomsByUserEmail($search)
    {
        $userIds = User::where('email', 'like', "%" . $search . "%")->pluck('id')->toArray();
        $userIds = array_values($userIds);

        return $this->getRoomsByUserIds($userIds);
    }

    private function getRoomsByUserIds($userIds)
    {
        if (empty($userIds)) {
            return [];
        }
        $userIds = array_values($userIds);
        $rooms = Room::whereIn('ownerId', $userIds)->get();
        $result = count($rooms) ? $this->prepareShowRoomList($rooms) : [];

        return $result;
    }

    public function getRoomsByName($search)
    {
        $rooms = Room::where('name', 'like', "%" . $search . "%")->get();
        $result = count($rooms) ? $this->prepareShowRoomList($rooms) : [];

        return $result;
    }

    public function getRoomsByPlaylistName($search, $user = null)
    {
        $roomsIds = Playlist::where('name', 'like', "%" . $search . "%")->pluck('roomId')->toArray();
        $playListCollection = Playlist::where('name', 'like', "%" . $search . "%")->get();

        if (empty($roomsIds)) {
            return [];
        }

        $roomsIds = array_values($roomsIds);
        $rooms = Room::whereIn('id', $roomsIds)->get();
        $result = count($rooms) ? $this->prepareShowRoomList($rooms, $playListCollection, $user) : [];

        return $result;
    }

    public function getRoomsByPlaylistDescription($search, $user = null)
    {
        $roomsIds = Playlist::where('description', 'like', "%" . $search . "%")->pluck('roomId')->toArray();
        $playListCollection = Playlist::where('description', 'like', "%" . $search . "%")->get();

        if (empty($roomsIds)) {
            return [];
        }

        $roomsIds = array_values($roomsIds);
        $rooms = Room::whereIn('id', $roomsIds)->get();
        $result = count($rooms) ? $this->prepareShowRoomList($rooms, $playListCollection, $user) : [];

        return $result;
    }

    public function getRoomsBySongName($search, $user = null)
    {
        $songsIds = Song::where('name', 'like', "%" . $search . "%")->pluck('id')->unique()->toArray();

        if (empty($songsIds)) {
            return [];
        }
        $songsIds = array_values($songsIds);
        $roomsIds = PlaylistSong::whereIn('songId', $songsIds)->pluck('roomId')->unique()->toArray();

        if (empty($roomsIds)) {
            return [];
        }

        $playlistIds = PlaylistSong::whereIn('songId', $songsIds)->pluck('playlistId')->unique()->toArray();
        $playlistIds = array_values($playlistIds);
        $playlistCollection = Playlist::whereIn('id', $playlistIds)->get();

        $roomsIds = array_values($roomsIds);
        $rooms = Room::find($roomsIds);

        $result = count($rooms) ? $this->prepareShowRoomList($rooms, $playlistCollection, $user, $songsIds) : [];

        return $result;
    }

    public function getRoomsBySongAuthors($search, $user = null)
    {
        $songsIds = Song::where('authors', 'like', "%" . $search . "%")->pluck('id')->toArray();

        if (empty($songsIds)) {
            return [];
        }
        $songsIds = array_values($songsIds);
        $roomsIds = PlaylistSong::whereIn('songId', $songsIds)->pluck('roomId')->toArray();

        if (empty($roomsIds)) {
            return [];
        }

        $playlistIds = PlaylistSong::whereIn('songId', $songsIds)->pluck('playlistId')->unique()->toArray();
        $playlistIds = array_values($playlistIds);
        $playlistCollection = Playlist::whereIn('id', $playlistIds)->get();

        $roomsIds = array_values($roomsIds);
        $rooms = Room::whereIn('id', $roomsIds)->get();

        $result = count($rooms) ? $this->prepareShowRoomList($rooms, $playlistCollection, $user, $songsIds) : [];

        return $result;
    }

    public function prepareShowRoomItemWithSearch(
        Room $room,
        PlaylistService $playlistService,
        $search
    ): RoomItemResponseDto
    {
        $dto = new RoomItemResponseDto();

        $dto->id = $room->id;
        $dto->name = $room->name;
        $dto->type = $room->type;
        $dto->logo = $room->logo;
        $dto->ownerId = $room->ownerId;
        $dto->ownerName = $room->ownerName;
        $dto->created_at = $room->created_at;

        $playlistIds =
            DB::table('playlist_room')
                ->where('roomId', $room->id)
                ->pluck('playlistId');

        $dto->listOfPlaylist['searchInName'] = $playlistService->getPlaylistByName($search, $playlistIds);
        $dto->listOfPlaylist['searchInDescription'] = $playlistService->getPlaylistByDescription($search, $playlistIds);
        $dto->listOfPlaylist['searchInSongName'] = $playlistService->getPlaylistBySongName($search, $room->id);
        $dto->listOfPlaylist['searchInSongAuthors'] = $playlistService->getPlaylistBySongAuthors($search, $room->id);
        $dto->adminList = $this->getAdminList($room);

        return $dto;
    }

    /**
     * @param User $user
     */
    public function updateUserInfo(User $user)
    {
        if (!empty($user)) {
            DB::table('rooms')
                ->where('ownerId', $user->id)
                ->update(['ownerName' => $user->name]);
        }
    }
}
