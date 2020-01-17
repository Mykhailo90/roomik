<?php

namespace App\Http\Controllers\Api;

use App\Http\Services\RegistrationService;
use App\SocialAccount;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends BaseController
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
     * @return \Illuminate\Http\Response
     */
    public function getInfo(Request $request, RegistrationService $registrationService)
    {
        if ($input = $request->post()) {
            $validator = Validator::make($input, [
                'paramName' => [
                    'required',
                    Rule::in(['email', 'name']),
                ],
                'email' => [
                    'email',
                    'max:191',
                    Rule::requiredIf(function () use ($input) {
                        return (isset($input['paramName']) && $input['paramName'] == 'email');
                    }),
                ],
                'name' => [
                    'string',
                    'max:191',
                    Rule::requiredIf(function () use ($input) {
                        return (isset($input['paramName']) && $input['paramName'] == 'name');
                    }),
                ]
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error. ', $validator->errors(), 403);
            }

            $user = User::where($input['paramName'], $input[$input['paramName']])->first();

            if (!$user) {
                return $this->sendError('User not found', 'Unknown user with ' .
                    $input['paramName'] . ' ' . $input[$input['paramName']]);
            }

            $success['user'] = $registrationService->prepareUserResponse($user);

            return $this->sendResponse($success, 'User received successfully.');
        } else {
            $access_token = $request->header('token');
            $user = User::findByToken($access_token);
            $success['user'] = $registrationService->prepareUserResponse($user);

            return $this->sendResponse($success, 'User received successfully.');
        }
    }


    /**
     * @param User $user
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(User $user)
    {
        // Удалить все связанные с пользователем данные
        // TODO
        $user->delete();
        $success['user'] = $user;

        return $this->sendResponse($success, 'User deleted successfully.');
    }

    public function addProviderInfo(Request $request, RegistrationService $registrationService)
    {
        $input = $request->toArray();

        $validator = Validator::make($input, [
            'provider' => 'required|string|max:191',
            'user_provider_id' => 'required|string|max:191',
            'user_provider_token' => 'string|max:1000'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error. ', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        $socialUser = SocialAccount::where('provider', $input['provider'])
            ->where('provider_id', $input['user_provider_id'])->first();

        if ($socialUser) {
            return $this->sendError('Add social account failed.', 'User account already exists');
        }

        $verificated = 1;
        if ($input['provider'] == 'deezer') {
            $verificated = $user->isNeedEmailVerification() ? 0 : 1;
        }

        SocialAccount::create([
            'user_id' => $user->id,
            'provider_id' => $input['user_provider_id'],
            'user_provider_token' => $input['user_provider_token'] ?? null,
            'provider' => $input['provider'],
            'emailVerificated' => $verificated
        ]);

        $success['user'] = $registrationService->prepareUserResponse($user);

        return $this->sendResponse($success, 'User received successfully.');
    }

    /**
     * @param User $user
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroySocialAccount(Request $request, RegistrationService $registrationService)
    {
        $input = $request->toArray();

        $validator = Validator::make($input, [
            'provider' => 'required|string|max:191',
            'user_provider_id' => 'required|string|max:191|exists:social_users,provider_id',
            'user_provider_token' => 'string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error. ', $validator->errors());
        }

        $access_token = $request->header('token');
        $user = User::findByToken($access_token);

        $socialUser =
            SocialAccount::where('user_id', $user->id)
            ->where('provider', $input['provider'])
            ->where('provider_id', $input['user_provider_id'])->first();

        if (!$socialUser) {
            return $this->sendError('Remove social account failed.', 'User account not found');
        }

        $socialUser->delete();

        $success['user'] = $registrationService->prepareUserResponse($user);

        return $this->sendResponse($success, 'Social account deleted successfully.');
    }
}
