<?php

namespace empleadoEstatalBot\RedditManager;

use empleadoEstatalBot\Config;
use empleadoEstatalBot\empleadoEstatal;
use Exception;
use League\HTMLToMarkdown\HtmlConverter;
use Rudolf\OAuth2\Client\Provider\Reddit;

class RedditManager
{
    public $client;

    private $headers;
    private $redis;

    private $subreddits = [
        'argentina'
    ];

    private $bannedDomains = [
        'imgur.com',
        'i.imgur.com',
        'm.imgur.com',
        'twitter.com',
        'youtube.com',
        'np.reddit.com',
        'i.reddit.com',
        'i.redditmedia.com',
        'reddit.com',
        'self.argentina',
        'youtube.com',
        'm.youtube.com',
        'youtu.be',
        'storify.com',
        'buzzfeed.com',
        'ar.radiocut.fm',
        'radiocut.fm',
        'vid.me',
    ];

    public function __construct()
    {
        if (empleadoEstatal::$debug) $this->subreddits = ['empleadoEstatalBot'];
    }

    public function login()
    {
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
    }

    public function getNewPosts()
    {
        $things = [];

        foreach ($this->subreddits as $subreddit) {
            try {
                $result = $this->client
                    ->get('https://oauth.reddit.com/r/' . $subreddit . '/new/.json', $this->headers, ['query' => [
                        'limit' => 10
                    ]])
                    ->send()
                    ->json();
                $things = array_merge($things, $this->checkDomains($result['data']['children']));
            } catch (Exception $e) {
                unlink(APP_PATH . 'tmp/tokens.reddit');
                empleadoEstatal::$log->addError('Failed to get subreddit /new posts.');
                die('Failed to get reddit data');
            }
        }

        return $things;
    }

    public function getPost($ids = null)
    {
        $things = [];
        $ids = array_unique($ids);

        foreach ($ids as $id) {
            try {
                $thing = $this->client
                    ->get('https://oauth.reddit.com/by_id/t3_' . $id . '/.json', $this->headers)
                    ->send()
                    ->json();

                $postable = $this->checkDomains($thing['data']['children']);
                if ($postable) $things[] = $postable[0];
            } catch (Exception $e) {
                empleadoEstatal::$log->addError('Wrong ID sent: ' . $id);
            }
        }

        return $things;
    }

    private function checkDomains($things)
    {
        $posts = $selectedPosts = $alreadyCommented = $selectedThings = [];

        /*
         * Caso de que la ddbb de redis este vacia (por que heroku la borra cada tanto en el hosting gratuito,
         * cargar los posts ya comentados para matchearlos al postear y evitar doble comment.
         */
        if (!empleadoEstatal::$redis->dbsize()) {
            $alreadyCommented = $this->alreadyCommented();
        }

        foreach ($things as $i) {
            $posts[] = $i['data']['id'];

            /*
             * Chequear tres cosas
             * 1. Que el domain del thing no este dentro de los domains banneados
             * 2. Que el id no coincida con los que ya existen en la ddbb de redis
             * 3. Chequear que no haya comentado ya (en caso de que la ddbb de redis este vacia).
             */
            if (!in_array($i['data']['domain'], $this->bannedDomains)
                && !empleadoEstatal::$redis->get($i['data']['id'])
                && !in_array($i['data']['id'], $alreadyCommented)
            ) {
                $selectedThings[] = $i;
                if (!empleadoEstatal::$debug) {
                    $selectedPosts[] = $i['data']['id'];
                    empleadoEstatal::$redis->set($i['data']['id'], date('c'));
                }
            }
        }

        empleadoEstatal::$log->addInfo('New posts after getting data:', $posts);
        empleadoEstatal::$log->addInfo('Selected posts to comment:', $selectedPosts);

        return $selectedThings;
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

    private function alreadyCommented()
    {
        if (empleadoEstatal::$debug) return [];

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
            'header_style' => 'atx'
        ]);

        $markdown = $converter->convert($parsed);

        // Agregar la marca de markdown para hacer el hover de css

        $markdown = "#####&#009;\n\n######&#009;\n\n####&#009;\n\n" . $markdown;

        return $markdown;
    }
}