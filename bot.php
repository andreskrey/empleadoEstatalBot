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
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


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
        'minutouno.com',
        'autoblog.com.ar',
        'perfil.com',
    ];

    private $previousPosts = [];

    private $client;
    private $headers;
    public $log;

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

        $this->log = new Logger('chePibe');
        $this->log->pushHandler(new StreamHandler(APP_PATH . '/tmp/log.log', Logger::INFO));

        if (file_exists(APP_PATH . 'tmp/previousPosts.json')) {
            $this->previousPosts = json_decode(file_get_contents(APP_PATH . 'tmp/previousPosts.json'), true);
            $this->log->addInfo('previousPosts.json loaded:', $this->previousPosts);
        } else {
            $this->log->addInfo('No previousPosts.json file found.');
        }

    }

    public function getNewPosts()
    {
        $things = $posts = $selectedPosts = [];

        foreach ($this->subreddits as $subredit) {
            try {
                $result = $this->client
                    ->get('https://oauth.reddit.com/r/' . $subredit . '/new/.json', $this->headers, ['query' => [
                        'limit' => 5
                    ]])
                    ->send()
                    ->json();
            } catch (Exception $e) {
                unlink(APP_PATH . 'tmp/tokens.reddit');
                $this->log->addError('Failed to get subreddit /new posts.');
                die('Failed to get reddit data');
            }

            foreach ($result['data']['children'] as $i) {
                $posts[] = $i['data']['id'];

                /*
                 * Chequear dos cosas
                 * 1. Que el domain del thing este dentro de la lista de diarios parsebles
                 * 2. Que el id no coincida con los ids obtenidos en la ejecucion anterior
                 * (para evitar postear dos veces en el mismo post)
                 */
                if (in_array($i['data']['domain'], $this->newspapers) && !in_array($i['data']['id'], $this->previousPosts)) {
                    $things[] = $i;
                    $selectedPosts[] = $i['data']['id'];
                }
            }

            if ($posts && !$this->debug) file_put_contents(APP_PATH . 'tmp/previousPosts.json', json_encode($posts));

        }

        $this->log->addInfo('New posts after getting /new data:', $posts);
        $this->log->addInfo('Selected posts to comment:', $selectedPosts);
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
            'header_style' => 'atx',
            'remove_nodes' => 'img'
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
    $ñoqui->log->addInfo('Done posting comments.');
} else {
    $ñoqui->log->addInfo('No new posts.');
}

echo 'Done. ' . count($posts) . ' posts.' . PHP_EOL;
