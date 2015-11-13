<?php

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\UserInterface;

class CustomEloquentUserProvider extends EloquentUserProvider
{
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Auth\UserInterface  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(UserInterface $user, array $credentials)
    {
        $rawPassword = $credentials['password'];

        $authPassword = $user->getAuthPassword();

        return $authPassword === md5($rawPassword);
    }

}