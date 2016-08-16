<?php
require_once('config.php');
require 'vendor/autoload.php';

use Rudolf\OAuth2\Client\Provider\Reddit;


class empleadoEstatal
{
    private $subreddits = [
        'argentina'
    ];

    private $client;


    public function __construct()
    {
        $reddit = new Reddit([
            'clientId' => empleadoEstatalConfig::$CLIENT_ID,
            'clientSecret' => empleadoEstatalConfig::$SECRET_KEY,
            'redirectUri' => empleadoEstatalConfig::$REDIRECT_URI,
            'userAgent' => 'PHP:empleadoEstatalBot:0.0.1, (by /u/subtepass)',
            'scopes' => empleadoEstatalConfig::$SCOPES,
        ]);

        $accessToken = $reddit->getAccessToken('password', [
            'username' => empleadoEstatalConfig::$USERNAME,
            'password' => empleadoEstatalConfig::$PASSWORD,
        ]);

        $this->client = $reddit->getHttpClient();
    }

    public function getNewPosts()
    {
        foreach ($this->subreddits as $subrredit) {
            $test = $this->client->get('/r/' . $subrredit . '/new');
        }
    }
}

$ñoqui = new empleadoEstatal();
$ñoqui->getNewPosts();
