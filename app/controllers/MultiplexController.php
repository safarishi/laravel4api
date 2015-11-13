<?php

use Rootant\Api\Exception\ValidationException;

class MultiplexController extends Controller
{

    public function __construct()
    {
        $this->afterFilter('disconnect:major', ['only' => ['tag', 'generateCaptcha']]);
    }

    /**
     * 随机生成默认长度6位由字母、数字组成的字符串
     *
     * @param  integer $length
     * @return string
     */
    protected static function generateRandomStr($length = 6)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $str;
    }

    /**
     * [uuid description]
     * @return string
     */
    public static function uuid()
    {
        $randomStr = self::generateRandomStr(7);

        return uniqid($randomStr, true);
    }

    /**
     * [getOwner description]
     * @param  int    $userId
     * @param  string $userIp
     * @return object|array
     */
    public static function getOwner($userId, $userIp)
    {
        if ($userId !== null) {
            // 返回用户信息
            return self::getUser($userId);
        }
        // 返回匿名用户信息
        return self::anonymousUser($userIp);
    }

    /**
     * 获取用户信息
     *
     * @param  int    $uid
     * @return object stdClass
     */
    public static function getUser($uid)
    {
        $userInfo = self::user()->find($uid);

        $soapClient = self::webServiceUtil();

        $params = array(
                // prefix uuid
                'UserId' => $userInfo->uuid,
            );

        $data = $soapClient->GetUser($params);

        $returnData = json_decode($data->GetUserResult);
        $returnData->avatar_url = $userInfo->avatar_url;
        $returnData->name       = $userInfo->name;

        return $returnData;
    }

    /**
     *
     * @return object SoapClient
     */
    public static function webServiceUtil()
    {
        $url     = 'http://manage.chinashippinginfo.net/csiInterface/InteShipping.asmx?wsdl';
        $option = array('trace' => true, 'exceptions' => true);

        return new SoapClient($url, $option);
    }

    /**
     * 获取匿名用户的信息
     *
     * @param  string $ip 匿名用户的ip
     * @return array
     */
    public static function anonymousUser($ip)
    {
        $name = self::getArea($ip);

        return [
            'avatar_url' => Config::get('imagecache::paths.avatar_url_prefix').'/default.png',
            'name' => '来自'.$name.'的用户'
        ];
    }

    /**
     *
     * @return object Illuminate\Database\Query\Builder
     */
    public static function user()
    {
        $user = DB::connection('secondary')->table('user')
            ->select('id', 'uuid', 'name', 'avatar_url', 'email', 'gender', 'company', 'job', 'created_at', 'updated_at');

        return $user;
    }

    /**
     * 根据ip获取用户所在城市
     *
     * @param  string $ip
     * @return string     城市名称
     */
    public static function getArea($ip)
    {
        // 根据ip地址查询地点信息的url
        $url = 'http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip='.$ip;

        $data = json_decode(file_get_contents($url));

        $result = '火星';
        if (is_object($data) && property_exists($data, 'city')) {
            $result = $data->city;
        }

        return $result;
    }

    // imitate to get remote article info
    public static function getArticle($aid)
    {
        $soapClient = self::webServiceUtil();

        $params = array(
                'ArticleId' => $aid,
            );

        $data = $soapClient->GetArticleInfo($params);

        return json_decode($data->GetArticleInfoResult);
    }

    public function generateToken()
    {
        echo self::uuid();
    }

    public function generateCaptcha()
    {
        $token = Input::get('token');

        if (strlen($token) !== 30) {
            throw new ValidationException('token 参数传递错误');
        }

        Captcha::create();

        $captcha = Session::get('captchaHash');

        $captchaToken = DB::connection('major')->table('tmp_token');
        $captchaToken->insert(
                array(
                    'captcha'    => $captcha,
                    'token'      => $token,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                )
            );
    }

    /**
     * 校验验证码
     *
     * @return void
     *
     * @throws Rootant\Api\Exception\ValidationException
     */
    public static function verifyCaptcha()
    {
        $token   = Input::get('token');
        $captcha = Input::get('captcha');

        $rules =  array('captcha' => array('required'), 'token' => array('required'));

        $validator = Validator::make(Input::only('captcha', 'token'), $rules);
        if ($validator->fails()) {
            $messages = $validator->messages();
            throw new ValidationException($messages->all());
        }

        $captchaToken = DB::connection('major')->table('tmp_token');
        $data = $captchaToken->where('token', $token)->first();

        if ($data === null) {
            throw new ValidationException('无效的 token');
        }

        $captchaToken->where('token', $token)->delete();

        if (!Hash::check(mb_strtolower($captcha), $data->captcha)) {
            throw new ValidationException('验证码填写不正确');
        }
    }

}
