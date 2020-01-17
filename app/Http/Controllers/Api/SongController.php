<?php

namespace App\Http\Controllers\Api;

use App\Http\Services\PlaylistService;
use App\Http\Services\SongService;
use App\Playlist;
use App\PlaylistSong;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use WebSocket\Client;


class SongController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('customAuth');
    }

    /**
     * @param Request $request
     * @param SongService $songService
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, SongService $songService)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'songs' => 'array',
            'playlistId' => 'required|exists:playlist,id',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        $playlist = Playlist::find($input['playlistId']);

        if (!$songService->checkUserCanAddRemoveSong($user, $playlist)) {
            return $this->sendError('Failed store.', 'You are not have an access to add song in this room');
        }

        if (empty($input['songs'])) {
            return $this->sendError('Failed store.', 'SongList empty');
        }

        $result = $songService->saveList($playlist, $input['songs']);

        $success = $result;

        return $this->sendResponse($success, 'Playlist created successfully.');
    }

    /**
     * Route to change statistic info for track
     * @param Request $request
     * @param PlaylistService $playlistService
     * @param SongService $songService
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PlaylistService $playlistService, SongService $songService)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'playlistId' => 'required|int',
            'songId' => 'required|int',
            'action' => [
                'required',
                Rule::in(['like', 'listen']),
            ],
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token', 403);
        }

        if (!PlaylistSong::where('songId', $input['songId'])
            ->where('playlistId', $input['playlistId'])
            ->count()
        ) {
            return $this->sendError('Failed update.', 'Song in the playlist not found', 404);
        }

        if ($input['action'] == 'like') {
            $songService->addLike($user, $input['songId'], $input['playlistId']);
        } else if ($input['action'] == 'listen') {
            $songService->addListen($user, $input['songId'], $input['playlistId']);
        }

        $playlist = Playlist::find($input['playlistId']);

        $trackInfoList = $playlistService->addAdditionalInfo($playlist, $user);
        $playlist->ownerName = $playlist->user->name;
        $playlist->trackInfoList = $trackInfoList;
        $success['playlist'] = $playlist;

        try{
            $client = new Client("ws://roomik.best:9002");
            $msg = 'songId=' . $input['songId'] . ';action=' . $input['action'];
            $client->send($msg);
        } catch (\Exception $e) {
            return $this->sendResponse($success, 'Updated successfully.');
        }

        return $this->sendResponse($success, 'Updated successfully.');
    }

    /**
     * @param Request $request
     * @param SongService $songService
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(Request $request, SongService $songService)
    {
        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        $input = $request->post();

        $validator = Validator::make($input, [
            'playlistId' => 'required|int',
            'songId' => 'required|int'
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $songInPlaylist = PlaylistSong::where('songId', $input['songId'])
            ->where('playlistId', $input['playlistId'])
            ->first();

        if (is_null($songInPlaylist)) {
            return $this->sendError('Failed remove.', 'Song not found', 404);
        }

        if (!$songService->checkUserCanAddRemoveSong($user, $songInPlaylist->playlist)) {
            return $this->sendError('Failed remove.', 'You have not an access', 403);
        }

        PlaylistSong::where('songId', $input['songId'])
            ->where('playlistId', $input['playlistId'])
            ->delete();

        $result['song'] = $songInPlaylist->song;

        return $this->sendResponse($result, 'Song deleted successfully.');
    }
}
