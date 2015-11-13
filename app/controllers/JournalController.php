<?php

use LucaDegasperi\OAuth2Server\Authorizer;
use Rootant\Api\Exception\ValidationException;
use Rootant\Api\Exception\DuplicateOperationException;
use Rootant\Api\Exception\ResourceNonExistentException;

class JournalController extends CommonController
{
    const IMAGE_ORIGIN = '\..\qikan\v2';

    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->beforeFilter('oauth', ['except' => ['index', 'show', 'commentList', 'anonymousComment']]);
        $this->beforeFilter('validation');
        // after filter
        $this->afterFilter('disconnect:mysql', ['only' => ['index', 'show', 'comment', 'commentList']]);
        $this->afterFilter('disconnect:major', ['only' => ['show', 'star', 'unstar', 'comment', 'anonymousComment', 'commentList']]);
    }

    private static $_validate = [
        'comment' => [
            'content' => 'required',
        ],
        'anonymousComment' => [
            'content' => 'required',
        ],
    ];

    public function index()
    {
        $journals = $this->journal()
            ->orderBy('qname', 'desc')
            ->get();

        foreach ($journals as $journal) {
            $journal->thumbnail_url = self::RESOURCE_URL.'/'.$journal->thumbnail_url;
        }

        return $journals;
    }

    /**
     * [show description]
     * @param  string $id 期刊id
     * @return array
     */
    public function show($id)
    {
        $journal = $this->journal()->find($id);
        if ($journal === null) {
            throw new ResourceNonExistentException('id 参数传递错误');
        }

        $dirPrefix = base_path().self::IMAGE_ORIGIN;
        $dir = $dirPrefix.'\source\html\\'.$journal->journal_date;

        $files = File::files($dir);
        foreach ($files as &$file) {
            $file = str_replace($dirPrefix, '', $file);
            $file = array('url' => self::RESOURCE_URL.str_replace('\\', '/', $file));
        }
        unset($file);

        $isStarred = $this->checkUserJournalStar($id);
        // todo

        return array('is_starred' => $isStarred, 'data' => $files);
    }

    /**
     * 校验用户是否收藏期刊
     *
     * @param  string $id 期刊id
     * @return boolean
     */
    protected function checkUserJournalStar($id)
    {
        $uid = $this->getUid();

        return $this->checkUserStar($uid, $id);
    }

    public function star($id)
    {
        $uid = $this->authorizer->getResourceOwnerId();

        if ($this->checkUserStar($uid, $id)) {
            throw new DuplicateOperationException('您已收藏！');
        }

        $insertData = array(
                'user_id'    => $uid,
                'journal_id' => $id,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            );

        $insertId = $this->models['star']->insertGetId($insertData);

        return (array) $this->models['star']->find($insertId);
    }

    public function unstar($id)
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $star = $this->dbRepository('major', 'star');

        $star->where('user_id', $uid)
            ->where('journal_id', $id)
            ->delete();

        return Response::make('', 204);
    }

    /**
     * [commentList description]
     * @param  string $id 期刊id
     * @return array
     */
    public function commentList($id)
    {
        $this->journal = $this->journal()->find($id);
        if ($this->journal === null) {
            throw new ValidationException('参数传递错误！');
        }

        $comment = $this->dbRepository('major', 'journal_comment');

        $list = $comment->where('journal_id', $id)
            ->select('id', 'favours', 'content', 'created_at', 'user_id', 'user_ip')
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        $list = $this->processCommentResponse($list);

        $extra = $this->getExtraComment($id);

        return [
            'lists'  => $list,
            'extras' => $extra,
        ];
    }

    /**
     * [getExtraComment description]
     * @param  string $id 期刊id
     * @return todo
     */
    protected function getExtraComment($id)
    {
        $comments = $this->dbRepository('major', 'journal_comment')
            ->select('id', 'journal_id', 'favours', 'content', 'created_at', 'user_id', 'user_ip')
            ->where('journal_id', '<>', $id)
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        shuffle($comments);
        $data = array_slice($comments, 0, 2);

        $this->flag = self::JOURNAL_FLAG;

        return $this->processCommentResponse($data);
    }

    /**
     * [comment description]
     * @param  string $id 期刊id
     * @return array
     */
    public function comment($id)
    {
        $this->userId = $this->authorizer->getResourceOwnerId();
        $insertData   = array_merge(
                $this->commentIntersection($id),
                ['user_id' => $this->userId]
            );

        return $this->commentResponse($insertData);
    }

    private function commentIntersection($id)
    {
        $this->models['comment'] = $this->dbRepository('major', 'journal_comment');

        return array(
                'journal_id' => $id,
                'content'    => Input::get('content'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            );
    }

    /**
     * [anonymousComment description]
     * @param  string $id 期刊id
     * @return array
     */
    public function anonymousComment($id)
    {
        MultiplexController::verifyCaptcha();

        $this->userIp = Request::ip();
        $insertData   = array_merge(
                $this->commentIntersection($id),
                ['user_ip' => $this->userIp]
            );

        return $this->commentResponse($insertData);
    }

    protected function commentResponse($insertData)
    {
        $insertId = $this->models['comment']->insertGetId($insertData);

        $data = $this->models['comment']
            ->select('id', 'favours', 'content', 'created_at')
            ->find($insertId);
        $data->user = $this->getOwner();

        return (array) $data;
    }

}