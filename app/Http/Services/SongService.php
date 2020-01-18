<?php

namespace App\Http\Services;

use App\Action;
use App\AdminList;
use App\Http\DTO\SongDto;
use App\Http\DTO\SongInfoDto;
use App\Playlist;
use App\PlaylistSong;
use App\Song;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SongService
{
    /**
     * @param Request $request
     * @return SongDto
     */
    public function prepareSong(Request $request): SongDto
    {
        $dto = new SongDto();
        $input = $request->post();

        $dto->deezerId = $input['deezerId'];
        $dto->name = $input['name'];
        $dto->authors = $input['authors'];
        $dto->length = $input['length'];
        $dto->icon = $input['icon'];

        return $dto;
    }

    /**
     * @param User $user
     * @param Playlist $playlist
     * @return bool
     */
    public function checkUserCanAddRemoveSong(User $user, Playlist $playlist)
    {
        if ($user->id == $playlist->ownerId) {
            return true;
        }

        $adminUserIds = AdminList::where('room_id', $playlist->roomId)->pluck('user_id')->toArray();
        $adminUserIds = array_values($adminUserIds);

        if (in_array($user->id, $adminUserIds)) {
            return true;
        }

        return false;
    }

    /**
     * @param Playlist $playlist
     * @param array $songs
     * @return array
     */
    public function saveList(Playlist $playlist, array $songs)
    {
        $result = [
            'added' => 0,
            'notAdded' => 0,
            'exist' => 0,
            'errorDataList' => []
        ];

        foreach ($songs as $item) {
            $song = new Song();
            $validator = Validator::make($item, $song->rules);

            if ($validator->fails()) {
                $result['errorDataList'][] = (object)($item);
                $result['notAdded'] = $result['notAdded'] + 1;
                continue;
            }

            $song = Song::where('deezerId', $item['deezerId'])->first();

            if (!$song) {
                try {
                    $song = $this->addSong($item);
                } catch (\Exception $e) {
                    $result['errorDataList'][] = (object)($item);
                    $result['notAdded'] = $result['notAdded'] + 1;
                    continue;
                }
            }

            $result = $this->addToPlaylistSong($song, $playlist, $result);
        }

        return $result;
    }

    /**
     * @param $song
     * @param $playlist
     * @param $result
     * @return mixed
     */
    public function addToPlaylistSong($song, $playlist, $result)
    {
        if (PlaylistSong::where('songId', $song->id)
            ->where('playlistId', $playlist->id)
            ->count()
        ) {
            $result['notAdded'] = $result['notAdded'] + 1;
            $result['exist'] = $result['exist'] + 1;

            return $result;
        }

        try {
            PlaylistSong::create([
                'playlistId' => $playlist->id,
                'songId' => $song->id,
                'roomId' => $playlist->roomId,
                'ownerId' => $playlist->ownerId
            ]);
        } catch (\Exception $e) {
            $result['errorDataList'][] = $e->getMessage();
            $result['notAdded'] = $result['notAdded'] + 1;

            return $result;
        }

        $result['added'] = $result['added'] + 1;


        return $result;
    }

    /**
     * @param array $songItem
     * @return mixed
     */
    public function addSong(array $songItem)
    {
        $song = Song::create($songItem);

        return $song;
    }

    /**
     * @param $user
     * @param $songId
     * @param $playlistId
     */
    public function addLike($user, $songId, $playlistId)
    {
        $model = Action::where('songId', $songId)
                        ->where('playlistId', $playlistId)
                        ->where('userId', $user->id)
                        ->first();

        if (!$model) {
            Action::create([
                'userId' => $user->id,
                'songId' => $songId,
                'playlistId' => $playlistId,
                'hasLike' => 1,
                'countListens' => 0
            ]);

            return;
        }

        if (!$model->hasLike) {
            $model->increment('hasLike');
        }

    }

    /**
     * @param $user
     * @param $songId
     * @param $playlistId
     */
    public function addListen($user, $songId, $playlistId)
    {
        $model = Action::where('songId', $songId)
            ->where('playlistId', $playlistId)
            ->where('userId', $user->id)
            ->first();

        if (!$model) {
            Action::create([
                'userId' => $user->id,
                'songId' => $songId,
                'playlistId' => $playlistId,
                'hasLike' => 0,
                'countListens' => 1
            ]);

            return;
        }

        $model->increment('countListens');
    }

    public function buildSongInfo(SongInfoDto $dto, $song, $playlist, $userId) :SongInfoDto
    {
        $dto->songId = $song->songId;
        $dto->deezerId = $song->deezerId;
        $dto->name = $song->name;
        $dto->authors = $song->authors;
        $dto->duration = $song->duration;
        $dto->icon = $song->icon;

        $songInfo = DB::table('playlist_song_user_count_actions')
            ->where('songId', $song->songId)
            ->where('playlistId', $playlist->id)
            ->where('userId', $userId)
            ->first();

        if (empty($songInfo)) {
            $dto->hasLikeFromUser = 0;
            $dto->countListensByUser = 0;
        } else {
            $dto->hasLikeFromUser = $songInfo->hasLike;
            $dto->countListensByUser = $songInfo->countListens;
        }

        $dto->countLikesInPlaylist = DB::table('playlist_song_user_count_actions')
                                        ->where('songId', $song->songId)
                                        ->where('playlistId', $playlist->id)
                                        ->where('hasLike', 1)
                                        ->count();

        $sumListens = DB::table('playlist_song_user_count_actions')
                                        ->select(DB::raw('SUM(countListens) as countListens'))
                                        ->where('songId', $song->songId)
                                        ->where('playlistId', $playlist->id)
                                        ->first();

        $dto->countListensInPlaylist = (int)($sumListens->countListens ?? 0);



        $dto->countUniqueListeners = DB::table('playlist_song_user_count_actions')
                                        ->where('songId', $song->songId)
                                        ->where('playlistId', $playlist->id)
                                        ->where('countListens','>', 0)
                                        ->count();

        $dto->countTotalLikes = DB::table('playlist_song_user_count_actions')
                                        ->where('songId', $song->songId)
                                        ->where('hasLike', 1)
                                        ->count();

        $sumTotalListens = DB::table('playlist_song_user_count_actions')
                                        ->select(DB::raw('SUM(countListens) as countListens'))
                                        ->where('songId', $song->songId)
                                        ->first();

        $dto->countTotalListens = $sumTotalListens->countListens ?? 0;


        return $dto;
    }
}
