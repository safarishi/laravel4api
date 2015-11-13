<?php

use Rootant\Api\Exception\ValidationException;

class CommunityController extends CommonController
{
    public function __construct()
    {
        $this->afterFilter('disconnect:secondary', ['only' => ['tag', 'getCommentsByTags']]);
    }

    public function tag()
    {
        $type = Input::get('type', 'all');

        if ($type !== 'all' && $type !== 'hot') {
            throw new ValidationException('参数传递错误！');
        }

        $tag = $this->dbRepository('secondary', 'tag');

        if ($type === 'all') {
            $tag->orderBy('reorder');
        } else {
            // 双order排序，先按前者排序，当按前者排序相同时再按后者排序
            $tag->orderBy('reorder')->orderBy('counts', 'desc')->take(5);
        }

        return $tag->select('id', 'name')->get();
    }

    public function getCommentsByTags($tagStr)
    {
        $articles = $this->dbRepository('secondary', 'article')
            ->select('id', 'aid')
            ->where('tags', 'like', '%,'.$tagStr.'%')
            ->orderBy('updated_at', 'desc')
            ->take(8)
            ->get();

        $articleIds = array();
        foreach ($articles as $article) {
            $articleIds[] = $article->id;
        }

        $commentModel = $this->dbRepository('secondary', 'article_comment')
            ->select('id', 'user_id', 'user_ip', 'favours', 'content', 'article_id', 'created_at')
            ->whereIn('article_id', $articleIds)
            ->orderBy('created_at', 'desc');

        $this->addPagination($commentModel);

        $comments = $commentModel->get();

        // 处理数据并返回
        return self::handleData($comments, $articles);
    }

    protected function handleData($returnData, $additionalData)
    {
        foreach ($returnData as $v) {
            $v->owner = MultiplexController::getOwner($v->user_id, $v->user_ip);
            foreach ($additionalData as $vv) {
                if ($vv->id === $v->article_id) {
                    $v->article = MultiplexController::getArticle($vv->aid);
                }
            }
            $v->replies = $this->getReply($v->id);
            // release some field
            unset($v->user_id, $v->user_ip, $v->article_id);
        }

        return $returnData;
    }

    protected function getReply($commentId)
    {
        $replies = $this->dbRepository('secondary', 'reply')
            ->select('id', 'user_id', 'user_ip', 'created_at', 'content')
            ->where('article_comment_id', $commentId)
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->get();

        foreach ($replies as $reply) {
            $reply->user = MultiplexController::getOwner($reply->user_id, $reply->user_ip);
            unset($reply->user_id, $reply->user_ip);
        }

        return $replies;
    }

}