<?php

use LucaDegasperi\OAuth2Server\Authorizer;
use Rootant\Api\Exception\ValidationException;
use Rootant\Api\Exception\DuplicateOperationException;

class CommentController extends CommonController
{
    /**
     * 评论回复
     *
     */
    const COMMENT_REPLY = 2;

    /**
     * 评论赞
     *
     */
    const COMMENT_FAVOUR = 1;

    /**
     * 评论 id
     *
     * @var string
     */
    protected $id;

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

    public function prepare($route, $request)
    {
        $this->id = $route->parameter('comment_id');

        if (explode('@', $route->getActionName())[1] === 'anonymousReply') {
            // 匿名评论获取用户ip
            $this->userIp = $request->ip();
        } else {
            // 获取用户id
            $this->userId = $this->authorizer->getResourceOwnerId();
        }
    }

    /**
     * [favour description]
     * @param  string $journalId 期刊id
     * @param  string $commentId 评论id
     * @return array
     */
    public function favour($journalId, $commentId)
    {
        $this->type = $this->getOriginType();

        if ($this->checkUserFavour($this->userId, $commentId)) {
            throw new DuplicateOperationException('您已点赞！');
        }

        $insertData = array(
                'user_id'            => $this->userId,
                'journal_comment_id' => $commentId,
                'origin_type'        => $this->type,
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s'),
            );

        $this->models['favour'] = $this->dbRepository('major', 'favour');
        // db transaction todo
        $insertId = $this->models['favour']->insertGetId($insertData);

        // 文章评论被点赞数量 +1
        $this->flag = '+';
        $this->updateCommentFavours($commentId);

        // related to my information todo
        $this->informationType = self::COMMENT_FAVOUR;
        $this->contents = '点赞了这条评论:)';
        $this->recordInformation();

        return (array) $this->models['favour']->find($insertId);
    }

    /**
     *
     * @param  string $journalId 期刊id
     * @param  string $commentId 评论id
     * @return todo
     */
    public function unfavour($journalId, $commentId)
    {
        $this->type = $this->getOriginType();

        $quantity = $this->dbRepository('major', 'favour')
            ->where('origin_type', $this->type)
            ->where('user_id', $this->userId)
            ->where('journal_comment_id', $commentId)
            ->delete();

        if ($quantity === 1) {
            $this->flag = '-';
            $this->updateCommentFavours($commentId);
            // record information
            $this->informationType = self::COMMENT_FAVOUR;
            $this->contents = '取消赞了这条评论:(';
            $this->recordInformation();
        }

        return Response::make('', 204);
    }

    /**
     *
     * @param  string $journalId 期刊id
     * @param  string $commentId 评论id
     * @return array
     */
    public function reply($journalId, $commentId)
    {
        $insertData = array_merge(
                $this->replyIntersection($commentId),
                ['user_id' => $this->userId]
            );

        return $this->replyResponse($insertData);
    }

    protected function replyIntersection($commentId)
    {
        $this->models['reply'] = $this->dbRepository('major', 'reply');

        $originType = $this->getOriginType();

        $this->content = Input::get('content');

        return array(
                'journal_comment_id' => $commentId,
                'origin_type'        => $originType,
                'content'            => $this->content,
                'created_at'         => date('Y-m-d H:i:s'),
                'updated_at'         => date('Y-m-d H:i:s'),
            );
    }

    protected function replyResponse($insertData)
    {
        $insertId = $this->models['reply']->insertGetId($insertData);

        $this->informationType = self::COMMENT_REPLY;
        $this->contents = '回复：'.$this->content;
        $this->recordInformation();

        $data = $this->models['reply']
            ->select('id', 'created_at', 'content')
            ->find($insertId);

        $data->user = $this->getOwner();

        return (array) $data;
    }

    /**
     * 存储相关信息到消息数据表
     *
     * @return void
     */
    protected function recordInformation()
    {
        $contentInfo = $this->informationIntersection();

        $insertData = array(
                'type'       => $this->informationType,
                'content'    => serialize($contentInfo),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            );

        $insertData = $this->processInformationData($insertData);

        // 持久化存储数据
        $this->models['information']->insert($insertData);
    }

    protected function processInformationData($data)
    {
        if ($this->userId) {
            $data = array_merge($data, ['from_uid' => $this->userId]);
        } else {
            $data = array_merge($data, ['from_user_ip' => $this->userIp]);
        }

        if ($this->uid === null) {
            $data = array_merge($data, ['to_user_ip' => $this->uip, 'unread_user_ip' => $this->uip]);
        } else {
            $data = array_merge($data, ['to_uid' => $this->uid, 'unread_uid' => $this->uid]);
        }

        return $data;
    }

    /**
     * 提取消息的公共部分
     *
     * @return object
     */
    protected function informationIntersection()
    {
        $this->models['information'] = $this->dbRepository('major', 'information');
        $this->models['comment']     = $this->dbRepository('major', 'journal_comment');

        return array_merge(
                ['reply'   => $this->contents],
                ['comment' => (array) $this->comment()]
            );
    }

    /**
     * 获取评论信息
     *
     * @return object stdClass
     */
    protected function comment()
    {
        $comment = $this->models['comment']->find($this->id);

        $this->uid = $comment->user_id;
        $this->uip = $comment->user_ip;
        // 获取评论所属用户信息
        $comment->owner = $this->getOwner();
        // release/unset some field
        unset($comment->user_id, $comment->user_ip, $comment->favours, $comment->journal_id, $comment->updated_at);

        return $comment;
    }

    /**
     *
     * @param  string $journalId 期刊id
     * @param  string $commentId 评论id
     * @return array
     */
    public function anonymousReply($journalId, $commentId)
    {
        MultiplexController::verifyCaptcha();

        $insertData = array_merge(
                $this->replyIntersection($commentId),
                ['user_ip' => $this->userIp]
            );

        return $this->replyResponse($insertData);
    }

}