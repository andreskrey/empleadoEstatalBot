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
    private $headers;


    public function __construct()
    {
        $reddit = new Reddit([
            'clientId' => empleadoEstatalConfig::$CLIENT_ID,
            'clientSecret' => empleadoEstatalConfig::$SECRET_KEY,
            'redirectUri' => empleadoEstatalConfig::$REDIRECT_URI,
            'userAgent' => 'PHP:empleadoEstatalBot:0.0.1, (by /u/subtepass)',
            'scopes' => empleadoEstatalConfig::$SCOPES,
        ]);

        $tokenExists = file_exists('tmp/tokens.reddit');

        if (!$tokenExists) {
            $accessToken = $reddit->getAccessToken('password', [
                'username' => empleadoEstatalConfig::$USERNAME,
                'password' => empleadoEstatalConfig::$PASSWORD,
            ]);

            $token = $accessToken->accessToken;
            file_put_contents('tmp/tokens.reddit', $tokenExists);
        } else {
            $token = file_get_contents('tmp/tokens.reddit');
        }

        $this->client = $reddit->getHttpClient();
        $this->headers = $reddit->getHeaders($token);
    }

    public function getNewPosts()
    {
        foreach ($this->subreddits as $subredit) {
            $test = $this->client->get('https://oauth.reddit.com/r/' . $subredit . '/new/.json', $this->headers);
            $result = $test->send()->json();

            foreach()
        }
    }
}

$ñoqui = new empleadoEstatal();
$ñoqui->getNewPosts();
