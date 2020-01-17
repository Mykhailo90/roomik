<?php

namespace App\Http\Services;

use App\AdminList;
use App\Http\DTO\PlaylistDto;
use App\Http\DTO\PlaylistResponseDto;
use App\Http\DTO\SongInfoDto;
use App\Playlist;
use App\PlaylistSong;
use App\Room;
use App\Song;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaylistService
{
    /**
     * @param Request $request
     * @param $user
     * @return PlaylistDto
     */
    public function preparePlaylist(Request $request, $user): PlaylistDto
    {
        $dto = new PlaylistDto();
        $input = $request->post();
        $time = time();

        if (isset($input['name'])) {
            $dto->name = ($input['name']);
        }

        if (isset($input['type'])) {
            $dto->type = $input['type'];
        }

        if (key_exists('description', $input)) {
            $dto->description = $input['description'] ?? '';
        }

        $dto->ownerId = $user->id;
        $dto->roomId = $input['roomId'];
        $dto->created_at = $time;
        $dto->updated_at = $time;

        if (isset($request->logo) && !empty($request->logo)) {
            $logo = $request->file('logo');

            $destinationPath = 'logoItems/';
            $profileImage = date('YmdHis') . "." . $logo->getClientOriginalExtension();
            $logo->move($destinationPath, $profileImage);

            $dto->logo = 'https://' . $request->getHost() . '/' . $destinationPath . $profileImage;
        }

        return $dto;
    }

    /**
     * @param $playListItems
     * @return array
     */
    public function prepareShowPlaylist($playListItems): array
    {
        $result = [];

        foreach ($playListItems as $playList) {
            $dto = new PlaylistResponseDto();

            $dto->id = $playList->id;
            $dto->name = $playList->name;
            $dto->type = $playList->type;
            $dto->logo = $playList->logo;
            $dto->ownerId = $playList->ownerId;
            $dto->roomId = $playList->roomId;
            $dto->created_at = $playList->created_at;

            $songs =
                DB::table('playlist_song')
                    ->where('playlistId', $playList->id)
                    ->pluck('songId');

            $dto->countSongs = count($songs);

            if ($dto->countSongs) {
                $dto->countUniqueListeners = DB::table('playlist_song_user_count_actions')
                    ->where('playlistId', $playList->id)
                    ->whereIn('songId', $songs)
                    ->where('countListens', '>', 0)
                    ->count();
            } else {
                $dto->countUniqueListeners = 0;
            }

            $result[] = $dto;
        }

        return $result;
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

    /**
     * @param User $user
     * @param Playlist $playlist
     * @return bool
     */
    public function isShowAccepted(User $user, Playlist $playlist) :bool
    {
        if ($playlist->type == 'public') {
            return true;
        }

        if ($user->id == $playlist->ownerId) {
            return true;
        }

        $room = $playlist->room;

        $userIds = AdminList::where('room_id', $room->id)->pluck('user_id')->toArray();

        if (!empty($userIds) && in_array($user->id, $userIds)) {
            return true;
        }

        return false;
    }

    /**
     * @param Playlist $playlist
     * @param User $user
     * @return array
     */
    public function addAdditionalInfo(Playlist $playlist, User $user, $songIds = null) :array
    {
        $result = [];

        if (empty($songIds)) {
            $songs = DB::table('playlist_song')
                ->leftJoin('songs', 'playlist_song.songId', '=', 'songs.id')
                ->select(
                    'playlist_song.songId',
                    'songs.deezerId',
                    'songs.name',
                    'songs.authors',
                    'songs.duration',
                    'songs.icon'
                )
                ->where('playlist_song.playlistId', $playlist->id)
                ->get();
        } else {
            $songs = DB::table('playlist_song')
                ->leftJoin('songs', 'playlist_song.songId', '=', 'songs.id')
                ->select(
                    'playlist_song.songId',
                    'songs.deezerId',
                    'songs.name',
                    'songs.authors',
                    'songs.duration',
                    'songs.icon'
                )
                ->where('playlist_song.playlistId', $playlist->id)
                ->whereIn('songs.id', $songIds)
                ->get();
        }

        if (count($songs) == 0) {
            return $result;
        }

        $songService = new SongService();
        $index = 0;
        foreach ($songs as $item) {
            $result[$index] = $songService->buildSongInfo(
                new SongInfoDto(),
                $item,
                $playlist,
                $user->id
            );
            ++$index;
        }

        $roomService = new RoomService();
        $playlist->user = $roomService->prepareUserResponse($user);

        return $result;
    }

    public function getPlaylistByName($search, $playlistIds)
    {
        $items = Playlist::whereIn('id', $playlistIds)->where('name', 'like', "%" . $search . "%")->get();

        return count($items) ? $items : [];
    }

    public function getPlaylistByDescription($search, $playlistIds)
    {
        $items = Playlist::whereIn('id', $playlistIds)->where('description', 'like', "%" . $search . "%")->get();

        return count($items) ? $items : [];
    }

    public function getPlaylistBySongName($search, $roomId)
    {
        // Получили ИД песен связанные с комнатой
        $songsIds = PlaylistSong::where('roomId', $roomId)->pluck('songId')->unique()->toArray();

        if (empty($songsIds)) {
            return [];
        }
        $songsIds = array_values($songsIds);

        // Получили ИД песен с нужным вхождением по имени
        $songsIds = Song::whereIn('id', $songsIds)
            ->where('name', 'like', "%" . $search . "%")
            ->pluck('id')
            ->unique()
            ->toArray();

        if (empty($songsIds)) {
            return [];
        }

        $songsIds = array_values($songsIds);

        // Получили ИД плейлистов, которые нужно отобразить
        $playlistIds = PlaylistSong::whereIn('songId', $songsIds)->pluck('playlistId')->unique()->toArray();

        if (empty($playlistIds)) {
            return [];
        }

        $playlistIds = array_values($playlistIds);
        $items = Playlist::whereIn('id', $playlistIds)->get();

        return count($items) ? $items : [];
    }

    public function getPlaylistBySongAuthors($search, $roomId)
    {
        // Получили ИД песен связанные с комнатой
        $songsIds = PlaylistSong::where('roomId', $roomId)->pluck('songId')->unique()->toArray();

        if (empty($songsIds)) {
            return [];
        }
        $songsIds = array_values($songsIds);

        // Получили ИД песен с нужным вхождением по имени
        $songsIds = Song::whereIn('id', $songsIds)
            ->where('authors', 'like', "%" . $search . "%")
            ->pluck('id')
            ->unique()
            ->toArray();

        if (empty($songsIds)) {
            return [];
        }

        $songsIds = array_values($songsIds);

        // Получили ИД плейлистов, которые нужно отобразить
        $playlistIds = PlaylistSong::whereIn('songId', $songsIds)->pluck('playlistId')->unique()->toArray();

        if (empty($playlistIds)) {
            return [];
        }

        $playlistIds = array_values($playlistIds);
        $items = Playlist::whereIn('id', $playlistIds)->get();

        return count($items) ? $items : [];
    }
}
