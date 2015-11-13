<?php

return array(

	/*
	|--------------------------------------------------------------------------
	| Third Party Services
	|--------------------------------------------------------------------------
	|
	| This file is for storing the credentials for third party services such
	| as Stripe, Mailgun, Mandrill, and others. This file provides a sane
	| default location for this type of information, allowing packages
	| to have a conventional place to find your various credentials.
	|
	*/

	'mailgun' => array(
		'domain' => '',
		'secret' => '',
	),

	'mandrill' => array(
		'secret' => '',
	),

	'stripe' => array(
		'model'  => 'User',
		'secret' => '',
	),

	'weibo' => array(
		'AppId'       => '2967800801',
		'AppSecret'   => '979067126b050cd41977b7cba7a7832e',
		'CallbackUrl' => 'http://ss.rootant.org/api/weibo_callback',
	),

	'qq' => array(
		'AppId'       => '101256144',
		'AppSecret'   => 'ce5a4ae125e436f491a1ec85a994f8e4',
		'CallbackUrl' => 'http://ss.rootant.org/qq_callback',
	),

	'weixin' => array(
		'AppId'       => 'wx30fb0b0693502a70',
		'AppSecret'   => 'ddfc88f42a0c7d6f1facb20170c720e1',
		'CallbackUrl' => 'http://csi.rootant.org/v3/weixin_callback',
	),

);
