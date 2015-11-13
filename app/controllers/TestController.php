<?php

class TestController extends CommonController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->beforeFilter('oauth', ['except' => ['anonymousReply']]);
        $this->beforeFilter('validation');
        $this->beforeFilter('@prepare', ['only' => ['reply', 'anonymousReply', 'favour', 'unfavour']]);
        $this->afterFilter('disconnect:major', ['only' => ['reply', 'anonymousReply', 'favour', 'unfavour']]);
    }

    private static $_validate = [
        'reply' => [
            'content' => 'required',
        ],
        'anonymousReply' => [
            'content' => 'required',
        ],
    ];
}