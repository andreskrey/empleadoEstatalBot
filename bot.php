<?php
const APP_PATH = __DIR__ . DIRECTORY_SEPARATOR;

if (getenv('CURRENT_ENV') == 'HEROKU') {
    require_once(APP_PATH . 'config.heroku.php');
    new empleadoEstatalConfig();
} else {
    require_once(APP_PATH . 'config.php');
}
require_once(APP_PATH . 'inc/newspaperParser.php');
require APP_PATH . 'vendor/autoload.php';

use Rudolf\OAuth2\Client\Provider\Reddit;
use League\HTMLToMarkdown\HtmlConverter;


class empleadoEstatal
{
    private $subreddits = [
        'argentina'
    ];

    private $newspapers = [
        'lanacion.com.ar',
        'clarin.com',
        'ieco.clarin.com',
        'infobae.com',
        'cronista.com',
        'telam.com.ar',
        'buenosairesherald.com',
        'pagina12.com.ar',
    ];

    private $lastestPost = null;

    private $client;
    private $headers;

    private $debug = false;


    public function __construct()
    {
        $reddit = new Reddit([
            'clientId' => empleadoEstatalConfig::$CLIENT_ID,
            'clientSecret' => empleadoEstatalConfig::$SECRET_KEY,
            'redirectUri' => empleadoEstatalConfig::$REDIRECT_URI,
            'userAgent' => 'PHP:empleadoEstatalBot:0.0.1, (by /u/subtepass)',
            'scopes' => empleadoEstatalConfig::$SCOPES
        ]);

        $tokenExists = file_exists(APP_PATH . 'tmp/tokens.reddit');
        if ($tokenExists && filemtime(APP_PATH . 'tmp/tokens.reddit') + (60 * 50) < time()) {
            $tokenExists = false;
            unlink(APP_PATH . 'tmp/tokens.reddit');
        }

        if (!$tokenExists) {
            $accessToken = $reddit->getAccessToken('password', [
                'username' => empleadoEstatalConfig::$USERNAME,
                'password' => empleadoEstatalConfig::$PASSWORD
            ]);

            $token = $accessToken->accessToken;
            file_put_contents(APP_PATH . 'tmp/tokens.reddit', $token);
        } else {
            $token = file_get_contents(APP_PATH . 'tmp/tokens.reddit');
        }

        $this->client = $reddit->getHttpClient();
        $this->headers = $reddit->getHeaders($token);

        if (file_exists(APP_PATH . 'tmp/lastest.post')) $this->lastestPost = file_get_contents(APP_PATH . 'tmp/lastest.post');
    }

    public function getNewPosts()
    {
        $things = [];

        foreach ($this->subreddits as $subredit) {
            try {
                $result = $this->client
                    ->get('https://oauth.reddit.com/r/' . $subredit . '/new/.json', $this->headers, ['query' => [
                        'before' => $this->lastestPost,
                        'limit' => 2
                    ]])
                    ->send()
                    ->json();
            } catch (Exception $e) {
                unlink('tmp/tokens.reddit');
                die();
            }

            $firstPost = null;
            foreach ($result['data']['children'] as $i) {
                if (!$firstPost) $firstPost = 't3_' . $i['data']['id'];
                if (in_array($i['data']['domain'], $this->newspapers)) {
                    $things[] = $i;
                }
            }

            if (isset($i) && !$this->debug) file_put_contents(APP_PATH . 'tmp/lastest.post', $firstPost);

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
            $this->client->post('https://oauth.reddit.com/api/comment', $this->headers, [
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
    $ñoqui->postComments($posts);
}

echo 'Done. ' . count($posts) . ' posts.' . PHP_EOL;
if (count($posts)) print_r($posts);
