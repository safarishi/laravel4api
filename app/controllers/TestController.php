<?php

use LucaDegasperi\OAuth2Server\Authorizer;
use Rootant\Api\Exception\ValidationException;

class TestController extends CommonController
{
    /**
     * [__construct description]
     *
     */
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->beforeFilter('oauth', ['except' => ['functionName', 'test']]);
        $this->beforeFilter('validation');
        $this->afterFilter('disconnect:major', ['only' => ['functionName', 'test']]);
    }

    private static $_validate = [
        // todo
        // 格式类似如下
        // 'functionName' => [
            // 'username' => 'required|min:5|unique:user,name',
            // 'email'    => 'required|email|unique:user',
            // 'password' => 'required|min:6|confirmed',
        // ],
    ];

    public function test()
    {
        // code ...
    }
}