<?php

namespace App\Http\Controllers;

use App\Http\Services\RegistrationService;
use App\User;
use Laravel\Socialite\Facades\Socialite;

class SocialController extends Controller
{
    public function redirect($provider)
    {
        $driver = Socialite::driver($provider);

        return $driver->redirect();
    }

    public function callback($provider)
    {
        $getInfo = Socialite::driver($provider)->user();
        $user = $this->createUser($getInfo,$provider);
        auth()->login($user);

        return redirect()->to('/home');
    }

    function createUser($getInfo,$provider){
        $registrationService = new RegistrationService();
        $user = $registrationService->getUserBySocialData($provider, $getInfo->id);

        if ($user) {
            $user = $registrationService->checkUserToken($user);

            return $user;
        }

        if ($getInfo->email) {
            $user = User::where('email', $getInfo->email)->first();

            if ($user) {
                $user = $registrationService->checkUserToken($user);
                $registrationService->setSocialData($getInfo, $user, $provider);

               return $user;
            }

            $user = $registrationService->userGenerate($getInfo, $provider);
        }

        return $user;
    }
}
