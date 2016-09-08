<?php
namespace empleadoEstatalBot;

use Exception;
use Rudolf\OAuth2\Client\Provider\Reddit;
use League\HTMLToMarkdown\HtmlConverter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use empleadoEstatalBot\NewspaperProcessor;

define('APP_PATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getenv('CURRENT_ENV') == 'HEROKU') {
    require_once(APP_PATH . 'config' . DIRECTORY_SEPARATOR . 'config.heroku.php');
    new Config();
} else {
    require_once(APP_PATH . 'config' . DIRECTORY_SEPARATOR . 'config.php');
}
require APP_PATH . '../vendor/autoload.php';


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
        'cronica.com.ar',
        //'ambito.com',
    ];

    private $client;
    private $redis;
    private $headers;
    public $log;

    private $debug = false;


    public function __construct()
    {
        if ($this->debug) $this->subreddits = ['empleadoEstatalBot'];

        $this->redis = new \Predis\Client(Config::$REDIS_URL);

        $reddit = new Reddit([
            'clientId' => Config::$CLIENT_ID,
            'clientSecret' => Config::$SECRET_KEY,
            'redirectUri' => Config::$REDIRECT_URI,
            'userAgent' => 'PHP:empleadoEstatalBot:0.0.1, (by /u/subtepass)',
            'scopes' => Config::$SCOPES
        ]);

        $tokenExists = file_exists(APP_PATH . 'tmp/tokens.reddit');
        if ($tokenExists && filemtime(APP_PATH . 'tmp/tokens.reddit') + (60 * 50) < time()) {
            $tokenExists = false;
            unlink(APP_PATH . 'tmp/tokens.reddit');
        }

        if (!$tokenExists) {
            $accessToken = $reddit->getAccessToken('password', [
                'username' => Config::$USERNAME,
                'password' => Config::$PASSWORD
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
    }

    private function getNewPosts()
    {
        $things = $posts = $selectedPosts = $alreadyCommented = [];

        /*
         * Caso de que la ddbb de redis este vacia (por que heroku la borra cada tanto en el hosting gratuito,
         * cargar los posts ya comentados para matchearlos al postear y evitar doble comment.
         */
        if ($this->redis->dbsize()) {
            $this->log->addInfo('Posted comments ddbb NOT empty. :)');
        } else {
            $alreadyCommented = $this->alreadyCommented();
            $this->log->addAlert('Posted comments ddbb empty.');
        }

        foreach ($this->subreddits as $subredit) {
            try {
                $result = $this->client
                    ->get('https://oauth.reddit.com/r/' . $subredit . '/new/.json', $this->headers, ['query' => [
                        'limit' => 10
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
                 * Chequear tres cosas
                 * 1. Que el domain del thing este dentro de la lista de diarios parsebles
                 * 2. Que el id no coincida con los que ya existen en la ddbb de redis
                 * 3. Chequear que no haya comentado ya (en caso de que la ddbb de redis este vacia).
                 */
                if (in_array($i['data']['domain'], $this->newspapers)
                    && !$this->redis->get($i['data']['id'])
                    && !in_array($i['data']['id'], $alreadyCommented)
                ) {
                    $things[] = $i;
                    if (!$this->debug) {
                        $selectedPosts[] = $i['data']['id'];
                        $this->redis->set($i['data']['id'], date('c'));
                    }
                }
            }

        }

        $this->log->addInfo('New posts after getting /new data:', $posts);
        $this->log->addInfo('Selected posts to comment:', $selectedPosts);
        return $things;
    }

    private function getNewspaperText($things)
    {
        foreach ($things as $k => $i) {
            $text = $this->client->get($i['data']['url'])->send();
            $body = $text->getBody(true);

            // Por alguna razon a veces minutouno manda gzippeado y guzzle no lo descomprime
            // Los tres chars son los magic numbers de zip
            $isGZip = 0 === mb_strpos($body, "\x1f" . "\x8b" . "\x08");
            if ($isGZip) $body = gzdecode($body);

            $newspaper = 'empleadoEstatalBot\NewspaperProcessor\Parsers\\' . str_replace('.', '', $i['data']['domain']) . 'Parser';
            if (class_exists($newspaper)) {
                $parser = new $newspaper;
            } else {
                throw new \BadFunctionCallException();
            }

            $things[$k]['parsed'] = $parser->parse($body);
        }

        return $things;
    }

    private function postComments($things)
    {
        foreach ($things as $i) {
            $this->client->post('https://oauth.reddit.com/api/comment', $this->headers, [
                'thing_id' => 't3_' . $i['data']['id'],
                'text' => $this->buildMarkdown($i['parsed'])
            ])
                ->send();
        }
    }

    private function alreadyCommented()
    {
        if ($this->debug) return [];

        $ids = [];

        $comments = $this->client->get('https://oauth.reddit.com/user/empleadoEstatalBot', $this->headers, [
            'show' => 'comments',
            'sort' => 'new',
            'count' => 100
        ])
            ->send()
            ->json();

        foreach ($comments['data']['children'] as $i) {
            if (isset($i['data']['link_id'])) $ids[] = substr($i['data']['link_id'], 3);
        }

        return $ids;
    }

    private function buildMarkdown($parsed)
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'header_style' => 'atx',
            'remove_nodes' => 'img script'
        ]);

        $markdown = $converter->convert($parsed);

        return $markdown;
    }

    public function laburar()
    {
        $posts = $this->getNewPosts();

        if ($posts) {
            $posts = $this->getNewspaperText($posts);
            $this->postComments($posts);
            $this->log->addInfo('Done posting comments.');
        } else {
            $this->log->addInfo('No new posts.');
        }

        return count($posts);
    }
}
