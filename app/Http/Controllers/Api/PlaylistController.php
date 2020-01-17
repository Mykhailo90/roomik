<?php

namespace App\Http\Controllers\Api;

use App\Action;
use App\Http\Services\ImageService;
use App\Http\Services\PlaylistService;
use App\Http\Services\RoomService;
use App\Http\Services\SongService;
use App\Playlist;
use App\PlaylistRoom;
use App\PlaylistSong;
use App\Room;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class PlaylistController extends BaseController
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
     * @param PlaylistService $playlistService
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, PlaylistService $playlistService)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'name' => 'required|string|max:191',
            'description' => 'string|max:191|nullable',
            'logo' => 'image|max:4096',
            'type' => [
                'required',
                Rule::in(['private', 'public']),
            ],
            'roomId' => 'required|exists:rooms,id',
            'songs' => 'array'
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        if (!$playlistService->checkUserIsOwner($user, $input['roomId'])) {
            return $this->sendError('Failed store.', 'You are not an owner');
        }

        $playlistDto = (array)$playlistService->preparePlaylist($request, $user);

        $playlist = Playlist::create($playlistDto);

        $songs = $input['songs'] ?? [];

        if (!empty($songs)) {
            $songService = new SongService();
            $songService->saveList($playlist, $input['songs']);
        }

        PlaylistRoom::create(
            [
                'roomId' => $input['roomId'],
                'playlistId' => $playlist->id,
                'ownerId' => $user->id
            ]
        );

        $trackInfoList = $playlistService->addAdditionalInfo($playlist, $user);
        $playlist->ownerName = $playlist->user->name;
        $playlist->trackInfoList = $trackInfoList;
        $success['playlist'] = $playlist;

        return $this->sendResponse($success, 'Playlist created successfully.');
    }

    /**
     * @param Request $request
     * @param PlaylistService $playlistService
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id, PlaylistService $playlistService)
    {
        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        $playlist = Playlist::find($id);

        if (empty($playlist)) {
            return $this->sendError('Playlist not found.', 'Invalid Playlist', 404);
        }

        if ($playlistService->isShowAccepted($user, $playlist)) {
            $trackInfoList = $playlistService->addAdditionalInfo($playlist, $user);
            $playlist->ownerName = $playlist->user->name;
            $playlist->trackInfoList = $trackInfoList;
            $success['playlist'] = $playlist;
            $success['checksum'] = md5(print_r($playlist, true));

            return $this->sendResponse($success, 'Playlist retrieved successfully.');
        } else {
            return $this->sendError('You have no access to open this information.');
        }
    }

    /**
     * @param Request $request
     * @param Playlist $playlist
     * @param PlaylistService $playlistService
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id, PlaylistService $playlistService)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'name' => 'string|max:191',
            'logo' => 'image|max:4096',
            'type' => [
                Rule::in(['private', 'public']),
            ],
            'description' => 'string|max:191|nullable',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token', 403);
        }

        $playlist = Playlist::find($id);

        if (is_null($playlist)) {
            return $this->sendError('Failed update.', 'Playlist not found', 404);
        }

        if ($user->id != $playlist->ownerId) {
            return $this->sendError('Failed update.', 'You are not an owner', 403);
        }

        if (isset($input['name'])) {
            $playlist->name = $input['name'];
        }

        if (isset($input['type'])) {
            $playlist->type = $input['type'];
        }

        if (key_exists('description', $input)) {
            $playlist->description = $input['description'] ?? '';
        }

        if (isset($request->logo) && !empty($request->logo)) {
            $destinationPath = 'logoItems/';

            $imgService = new ImageService();
            $host = $request->getHost();

            if (!empty($playlist->logo)) {
                $imgService->removeImg($playlist->logo, $host);
            }

            $logo = $request->file('logo');
            $profileImage = date('YmdHis') . "." . $logo->getClientOriginalExtension();
            $logo->move($destinationPath, $profileImage);

            $playlist->logo = 'https://' . $request->getHost() . '/' . $destinationPath . $profileImage;
        }

        $playlist->save();

        $playlist->ownerName = $playlist->user->name;
        $success['playlist'] = $playlist;

        return $this->sendResponse($success, 'Playlist updated successfully.');
    }

    /**
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(Request $request, $id)
    {
        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        $playlist = Playlist::find($id);

        if (is_null($playlist)) {
            return $this->sendError('Failed update.', 'Playlist not found', 404);
        }

        if ($user->id != $playlist->ownerId) {
            return $this->sendError('Failed update.', 'You are not an owner', 403);
        }

        //remove logo
        $imgService = new ImageService();
        $host = $request->getHost();
        $imgService->removeImg($playlist->logo, $host);
        //remove songs
        PlaylistSong::where('playlistId', $playlist->id)->delete();
        //remove actions
        Action::where('playlistId', $playlist->id)->delete();
        //remove list
        $playlist->delete();

        return $this->sendResponse($playlist->toArray(), 'Playlist deleted successfully.');
    }

    /**
     * @param Request $request
     * @param PlaylistService $playlistService
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request, PlaylistService $playlistService, RoomService $roomService)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'search' => 'required|string|max:199',
            'roomId' => 'required|exists:rooms,id',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        $search = $input['search'];

        $room = Room::find($input['roomId']);
        $success['room'] = $roomService->prepareShowRoomItemWithSearch($room, $playlistService, $search);

        return $this->sendResponse($success, 'Room retrieved successfully.');
    }
}
