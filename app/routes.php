<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/', function()
{
    return 'laravel 4 app';
    return View::make('index');
});

Route::get('test', 'TestController@test');

Route::patterns([
    'id' => '[1-9][0-9]*',
    'comment_id' => '[1-9][0-9]*',
]);

Route::group(array('prefix' => 'v3'), function()
{
    Route::get('journals', 'JournalController@index');
    Route::get('journals/{id}', 'JournalController@show');
    Route::put('journals/{id}/stars', 'JournalController@star');
    Route::delete('journals/{id}/stars', 'JournalController@unstar');
    Route::post('users', 'UserController@store');
    Route::post('oauth/access_token', 'OAuthController@postAccessToken');
    Route::delete('oauth/invalidate_token', 'UserController@logout');
    Route::get('journals/{id}/comments', 'JournalController@commentList');
    // 用户期刊评论
    Route::post('journals/{id}/comments', 'JournalController@comment');
    // 匿名用户期刊评论
    Route::post('journals/{id}/anonymous_comments', 'JournalController@anonymousComment');
    Route::put('journals/{id}/comments/{comment_id}/favours', 'CommentController@favour');
    Route::delete('journals/{id}/comments/{comment_id}/favours', 'CommentController@unfavour');
    // 用户评论回复
    Route::post('journals/{id}/comments/{comment_id}/replies', 'CommentController@reply');
    // 匿名用户评论回复
    Route::post('journals/{id}/comments/{comment_id}/anonymous_replies', 'CommentController@anonymousReply');
    // 我的评论
    Route::get('user/comments', 'UserController@myComment');
    // 我的收藏
    Route::get('user/stars', 'UserController@myStar');
    Route::get('user', 'UserController@show');
    Route::get('user/informations', 'UserController@myInformation');
    // 修改用户个人信息
    Route::post('user', 'UserController@modify');
    // 社区标签
    Route::get('tags', 'CommunityController@tag');
    Route::get('comments/{tags}', 'CommunityController@getCommentsByTags')
        ->where('tags', '([1-9][0-9]*,)+');
    // 验证码
    Route::get('generate_token', 'MultiplexController@generateToken');
    Route::get('generate_captcha', 'MultiplexController@generateCaptcha');
    // 小红点
    Route::get('user/notices', 'UserController@hasNewInformation');
    // 小红点点击
    Route::delete('user/notices', 'UserController@removeNotice');
    // 第三方登录
    Route::get('redirect_url/{type}', 'ThirdPartyLoginController@redirectUrl')
        ->where('type', 'qq|weibo|weixin');
    // weibo callback qq callback todo
    Route::get('weixin_callback', 'ThirdPartyLoginController@weixinCallback');
    // 获取用户登录口令
    Route::get('entry', 'ThirdPartyLoginController@entry');
});

Route::group(array('prefix' => 'api'), function()
{
    Route::get('weibo_callback', 'ThirdPartyLoginController@weiboCallback');
});

Route::get('qq_callback', 'ThirdPartyLoginController@qqCallback');