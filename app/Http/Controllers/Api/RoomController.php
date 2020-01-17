<?php

namespace App\Http\Controllers\Api;

use App\AdminList;
use App\Http\Services\ImageService;
use App\Http\Services\PlaylistService;
use App\Http\Services\RoomService;
use App\Http\Services\SongService;
use App\Playlist;
use App\Room;
use App\User;
use App\UserRoom;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RoomController extends BaseController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('customAuth',['except'=>['index']]);
    }


    /**
     * @param RoomService $roomService
     * @return \Illuminate\Http\Response
     */
    public function index(RoomService $roomService)
    {
        $rooms = Room::all();

        if (empty($rooms)) {
            return $this->sendResponse(null, 'Empty rooms list');
        }

        $success['rooms'] = $roomService->prepareShowRoomList($rooms);

        return $this->sendResponse($success, 'Rooms retrieved successfully.');
    }

    /**
     * @param Request $request
     * @param RoomService $roomService
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, RoomService $roomService)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'name' => 'required|unique:rooms,name|max:191',
            'type' => [
                'required',
                Rule::in(['private', 'public']),
            ],
            'logo' => 'image|max:4096'
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        $roomDto = (array)$roomService->prepareRoom($request, $user);

        $room = Room::create($roomDto);
        $success['room'] = $room;

        return $this->sendResponse($success, 'Room created successfully.');
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id, RoomService $roomService)
    {
        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        $room = Room::find($id);

        if (is_null($room)) {
            return $this->sendError('Room not found.');
        }

        $success['room'] = $roomService->prepareShowRoomItem($room, $roomService);

        return $this->sendResponse($success, 'Room retrieved successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $input = $request->post();
        $triger = 0;
        if (key_exists('name', $input) && empty($input['name'])) {
            $triger = 1;
        }

        $validator = Validator::make($input, [
            'logo' => 'image|max:4096',
            'type' => [
                Rule::in(['private', 'public']),
            ],
            'name' => function($key, $value) use ($id) {
                if (!is_string($value)) {
                    return false;
                }

                if (strlen($value) > 191) {
                    return false;
                }
            }
        ]);

        $validator->after(function($validator) use ($input, $id, $triger)
        {
            if (isset($input['name']) && $this->checkUniqueRoomName($input['name'], $id))
            {
                $validator->errors()->add('name', 'Name must be unique!');
            }

            if ($triger == 1) {
                $validator->errors()->add('name', 'Name can`t be empty!');
            }
        });

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $room = Room::find($id);

        if (!$room) {
            return $this->sendError('Room validation.', 'Room not found', 404);
        }

        if (isset($input['name'])) {
            $room->name = $input['name'];
        }

        if (isset($request->logo) && !empty($request->logo)) {
            $destinationPath = 'logoItems/';

            $imgService = new ImageService();
            $host = $request->getHost();
            $imgService->removeImg($room->logo, $host);

            $logo = $request->file('logo');
            $profileImage = date('YmdHis') . "." . $logo->getClientOriginalExtension();
            $logo->move($destinationPath, $profileImage);

            $room->logo = 'https://' . $request->getHost() . '/' . $destinationPath . $profileImage;
        }

        if (isset($input['type'])) {
            $room->type = $input['type'];
        }

        $room->save();
        $success['room'] = $room;

        return $this->sendResponse($success, 'Room updated successfully.');
    }

    /**
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        $room = Room::find($id);

        if (!$room) {
            return $this->sendError('Validation error.', 'Room not found');
        }

        if ($user->id != $room->ownerId) {
            return $this->sendError('Access fail.', 'You are not an owner');
        }

        $imgService = new ImageService();
        $host = $request->getHost();

        $imgService->removeImg($room->logo, $host);

        AdminList::where('room_id', $room->id)->delete();

        $listOfPlayList = Playlist::where('roomId', $room->id)->get();

        foreach ($listOfPlayList as $item) {
            $imgService->removeImg($item->logo, $host);
        }

        Playlist::where('roomId', $room->id)->delete();
        $room->delete();

        $success['room'] = $room;

        return $this->sendResponse($success, 'Room deleted successfully.');
    }

    public function addAdmin(Request $request, RoomService $service)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'email' => 'required|exists:users,email',
            'roomId' => 'required|exists:rooms,id',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);
        $admin = User::findByEmail($input['email']);

        if ($user->email == $admin->email) {
            return $this->sendError('Action failed', 'Admin has to be another person!');
        }

        if (!$service->checkUserIsOwner($user, $input['roomId'])) {
            return $this->sendError('Failed.', 'You are not an owner for this room');
        }

        if (AdminList::where('user_id', $admin->id)
            ->where('room_id', $input['roomId'])
            ->exists()
        ) {
            return $this->sendError('Failed.', 'Admin already exists');

        }

        AdminList::create(['user_id' => $admin->id, 'room_id' => $input['roomId']]);

        $room = Room::find($input['roomId']);
        $success['room'] = $service->prepareShowRoomItem($room, $service);

        return $this->sendResponse($success, 'Admin added successfully.');
    }

    /**
     * @param Request $request
     * @param RoomService $service
     * @return \Illuminate\Http\Response
     */
    public function removeAdmin(Request $request, RoomService $service)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'email' => 'required|exists:users,email',
            'roomId' => 'required|exists:rooms,id',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);
        $admin = User::findByEmail($input['email']);

        if (!$service->checkUserIsOwner($user, $input['roomId'])) {
            return $this->sendError('Failed.', 'You are not an owner for this room');
        }

        if (!$adminItem = AdminList::where('user_id', $admin->id)
            ->where('room_id', $input['roomId'])
            ->first()
        ) {
            return $this->sendError('Failed.', 'Admin with this email not found');
        }

        $adminItem->delete();

        $room = Room::find($input['roomId']);
        $success['room'] = $service->prepareShowRoomItem($room, $service);

        return $this->sendResponse($success, 'Admin removed successfully.');
    }

    public function addInvite(Request $request, RoomService $service)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'email' => 'required|exists:users,email',
            'roomId' => 'required|exists:rooms,id',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);
        $invited = User::findByEmail($input['email']);

        if ($user->email == $invited->email) {
            return $this->sendError('Action failed', 'Invited has to be another person!');
        }

        if (!$service->checkUserIsOwner($user, $input['roomId'])) {
            return $this->sendError('Failed.', 'You are not an owner for this room');
        }

        if (UserRoom::where('user_id', $invited->id)
            ->where('roomId', $input['roomId'])
            ->exists()
        ) {
            return $this->sendError('Failed.', 'Invited already exists');

        }

        UserRoom::create(['user_id' => $invited->id, 'roomId' => $input['roomId']]);

        $room = Room::find($input['roomId']);
        $success['room'] = $service->prepareShowRoomItem($room, $service);

        return $this->sendResponse($success, 'Invite added successfully.');
    }

    /**
     * @param Request $request
     * @param RoomService $service
     * @return \Illuminate\Http\Response
     */
    public function removeInvite(Request $request, RoomService $service)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'email' => 'required|exists:users,email',
            'roomId' => 'required|exists:rooms,id',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);
        $invited = User::findByEmail($input['email']);

        if (!$service->checkUserIsOwner($user, $input['roomId'])) {
            return $this->sendError('Failed.', 'You are not an owner for this room');
        }

        if (!$invitedItem = UserRoom::where('user_id', $invited->id)
            ->where('roomId', $input['roomId'])
            ->first()
        ) {
            return $this->sendError('Failed.', 'Invited with this email not found');
        }

        DB::table('users_rooms')
            ->where('user_id', $invited->id)
            ->where('roomId', $input['roomId'])
            ->delete();

        $room = Room::find($input['roomId']);
        $success['room'] = $service->prepareShowRoomItem($room, $service);

        return $this->sendResponse($success, 'Invited removed successfully.');
    }

    /**
     * @param $name
     * @param $id
     * @return bool
     */
    private function checkUniqueRoomName($name, $id)
    {
        $rooms = Room::where('name', $name)->first();

        if (empty($rooms)) {
            return false;
        }

        if ($rooms->id == $id) {
            return false;
        }

        return true;
    }

    /**
     * @param Request $request
     * @param RoomService $roomService
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request, RoomService $roomService)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'search' => 'required|string|max:199',
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
        $includeUserName = $roomService->getRoomsByUserName($search);
        $includeUserEmail = $roomService->getRoomsByUserEmail($search);
        $includeRoomName = $roomService->getRoomsByName($search);
        $includePlayListName = $roomService->getRoomsByPlaylistName($search, $user);
        $includePlayListDescription = $roomService->getRoomsByPlaylistDescription($search, $user);
        $includeSongName = $roomService->getRoomsBySongName($search, $user);
        $includeSongAuthors = $roomService->getRoomsBySongAuthors($search, $user);

        $rooms = [
            'includeUserName' => $includeUserName,
            'includeUserEmail' => $includeUserEmail,
            'includeRoomName' => $includeRoomName,
            'includePlayListName' => $includePlayListName,
            'includePlayListDescription' => $includePlayListDescription,
            'includeSongName' => $includeSongName,
            'includeSongAuthors' => $includeSongAuthors
        ];

        $success['rooms'] = $rooms;

        return $this->sendResponse($success, 'Rooms retrieved successfully.');
    }
}
