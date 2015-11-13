<?php

class CommonController extends ApiController
{

    const JOURNAL_FLAG = 'journal';

    protected $userId;

    /**
     * [$flag description]
     * @var string
     */
    protected $flag;

    /**
     * [dbRepository description]
     * @param  string $connection 数据库连接名
     * @param  string $name       数据库表名
     * @return object
     */
    protected function dbRepository($connection, $name)
    {
        return DB::connection($connection)->table($name);
    }

    /**
     * 获取用户id
     * 用户未登录返回空字符串 ''
     * 登录用户返回用户id
     *
     * @return string
     */
    protected function getUid()
    {
        $uid = '';

        if ($this->accessToken) {
            // 获取用户id
            $this->authorizer->validateAccessToken();
            $uid = $this->authorizer->getResourceOwnerId();
        }

        return $uid;
    }

    // /**
    //  * 判断用户是否收藏期刊
    //  *
    //  * @param  string $uid       用户id
    //  * @param  string $journalId 期刊id
    //  * @return boolean
    //  */
    // protected function checkUserStar($uid, $journalId)
    // {
    //     $this->models['star'] = $this->dbRepository('major', 'star');

    //     return $this->models['star']
    //         ->where('user_id', $uid)
    //         ->where('journal_id', $journalId)
    //         ->exists();
    // }

    /**
     * [getOwner description]
     * @param  int    $userId
     * @param  string $userIp
     * @return object|array
     */
    protected function getOwner()
    {
        if ($this->userId !== null) {
            // 返回用户信息
            return $this->user()->find($this->userId);
        }
        // 返回匿名用户信息
        return $this->anonymousUser();
    }

    /**
     * 增加数据模型分页
     *
     * @param  object $model 需要分页的数据模型
     * @return void
     */
    protected function addPagination($model)
    {
        // 第几页数据，默认第一页
        $page    = Input::get('page', 1);
        // 每页显示数据条目，默认每页20条数据
        $perPage = Input::get('per_page', 20);
        $page    = intval($page);
        $perPage = intval($perPage);

        if ($page <= 0 || !is_int($page)) {
            $page = 1;
        }
        if (!is_int($perPage) || $perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }
        // skip -- offset , take -- limit
        $model->skip(($page - 1) * $perPage)->take($perPage);
    }

}