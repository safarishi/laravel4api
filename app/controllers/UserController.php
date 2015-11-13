<?php

use LucaDegasperi\OAuth2Server\Authorizer;
use Rootant\Api\Exception\ValidationException;
use Rootant\Api\Exception\ResourceNonExistentException;

class UserController extends CommonController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->beforeFilter('oauth', ['except' => ['store']]);
        $this->beforeFilter('oauth.checkClient', ['only' => 'store']);
        $this->beforeFilter('validation');
        $this->afterFilter('disconnect:mysql', ['only' => ['store', 'show', 'modify']]);
        $this->afterFilter('disconnect:major', ['only' => ['hasNewInformation', 'removeNotice', 'store', 'modify', 'logout', 'myComment', 'myStar', 'myInformation']]);
    }

    private static $_validate = [
        'store' => [
            'username' => 'required|min:5|unique:user,name',
            'email'    => 'required|email|unique:user',
            'password' => 'required|min:6|confirmed',
        ],
    ];


    public function store()
    {
        $email = Input::get('email');
        $password = Input::get('password');

        $avatarUrl = $this->getAvatarUrl();

        $insertData = array(
                'name'        => Input::get('username'),
                'avatar_url'  => $avatarUrl,
                'password'    => md5($password),
                'email'       => $email,
                'personal_id' => MultiplexController::uuid(),
            );

        $user = DB::table('user');

        $insertId = $user->insertGetId($insertData);

        // 绑定第三方登录用户的信息
        $this->bindThirdPartyUser($email, $password);

        return (array) $user->find($insertId);
    }

    protected function getAvatarUrl()
    {
        $avatarUrl = Config::get('imagecache::paths.avatar_url_prefix').'/default.png';

        if (!Input::has('avatar_url')) {
            return $avatarUrl;
        }

        $avatar = Input::get('avatar_url');

        if (preg_match('#^http(s)?://#', $avatar) === 1) {
            $avatarUrl = $avatar;
        }

        return $avatarUrl;
    }

    /**
     * [bindThirdPartyUser description]
     * @param  string $username 用户名
     * @param  string $password 密码
     * @return void
     */
    protected function bindThirdPartyUser($username, $password)
    {
        if (!Input::has('token')) {
            return;
        }

        $token = Input::get('token');

        if (strlen($token) !== 30) {
            return;
        }

        $insertData = array(
                'token'      => $token,
                'username'   => $username,
                'password'   => $password,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            );

        $this->dbRepository('major', 'third_party_user_token')->insert($insertData);
    }

    public function logout()
    {
        $oauthAccessToken = $this->dbRepository('major', 'oauth_access_tokens');

        $oauthAccessToken->where('id', $this->accessToken)->delete();

        return Response::make('', 204);
    }

    public function myComment()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $comment = $this->dbRepository('major', 'journal_comment');
        $commentModel = $comment->where('user_id', $uid)
            ->orderBy('created_at', 'desc');
        // 增加数据分页
        $this->addPagination($commentModel);

        $returnData = $commentModel->get();

        $this->flag = self::JOURNAL_FLAG;

        return $this->processCommentResponse($returnData);
    }

    public function myStar()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $starModel = $this->dbRepository('major', 'star')
            ->select('journal_id')
            ->where('user_id', $uid)
            ->orderBy('created_at', 'desc');
        // 增加分页
        $this->addPagination($starModel);
        $star = $starModel->get();

        return $this->processMyStar($star);
    }

    protected function processMyStar($data)
    {
        $ids = array();
        foreach ($data as $value) {
            $ids[] = $value->journal_id;
        }

        $journals = $this->journal()
            ->whereIn('id', $ids)
            ->get();

        foreach ($data as $v) {
            foreach ($journals as $vv) {
                if ($vv->id === $v->journal_id) {
                    $vv->thumbnail_url = self::RESOURCE_URL.'/'.$vv->thumbnail_url;
                    $v->journal = $vv;
                }
            }
        }

        $returnData = array();
        foreach ($data as $value) {
            $returnData[] = $value->journal;
        }

        return $returnData;
    }

    public function show()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        return (array) $this->user()->find($uid);
    }

    public function myInformation()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $model = $this->dbRepository('major', 'information')
            ->select('id', 'from_uid', 'from_user_ip', 'created_at', 'content', 'type')
            ->where('to_uid', $uid)
            ->orderBy('created_at', 'desc');

        $this->addPagination($model);

        $information = $model->get();

        return $this->processMyInformation($information);
    }

    protected function processMyInformation($information)
    {
        foreach ($information as $v) {
            $this->userId = $v->from_uid;
            $this->userIp = $v->from_user_ip;
            $v->user = $this->getOwner();

            if ($v->type === 0) {
                // 消息为系统消息
                $v->content_info = ['system' => $v->content];
            } else {
                // 消息是序列化后的消息，则需要反序列
                $v->content_info = unserialize($v->content);
            }
        }

        foreach ($information as $v) {
            unset($v->from_uid, $v->from_user_ip, $v->type, $v->content);
        }

        return $information;
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

    /**
     * [updateThirdPartyUser description]
     * @param  string $rawEmail 旧邮箱
     * @param  string $newEmail 新邮箱
     * @return void
     */
    protected function updateThirdPartyUser($rawEmail, $newEmail)
    {
        $this->dbRepository('major', 'third_party_user_token')
            ->where('username', $rawEmail)
            ->update(array('username' => $newEmail, 'updated_at' => date('Y-m-d H:i:s')));
    }

    /**
     * 用户是否有新消息
     *
     * @api get /v3/notices
     * @return array
     */
    public function hasNewInformation()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $flag = $this->dbRepository('major', 'information')
            ->where('unread_uid', $uid)
            ->exists();

        return array('new_information' => $flag);
    }

    /**
     * 点击小红点
     * 取消红点
     *
     * @api delete /v3/user/notices
     * @return object
     */
    public function removeNotice()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $updateData = array(
                'unread_uid' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            );

        $this->dbRepository('major', 'information')
            ->where('unread_uid', $uid)
            ->update($updateData);

        return Response::make('', 204);
    }

}