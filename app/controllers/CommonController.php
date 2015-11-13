<?php

class CommonController extends ApiController
{

    const JOURNAL_FLAG = 'journal';

    const RESOURCE_URL = 'http://www.csapchina.com';

    protected $userId;

    protected $userIp;

    protected $type;

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
     * common logic for journal
     *
     * @return object
     */
    protected function journal()
    {
        return DB::table('qikan')
            ->select('id', 'thumbnail as thumbnail_url', 'name', 'qname as journal_date')
            ->where('display', 1);
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

    /**
     * 判断用户是否收藏期刊
     *
     * @param  string $uid       用户id
     * @param  string $journalId 期刊id
     * @return boolean
     */
    protected function checkUserStar($uid, $journalId)
    {
        $this->models['star'] = $this->dbRepository('major', 'star');

        return $this->models['star']
            ->where('user_id', $uid)
            ->where('journal_id', $journalId)
            ->exists();
    }

    /**
     * 判断用户是否点赞
     *
     * @param  string $uid       用户id
     * @param  string $commentId 评论id
     * @return boolean
     */
    protected function checkUserFavour($uid, $commentId)
    {
        $this->models['favour'] = $this->dbRepository('major', 'favour');

        return $this->models['favour']
            ->where('origin_type', $this->type)
            ->where('user_id', $uid)
            ->where('journal_comment_id', $commentId)
            ->exists();
    }

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
     * 用户信息统一返回
     *
     * @return todo
     */
    protected function user()
    {
        return DB::table('user')
            ->select('avatar_url', 'name as display_name', 'email', 'address');
    }

    private function anonymousUser()
    {
        $area = MultiplexController::getArea($this->userIp);

        return [
            'avatar_url' => Config::get('imagecache::paths.avatar_url_prefix').'/default.png',
            'name'       => '来自'.$area.'的用户'
        ];
    }

    protected function getOriginType()
    {
        $originType = 'csp';

        if (Input::get('type') === 'c') {
            $originType = 'csi';
        }

        return $originType;
    }

    /**
     * 更新评论被点赞数量
     *
     * @param  string $id 评论id
     * @return void
     */
    protected function updateCommentFavours($id)
    {
        $connection = 'major';
        $table = 'journal_comment';

        if ($this->type === 'c') {
            $connection = 'secondary';
            $table = 'article_comment';
        }

        $comment = $this->dbRepository($connection, $table);

        $delta = ($this->flag === '+') ? 1 : -1;

        $comment->where('id', $id)
            // increment 也可以来减少
            ->increment('favours', $delta, array('updated_at' => date('Y-m-d H:i:s')));
    }

    protected function processCommentResponse($data)
    {
        foreach ($data as $value) {
            $this->userId = $value->user_id;
            $this->userIp = $value->user_ip;
            $value->user  = $this->getOwner();

            $value->journal = ($this->flag === self::JOURNAL_FLAG) ?
                $this->journal()->find($value->journal_id) :
                $this->journal;

            $value->relies = $this->getReply($value->id);
        }

        foreach ($data as $value) {
            unset($value->user_id, $value->user_ip, $value->journal_id);
        }

        return $data;
    }

    /**
     * [getReply description]
     * @param  int $id 评论id
     * @return array
     */
    protected function getReply($id)
    {
        $replies = $this->dbRepository('major', 'reply')
            ->select('id', 'created_at', 'content', 'user_id', 'user_ip')
            ->where('journal_comment_id', $id)
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->get();

        foreach ($replies as $reply) {
            $this->userId = $reply->user_id;
            $this->userIp = $reply->user_ip;
            $reply->user  = $this->getOwner();

            unset($reply->user_id, $reply->user_ip);
        }

        return $replies;
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