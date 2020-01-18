<?php

namespace App;

use App\Notifications\SendPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\File;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'isShowEmail',
        'password',
        'token',
        'phone',
        'isShowPhone',
        'preferences',
        'isShowPreferences',
        'email_verified_at',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'remember_token', 'password', 'token', 'updated_at'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * @return string
     */
    public static function tokenGenerate()
    {
        $token = md5(uniqid(rand(),1));

        return $token;
    }


    /**
     * @param $token
     * @return |null
     */
    public static function findByToken($token)
    {
        if (!$token) {
            return null;
        }

        return User::where('token', $token)->first();
    }

    /**
     * @param $id
     */
    public static function clearToken($id)
    {
        User::where('id', $id)->update(['token' => null]);
    }


    /**
     * @param $email
     * @return |null
     */
    public static function findByEmail($email)
    {
        if (!$email) {
            return null;
        }

        return User::where('email', $email)->first();
    }


    /**
     * @param $token
     * @param $email
     */
    public static function updateToken($token, $email)
    {
        User::where('email', $email)->update(['token' => $token]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function socialAccounts(){
        return $this->hasMany(SocialAccount::class, 'user_id', 'id');
    }

    /**
     * @param string $token
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * Send user password for verification
     * @param $psw
     *
     */
    public function sendPasswordNotification($psw)
    {
        $this->notify(new SendPassword($psw));
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function updatePersonalData(Request $request)
    {
        $input = $request->post();

        if (isset($input['name'])) {
            $this->name = $input['name'];
        }

        if (isset($input['email'])) {
            $this->email = $input['email'];
            $this->email_verified_at = null;
        }

        if (isset($input['phone'])) {
            $this->phone = $input['phone'];
        }

        if (isset($input['preferences'])) {
            $this->preferences = $input['preferences'];
        }

        if (isset($input['isShowPhone'])) {
            $this->isShowPhone = $input['isShowPhone'];
        }

        if (isset($input['isShowEmail'])) {
            $this->isShowEmail = $input['isShowEmail'];
        }

        if (isset($input['isShowPreferences'])) {
            $this->isShowPreferences = $input['isShowPreferences'];
        }

        if (isset($input['password'])) {
            $this->password = bcrypt($input['password']);
        }

        if (isset($request->avatar) && !empty($request->avatar)) {
            if (!is_null($this->avatar)) {
                File::delete($this->avatar);
            }

            $avatar = $request->file('avatar');
            $destinationPath = 'avatars/';
            $profileImage = date('YmdHis') . "." . $avatar->getClientOriginalExtension();
            $avatar->move($destinationPath, $profileImage);

            $this->avatar = $destinationPath . $profileImage;
        }

        $this->save();

        return $this;
    }

    /**
     * @return int
     */
    public function isNeedEmailVerification() :int
    {
        return is_null($this->email_verified_at) ? 1 : 0;
    }

    public function isUserHasDeezerVerificatedAccount()
    {
        $deezerAccount = SocialAccount::where('provider', 'deezer')->where('user_id', $this->id)->first();

        return $deezerAccount->emailVerificated;
    }

    public function roomWhereAdminList()
    {
        $this->hasManyThrough('App\Room', 'App\AdminList', 'user_id', 'ownerId');
    }
}
