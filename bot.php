<?php
require_once('config.php');
require_once('inc/newspaperParser.php');
require 'vendor/autoload.php';

use Rudolf\OAuth2\Client\Provider\Reddit;
use League\HTMLToMarkdown\HtmlConverter;


class empleadoEstatal
{
    private $subreddits = [
        'empleadoEstatalBot'
    ];

    private $newspapers = [
        'lanacion.com.ar'
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
            file_put_contents('tmp/tokens.reddit', $token);
        } else {
            $token = file_get_contents('tmp/tokens.reddit');
        }

        $this->client = $reddit->getHttpClient();
        $this->headers = $reddit->getHeaders($token);
    }

    public function getNewPosts()
    {
        $things = [];

        foreach ($this->subreddits as $subredit) {
            $result = $this->client
                ->get('https://oauth.reddit.com/r/' . $subredit . '/new/.json', $this->headers)
                ->send()
                ->json();

            foreach ($result['data']['children'] as $i) {
                if (in_array($i['data']['domain'], $this->newspapers)) {
                    $things[] = $i;
                }
            }
        }

        return $things;
    }

    public function getNewspaperText($things)
    {
        foreach ($things as $k => $i) {
            $text = $this->client->get($i['data']['url'])->send();

            $parser = new newspaperParser(str_replace('.', '', $i['data']['domain']));
            $things[$k]['parsed'] = $parser->parse($text->getBody(true));
        }

        return $things;
    }

    public function postComments($things)
    {
        foreach ($things as $i) {

            $result = $this->client->post('https://oauth.reddit.com/api/comment', $this->headers, [
                'thing_id' => 't3_' . $i['data']['id'],
                'text' => $this->buildMarkdown($i['parsed'])
            ])
                ->send();
        }

    }

    private function buildMarkdown($parsed)
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'header_style' => 'atx'
        ]);

        $markdown = $converter->convert($parsed);

        return $markdown;
    }
}

$ñoqui = new empleadoEstatal();
$posts = $ñoqui->getNewPosts();

if ($posts) {
    $posts = $ñoqui->getNewspaperText($posts);
    $posts = $ñoqui->postComments($posts);
}
