<?php

use Rootant\Api\Exception\ValidationException;

class ThirdPartyLoginController extends Controller
{
    const INDEX_URI = 'web/index.html#/index/home';

    const THIRD_PARTY_BIND_URI = 'web/index.html#/index/thirdPartyLogin';

    protected $curlMethod = 'GET';

    public function __construct()
    {
        $this->afterFilter('disconnect:major', ['only' => ['entry', 'weiboCallback']]);
    }

    /**
     * 生成第三方跳转 url
     *
     * @param  string $type 第三方类型
     * @return todo
     */
    public function redirectUrl($type)
    {
        $this->type = $type;

        return $this->generateUrl();
    }

    protected function generateUrl()
    {
        $config = Config::get('services.'.$this->type);

        switch ($this->type) {
            case 'weibo':
                $url = 'https://api.weibo.com/oauth2/authorize?client_id='.
                    $config['AppId'].'&redirect_uri='.
                    urlencode($config['CallbackUrl']).'&response_type=code';
                break;
            case 'qq':
                $url = 'https://graph.qq.com/oauth2.0/authorize?response_type=code&client_id='.
                    $config['AppId'].'&redirect_uri='.
                    urlencode($config['CallbackUrl']).'&state=test';
                break;
            case 'weixin':
                $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.
                    $config['AppId'].'&redirect_uri='.
                    urlencode($config['CallbackUrl']).'&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect';
                break;
            default:
                # code...
                break;
        }

        return $url;
    }

    public function weiboCallback()
    {
        $this->type = 'weibo';

        $openId = $this->getOpenId();

        $result = $this->hasOpenId($openId);
        if ($result) {
            return Redirect::to($result);
        }

        // 拉取用户信息
        $user = $this->fetchUser($openId);

        $avatarUrl = $user->avatar_hd ? $user->avatar_hd : $user->avatar_large;

        $tmpToken = MultiplexController::uuid();

        $this->storeOpenId($openId, $tmpToken);

        $queryString = '?name='.$user->name.'&avatar_url='.$avatarUrl.'&token='.$tmpToken;

        return Redirect::to(self::THIRD_PARTY_BIND_URI.$queryString);
    }

    public function qqCallback()
    {
        $this->type = 'qq';

        $openId = $this->getOpenId();

        $result = $this->hasOpenId($openId);
        if ($result) {
            return Redirect::to($result);
        }

        // 拉取用户信息
        $user = $this->fetchUser($openId);

        $avatarUrl = $user->figureurl_qq_2 ? $user->figureurl_qq_2 : $user->figureurl_2;

        $tmpToken = MultiplexController::uuid();

        $this->storeOpenId($openId, $tmpToken);

        $queryString = '?name='.$user->nickname.'&avatar_url='.$avatarUrl.'&token='.$tmpToken;

        return Redirect::to(self::THIRD_PARTY_BIND_URI.$queryString);
    }

    public function weixinCallback()
    {
        $this->type = 'weixin';

        $openId = $this->getOpenId();

        $result = $this->hasOpenId($openId);
        if ($result) {
            return Redirect::to($result);
        }

        // 拉取用户信息
        $user = $this->fetchUser($openId);

        $tmpToken = MultiplexController::uuid();

        $this->storeOpenId($openId, $tmpToken);

        $queryString = '?name='.$user->nickname.'&avatar_url='.$user->headimgurl.'&token='.$tmpToken;

        return Redirect::to(self::THIRD_PARTY_BIND_URI.$queryString);
    }

    /**
     * [getOpenId description]
     * @return string
     */
    protected function getOpenId()
    {
        $this->serviceConfig = Config::get('services.'.$this->type);

        $this->code = Input::get('code');

        switch ($this->type) {
            case 'weibo':
                $this->curlUrl = 'https://api.weibo.com/oauth2/access_token?client_id='.
                    $this->serviceConfig['AppId'].'&client_secret='.
                    $this->serviceConfig['AppSecret'].'&grant_type=authorization_code&redirect_uri='.
                    urlencode($this->serviceConfig['CallbackUrl']).'&code='.$this->code;

                $this->curlMethod = 'POST';
                break;
            case 'qq':
                return $this->getQqOpenId();
                break;
            case 'weixin':
                $this->curlUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='
                    .$this->serviceConfig['AppId'].'&secret='
                    .$this->serviceConfig['AppSecret'].'&code='
                    .$this->code.'&grant_type=authorization_code';
                break;
            default:
                # code...
                break;
        }

        $outcome = json_decode($this->curlOperate());
        $this->accessToken = $outcome->access_token;

        return ($this->type === 'weibo') ? $outcome->uid : $outcome->openid;
    }

    /**
     * 获取 qq 第三方登录的 Open ID
     *
     * @return string
     */
    protected function getQqOpenId()
    {
        $this->curlUrl = 'https://graph.qq.com/oauth2.0/token?grant_type=authorization_code&client_id='.
            $this->serviceConfig['AppId'].'&client_secret='.
            $this->serviceConfig['AppSecret'].'&code='.
            Input::get('code').'&redirect_uri='.
            urlencode($this->serviceConfig['CallbackUrl']);

        $outcome = $this->curlOperate();

        parse_str($outcome, $arr);

        $this->accessToken = $arr['access_token'];
        $this->curlUrl = 'https://graph.qq.com/oauth2.0/me?access_token='.$this->accessToken;
        $str = $this->curlOperate();
        $start = strpos($str, '{');
        $length = strpos($str, '}') - $start + 1;
        $jsonStr = substr($str, $start, $length);

        return json_decode($jsonStr)->openid;
    }

    /**
     * [hasOpenId description]
     * @param  string  $openId Open ID
     * @return string|false
     */
    protected function hasOpenId($openId)
    {
        $this->removeDirtyData($openId);

        $exist = DB::connection('major')
            ->table('oauth')
            ->where('open_id', $openId)
            ->orderBy('user_id', 'desc')
            ->first();

        if ($exist !== null && $exist->user_id !== 0) {
            $model = DB::connection('major')
                ->table('third_party_user_token')
                ->where('oauth_id', $exist->id)
                ->first();

            $token = $model->token;

            return self::INDEX_URI.'?token='.$token;
        }

        return false;
    }

    /**
     * delete dirty data of table oauth
     *
     * @return void
     */
    private function removeDirtyData($openId)
    {
        DB::connection('major')->table('oauth')
            ->where('open_id', $openId)
            ->where('user_id', 0)
            ->delete();
    }

    /**
     * [fetchUser description]
     * @param  string $openId Open ID
     * @return object stcClass
     */
    protected function fetchUser($openId)
    {
        switch ($this->type) {
            case 'weibo':
                $this->curlUrl    = 'https://api.weibo.com/2/users/show.json?access_token='.$this->accessToken.'&uid='.$openId;
                $this->curlMethod = 'GET';
                break;
            case 'qq':
                $this->curlUrl = 'https://graph.qq.com/user/get_user_info?access_token='.
                    $this->accessToken.'&openid='.
                    $openId.'&appid='.$this->serviceConfig['AppId'];
                break;
            case 'weixin':
                $this->curlUrl = 'https://api.weixin.qq.com/sns/userinfo?access_token='.
                    $this->accessToken.'&openid='.
                    $openId.'&lang=zh_CN';
                break;
            default:
                # code...
                break;
        }

        return json_decode($this->curlOperate());
    }

    /**
     * [storeOpenId description]
     * @param  string $openId   Open ID
     * @param  string $tmpToken 临时 token
     * @return void
     */
    protected function storeOpenId($openId, $tmpToken)
    {
        $insertId = DB::connection('major')->table('oauth')
            ->insertGetId(
                    array(
                            'open_id'    => $openId,
                            'type'       => $this->type,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        )
                );

        DB::connection('major')->table('tmp_token')
            ->insert(
                    array(
                            'token'      => $tmpToken,
                            'oauth_id'   => $insertId,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        )
                );
    }

    /**
     * 公共的 curl 操作
     *
     * @return string
     */
    protected function curlOperate()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->curlUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $this->curlMethod,
            CURLOPT_SSL_VERIFYPEER => false,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            return $response;
        }
    }

    public static function handleToken($uid)
    {
        if (!Input::has('token')) {
            return;
        }

        $token = Input::get('token');

        if (strlen($token) !== 30) {
            return;
        }

        self::processData($uid, $token);
    }

    protected static function processData($uid, $token)
    {
        $userToken = DB::connection('major')->table('tmp_token');

        $exist = $userToken->where('token', $token)
            ->first();

        $flag = DB::connection('major')->table('third_party_user_token')
            ->where('token', $token)
            ->exists();

        if ($exist === null || !$flag) {
            throw new ValidationException('无效的 token');
        }

        $oauthId = $exist->oauth_id;
        DB::connection('major')->table('oauth')
            ->where('id', $oauthId)
            ->update(array('user_id' => $uid, 'updated_at' => date('Y-m-d H:i:s')));

        // 更新第三方用户信息表
        DB::connection('major')->table('third_party_user_token')
            ->where('token', $token)
            ->update(array('oauth_id' => $oauthId, 'updated_at' => date('Y-m-d H:i:s')));

        $userToken->where('token', $token)->delete();
    }

    public function entry()
    {
        $token = Input::get('token');

        $model = DB::connection('major')->table('third_party_user_token')
            ->where('token', $token)
            ->first();

        if ($token === null) {
            throw new ValidationException('无效的 token');
        }

        return ['username' => $model->username, 'password' => $model->password];
    }

}