<?php

namespace empleadoEstatalBot\RedditManager;

use empleadoEstatalBot\Post;
use GuzzleHttp\Client as HttpClient;
use empleadoEstatalBot\empleadoEstatal;
use Exception;
use League\HTMLToMarkdown\HtmlConverter;
use Rudolf\OAuth2\Client\Provider\Reddit;

class RedditManager
{
    /**
     * @var HttpClient
     */
    private $client;

    private $config;
    private $headers;
    private $things = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function login()
    {
        $reddit = new Reddit([
            'clientId' => $this->config['client_id'],
            'clientSecret' => $this->config['secret_key'],
            'redirectUri' => $this->config['redirect_uri'],
            'userAgent' => 'PHP:empleadoEstatalBot:0.0.1, (by /u/subtepass)',
            'scopes' => $this->config['scopes']
        ]);

        $tokenExists = file_exists('tmp/tokens.reddit');
        if ($tokenExists && filemtime('tmp/tokens.reddit') + (60 * 50) < time()) {
            $tokenExists = false;
            unlink('tmp/tokens.reddit');
        }

        if (!$tokenExists) {
            $accessToken = $reddit->getAccessToken('password', [
                'username' => $this->config['username'],
                'password' => $this->config['password']
            ]);

            $token = $accessToken->getToken();
            file_put_contents('tmp/tokens.reddit', $token);
        } else {
            $token = file_get_contents('tmp/tokens.reddit');
        }

        $this->client = $reddit->getHttpClient();
        $this->headers = $reddit->getHeaders($token);
    }

    public function getNewPosts()
    {
        foreach ($this->config['subreddits'] as $subreddit) {
            try {
                $request = $this->client
                    ->request('GET', 'https://oauth.reddit.com/r/' . $subreddit . '/new/.json', [
                        'headers' => $this->headers,
                        'query' => [
                            'limit' => 10
                        ]]);
                $response = json_decode($request->getBody(), true);
                $this->things[$subreddit] = $response['data']['children'];
            } catch (Exception $e) {
                if ($e->getCode() === 401) {
                    unlink('tmp/tokens.reddit');
                }

                empleadoEstatal::$log->addCritical('GetWorker: Failed to get subreddit /new posts: ' . $e->getMessage());
                throw $e;
            }
        }

        return $this->things;
    }

    public function filterPosts()
    {
        foreach ($this->things as $subreddit => $things) {
            foreach ($things as $key => $thing) {
                if (in_array($thing['data']['domain'], $this->config['banned_domains'])) {
                    empleadoEstatal::$log->addInfo('GetWorker: Discarded ' . $thing['data']['name'] . '. Banned domain: ' . $thing['data']['domain']);
                    unset($this->things[$subreddit][$key]);
                }
            }
        }
    }


    public function savePosts($posts = null)
    {
        if (!empty($posts)) {
            $this->things = $posts;
        }

        $saved = [];

        foreach ($this->things as $subreddit => $things) {
            foreach ($things as $key => $thing) {
                if (!Post::where('thing', $thing['data']['name'])->exists()) {
                    Post::firstOrCreate([
                        'subreddit' => $subreddit,
                        'thing' => $thing['data']['name'],
                        'url' => $thing['data']['url'],
                        'status' => 1,
                        'tries' => 0
                    ]);

                    $saved[] = $thing['data']['name'];
                }
            }
        }

        if (count($saved)) {
            empleadoEstatal::$log->addInfo('GetWorker: New posts saved to db: ' . implode(', ', $saved) . '.');
        } else {
            empleadoEstatal::$log->addInfo('GetWorker: No new posts to save.');
        }

        return $saved;
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