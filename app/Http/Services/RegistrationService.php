<?php

namespace App\Http\Services;

use App\Http\DTO\UserResponseDto;
use App\SocialAccount;
use App\User;
use App\UserRoom;
use Illuminate\Support\Facades\Request;

class RegistrationService
{
    public function getUserBySocialData (string $provider, string $id)
    {
        $socialUser = SocialAccount::where(['provider_id' => $id])
            ->where(['provider' => $provider])
            ->first();

        if (!$socialUser) {
            return null;
        }

        $user = User::where('id', $socialUser->user_id)->first();

        return $user;
    }

    public function checkUserToken(User $user)
    {
        if (is_null($user->token)) {
            $user->token = $user->tokenGenerate();

            $user->save();
        }

        return $user;
    }

    public function getUserByEmail($email)
    {
        return User::where('email', $email)->first();
    }

    public function userGenerate($providerData, $provider)
    {
        $user = User::create([
            'name'     => $providerData->name,
            'email'    => $providerData->email,
            'token' => User::tokenGenerate(),
            'email_verified_at' => ($provider == 'deezer') ? null : time(),
            'password' => ''
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $providerData->id,
            'user_provider_token' => $providerData->user_provider_token ?? null,
            'emailVerificated' => ($provider == 'deezer') ? 0 : 1
        ]);

        return $user;
    }

    public function setSocialData($userInfo, $user, $provider)
    {
        // Проверить, если запись есть просто выйти, если нет - добавить
        $account = SocialAccount::where('provider', $provider)->where('user_id', $user->id)->first();
        if ($account) {
            return;
        }

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $userInfo->id,
            'user_provider_token' => $userInfo->user_provider_token ?? null,
            'emailVerificated' => ($provider == 'deezer') ? 0 : 1
        ]);
    }

    /**
     * @param User $user
     * @return UserResponseDto
     */
    public function prepareUserResponse(User $user): UserResponseDto
    {
        $user = User::find($user->id);
        $dto = new UserResponseDto();
        $roomService = new RoomService();

        $dto->id = $user->id;
        $dto->name = $user->name;
        $dto->avatar = 'https://'. Request::getHttpHost() . '/' . $user->avatar;
        $dto->email = $user->email;
        $dto->phone = $user->phone;
        $dto->preferences = $user->preferences;
        $dto->isShowEmail = $user->isShowEmail;
        $dto->isShowPhone = $user->isShowPhone;
        $dto->isShowPreferences = $user->isShowPreferences;
        $dto->token = $user->token;
        $dto->socialAccounts = $user->socialAccounts;
        $dto->rooms = $roomService->getRoomsByUserId($user->id);
        $dto->invitingRooms = $roomService->getInvitingRoomsByUserId($user->id);
        $dto->isNeedVerificationEmail = $user->isNeedEmailVerification();
        $dto->roomsWhereAdminList = $roomService->getRoomsWhereAdminList($user->id);

        return $dto;
    }
}
