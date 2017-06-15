<?php

namespace empleadoEstatalBot\RedditManager;

use empleadoEstatalBot\Post;
use GuzzleHttp\Client as HttpClient;
use empleadoEstatalBot\empleadoEstatal;
use Exception;
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
            'userAgent' => 'PHP:empleadoEstatalBot:2.0.0, (by /u/subtepass)',
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
                foreach ($this->config['banned_domains'] as $banned_domain) {
                    if (fnmatch($banned_domain, $thing['data']['domain'], FNM_CASEFOLD)) {
                        empleadoEstatal::$log->addInfo(sprintf('GetWorker: Discarded %s. Banned domain: %s. Matched rule: %s', $thing['data']['name'], $thing['data']['domain'], $banned_domain));
                        unset($this->things[$subreddit][$key]);
                    }
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
                        'status' => empleadoEstatal::THING_TO_FETCH,
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

    public function postComments()
    {
        foreach (Post::where(['status' => empleadoEstatal::THING_FETCHED, ['tries', '<', 3]])->get() as $thing) {
            /**
             * @var $thing Post
             */
            try {
                $thing->tries++;

                $this->client->request('POST', 'https://oauth.reddit.com/api/comment', [
                    'headers' => $this->headers,
                    'form_params' => [
                        'thing_id' => $thing->thing,
                        'text' => $thing->markdown
                    ]
                ]);
                $thing->status = empleadoEstatal::THING_POSTED;
                empleadoEstatal::$log->addInfo(sprintf('PostWorker: posted %s.', $thing->thing));
            } catch (Exception $e) {
                empleadoEstatal::$log->addCritical(sprintf('PostWorker: Failed to post %s: %s', $thing->thing, $e->getMessage()));
            }

            $thing->save();
        }
    }
}
