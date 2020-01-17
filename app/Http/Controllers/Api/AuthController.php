<?php

namespace App\Http\Controllers\Api;

use App\Http\Services\RegistrationService;
use App\Http\Services\RoomService;
use App\SocialAccount;
use App\User;
use App\Verification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;
use phpDocumentor\Reflection\Types\Object_;

class AuthController extends BaseController
{
    /**
     * @param Request $request
     * @param RegistrationService $registrationService
     * @return \Illuminate\Http\Response
     */
    public function loginBySocial(Request $request, RegistrationService $registrationService)
    {
        $input = $request->toArray();

        $validator = Validator::make($request->post(), [
            'provider' => 'required|string|max:191',
            'user_provider_id' => 'required|string|max:191',
            'user_provider_token' => 'string|max:1000',
            'access_token' => [
                'string',
                Rule::requiredIf(function () use ($input) {
                    return ($input['provider'] != 'google');
                }),
            ],
            'name' => [
                'string',
                'max:191',
                Rule::requiredIf(function () use ($input) {
                    return ($input['provider'] == 'google');
                }),
            ],
            'email' => [
                'email',
                'max:191',
                Rule::requiredIf(function () use ($input) {
                    return ($input['provider'] == 'google');
                }),
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error. ', $validator->errors());
        }

        if ($input['provider'] == 'google') {
            $user = $registrationService->getUserByEmail($input['email']);

            if ($user) {
                $user = $registrationService->checkUserToken($user);

                $userInfo = new Object_();
                $userInfo->id = $input['user_provider_id'];
                $userInfo->user_provider_token = $input['user_provider_token'] ?? null;

                $registrationService->setSocialData($userInfo, $user, $input['provider']);
                $success['user'] = $registrationService->prepareUserResponse($user);

                return $this->sendResponse($success, 'User login successfully.');
            }

            $userInfo = new Object_();
            $userInfo->id = $input['user_provider_id'];
            $userInfo->user_provider_token = $input['user_provider_token'] ?? '';
            $userInfo->name = $input['name'];
            $userInfo->email = $input['email'];

            $user = $registrationService->userGenerate($userInfo, $input['provider']);
            $success['user'] = $registrationService->prepareUserResponse($user);

            return $this->sendResponse($success, 'User register successfully.');
        }

        $user = $registrationService->getUserBySocialData($input['provider'], $input['user_provider_id']);

        if ($user) {
            $user = $registrationService->checkUserToken($user);
            $userResponseObject = $registrationService->prepareUserResponse($user);

            // If provider is Deezer we have to check email!!! IMPORTANT!!!
            if ($input['provider'] == 'deezer') {
                $userResponseObject->isNeedVerificationEmail =
                    ($user->isUserHasDeezerVerificatedAccount()) ? $userResponseObject->isNeedVerificationEmail : 2;
            }

            $success['user'] = $userResponseObject;

            return $this->sendResponse($success, 'User login successfully.');
        }

        try {
            $socialDriver = Socialite::driver($input['provider']);
            $userInfo = $socialDriver->userFromToken($input['access_token']);
            $userInfo->user_provider_token = $input['user_provider_token'] ?? null;
        } catch (\Exception $e) {
            return $this->sendError('Unauthorised.', 'Invalid credentials data');
        }

        if (!is_null($userInfo->email)) {
            $user = $registrationService->getUserByEmail($userInfo->email);

            if ($user) {
                $user = $registrationService->checkUserToken($user);
                $registrationService->setSocialData($userInfo, $user, $input['provider']);
                $userResponseObject = $registrationService->prepareUserResponse($user);

                // If provider is Deezer we have to check email!!! IMPORTANT!!!
                if ($input['provider'] == 'deezer') {
                    $userResponseObject->isNeedVerificationEmail =
                        ($user->isUserHasDeezerVerificatedAccount()) ? $userResponseObject->isNeedVerificationEmail : 2;
                }

                $success['user'] = $userResponseObject;
                return $this->sendResponse($success, 'User login successfully.');
            }

            $user = $registrationService->userGenerate($userInfo, $input['provider']);
            $userResponseObject = $registrationService->prepareUserResponse($user);

            // If provider is Deezer we have to check email!!! IMPORTANT!!!
            if ($input['provider'] == 'deezer') {
                $userResponseObject->isNeedVerificationEmail =
                    ($user->isUserHasDeezerVerificatedAccount()) ? $userResponseObject->isNeedVerificationEmail : 2;
            }

            $success['user'] = $userResponseObject;

            return $this->sendResponse($success, 'User register successfully.');
        }

        return $this->sendError('Unauthorised.', 'Wrong email field! Email can not be empty');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->post(), [
            'name' => 'required|string|max:191',
            'email' => 'required|email|unique:users,email|max:191',
            'password' => 'required|string|min:4|max:64',
            'c_password' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $input = $request->post();
        $input['password'] = bcrypt($input['password']);
        $input['token'] = User::tokenGenerate();
        $user = User::create($input);
        $registrationService = new RegistrationService();
        $success['user'] = $registrationService->prepareUserResponse($user);

        return $this->sendResponse($success, 'User register successfully.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $credentials = $request->only(['email', 'password']);

        $user = User::findByEmail($credentials['email']);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Wrong email! User Not Found.');
        }

        if (password_verify($credentials['password'], $user->password)) {
            $token = $user->token;

            if (is_null($token)) {
                $token = User::tokenGenerate();
                User::updateToken($token, $credentials['email']);
                $user->token = $token;
            }

            $registrationService = new RegistrationService();
            $success['user'] = $registrationService->prepareUserResponse($user);

            return $this->sendResponse($success, 'User login successfully.');
        }

        return $this->sendError('Unauthorised.', 'Wrong password');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        User::clearToken($user->id);

        return $this->sendResponse(null, 'User logout successfully.');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function updatePersonalInfo(Request $request)
    {
        $input = $request->post();
        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        if (is_null($user)) {
            return $this->sendError('Unauthorised.', 'Invalid Token');
        }

        $validator = Validator::make($input, [
            'name' => 'string|max:191',
            'email'  =>  'email|unique:users,email,'. $user->id,
            'password' => 'string|required_with:c_password|min:4|max:64',
            'c_password' => 'required_with:password|same:password',
            'preferences' => 'string|max:100',
            'isShowPhone' => Rule::in([0, 1]),
            'isShowEmail' => Rule::in([0, 1]),
            'isShowPreferences' => Rule::in([0, 1]),
            'phone' => 'phone:AUTO',
            'avatar' => 'image|max:4096'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try {
            $user = $user->updatePersonalData($request);
            $roomService = new RoomService();
            $roomService->updateUserInfo($user);
        } catch (\Exception $e) {
            return $this->sendError('User update FAIL. ', $e->getMessage());
        }

        $registrationService = new RegistrationService();
        $success['user'] = $registrationService->prepareUserResponse($user);

        return $this->sendResponse($success, 'User update personal data successfully.');
    }

    public function sendPasswordCode(Request $request)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'email' => 'required|exists:users,email'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $user = User::findByEmail($input['email']);

        if (is_null($user)) {
            return $this->sendError('Failed.', 'Wrong email! User Not Found.');
        }

        $tmpPsw = rand(100000, 999999);

        $verification = Verification::where('email', $input['email'])->first();

        if (is_null($verification)) {
            Verification::create([
                'email' => $input['email'],
                'token' => $tmpPsw,
                'created_at' => date('Y-m-d H:m:s', time())
            ]);
        } else {
            DB::table('password_resets')
                ->where('email', $input['email'])
                ->update([
                    'token' => $tmpPsw,
                    'created_at' => date('Y-m-d H:m:s', time())
                ]);
        }

        $user->sendPasswordNotification($tmpPsw);

        return $this->sendResponse('Success', 'Password send successfully.');
    }

    public function passwordVerification(Request $request, RegistrationService $registrationService)
    {
        $input = $request->post();

        $validator = Validator::make($input, [
            'email' => 'required|exists:users,email',
            'action' => [
                'required',
                Rule::in(['resetPassword', 'emailVerification', 'deezerVerification'])
            ],
            'code' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $verification =
            Verification::where('email', $input['email'])->where('token', $input['code'])->first();

        if (is_null($verification)) {
            return $this->sendError('Failed.', 'Invalid Password');
        } else {
            DB::table('password_resets')
                ->where('email', $input['email'])
                ->where('token', $input['code'])
                ->delete();
        }

        $user = User::findByEmail($input['email']);

        switch ($input['action']) {
            case 'resetPassword':
                $user->token = $user->tokenGenerate();
                $user->save();
                $success['user'] = $registrationService->prepareUserResponse($user);

                return $this->sendResponse($success, 'Password validate successfully.');
            case 'emailVerification':
                $user->email_verified_at = date('Y-m-d H:m:s', time());
                $user->save();
                $success['user'] = $registrationService->prepareUserResponse($user);

                return $this->sendResponse($success, 'Password validate successfully.');
            case 'deezerVerification':
                $user->email_verified_at = date('Y-m-d H:m:s', time());
                $user->save();

                $socialUser =
                    SocialAccount::where('user_id', $user->id)->where('provider', 'deezer')->first();

                if (is_null($socialUser)) {
                    return $this->sendError('Failed.', 'Deezer account not found.');
                }

                $socialUser->emailVerificated = 1;
                $socialUser->save();

                $success['user'] = $registrationService->prepareUserResponse($user);

                return $this->sendResponse($success, 'Password validate successfully.');
        }
    }
}
