<?php

use Illuminate\Routing\Controller;
use LucaDegasperi\OAuth2Server\Authorizer;

class OAuthController extends ApiController
{
    protected $authorizer;

    public function __construct(Authorizer $authorizer)
    {
        $this->authorizer = $authorizer;
        // $this->afterFilter('disconnect:major', ['only' => ['postAccessToken']]);
    }

    public function postAccessToken()
    {
		return $this->authorizer->issueAccessToken();
    }
}
