<?php

use LucaDegasperi\OAuth2Server\Authorizer;
use Rootant\Api\Exception\ValidationException;

/**
 * 关于控制器类文件
 * 继承关系
 * class TestController extends CommonController
 * class CommonController extends ApiController
 * class ApiController extends Controller
 * 将类文件进行拆分：
 * UserController --> UserAvatarController UserPasswordController
 * ArticleController JournalController --> CommentController
 */
class TestController extends CommonController
{
    /**
     * 定义类常量
     * 类常量是可以继承过来的
     *
     */
    const COMMENT_FAVOUR = 1;

    /**
     * [__construct description]
     *
     */
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->beforeFilter('oauth', ['except' => ['functionName', 'test']]);
        $this->beforeFilter('validation');
        // $this->beforeFilter('@prepare', ['only' => ['reply', 'anonymousReply', 'favour', 'unfavour']]);
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
        // 'reply' => [
        //     'content' => 'required',
        // ],
    ];

    public function test()
    {
        // var_dump(TestController::JOURNAL_FLAG);
        // var_dump(self::COMMENT_FAVOUR);
        // code ...
    }
}