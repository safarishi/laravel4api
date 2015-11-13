<?php

use Rootant\Api\Exception\ValidationException;

class MultiplexController extends Controller
{

    public function __construct()
    {
        // $this->afterFilter('disconnect:major', ['only' => ['tag', 'generateCaptcha']]);
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

}
