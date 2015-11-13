<?php

use Rootant\Api\Exception\ValidationException;

class CommunityController extends CommonController
{
    public function __construct()
    {
        $this->afterFilter('disconnect:secondary', ['only' => ['tag', 'getCommentsByTags']]);
    }

    // todo functions

}