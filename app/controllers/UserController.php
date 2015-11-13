<?php

use LucaDegasperi\OAuth2Server\Authorizer;
use Rootant\Api\Exception\ValidationException;
use Rootant\Api\Exception\ResourceNonExistentException;

class UserController extends CommonController
{
    public function __construct(Authorizer $authorizer)
    {
        // parent::__construct($authorizer);
        // $this->beforeFilter('oauth', ['except' => ['store']]);
        // $this->beforeFilter('oauth.checkClient', ['only' => 'store']);
        // $this->beforeFilter('validation');
        // $this->afterFilter('disconnect:mysql', ['only' => ['store', 'show', 'modify']]);
        // $this->afterFilter('disconnect:major', ['only' => ['hasNewInformation', 'removeNotice', 'store', 'modify', 'logout', 'myComment', 'myStar', 'myInformation']]);
    }

    // private static $_validate = [
    //     'store' => [
    //         'username' => 'required|min:5|unique:user,name',
    //         'email'    => 'required|email|unique:user',
    //         'password' => 'required|min:6|confirmed',
    //     ],
    // ];

    public function logout()
    {
        $oauthAccessToken = $this->dbRepository('major', 'oauth_access_tokens');

        $oauthAccessToken->where('id', $this->accessToken)->delete();

        return Response::make('', 204);
    }

    public function modify()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $this->email = DB::table('user')->select('email')->find($uid)->email;

        $validator = Validator::make(Input::all(),
                array(
                        'name'  => 'min:5|unique:user,name,'.$uid,
                        'email' => 'email|unique:user,email,'.$uid,
                    )
            );

        if ($validator->fails()) {
            throw new ValidationException($validator->messages()->all());
        }

        $user = User::find($uid);

        $allowedFields = ['avatar_url', 'name', 'email', 'address'];

        array_walk($allowedFields, function($item) use ($user, $uid) {
            $v = Input::get($item);
            if ($v && $item !== 'avatar_url') {
                $user->$item = $v;
            }
            if (Input::has('email')) {
                $this->updateThirdPartyUser($this->email, Input::get('email'));
            }
            if (Input::hasFile('avatar_url')) {
                $user->avatar_url = UserAvatarController::uploadAvatar($uid);
            }
        });

        $user->save();

        return $user;
    }

}