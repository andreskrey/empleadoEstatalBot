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

        $tokenExists = file_exists(__DIR__ . '/../tmp/tokens.reddit');
        if ($tokenExists && filemtime(__DIR__ . '/../tmp/tokens.reddit') + (60 * 50) < time()) {
            $tokenExists = false;
            unlink(__DIR__ . '/../tmp/tokens.reddit');
        }

        if (!$tokenExists) {
            $accessToken = $reddit->getAccessToken('password', [
                'username' => $this->config['username'],
                'password' => $this->config['password']
            ]);

            $token = $accessToken->getToken();
            file_put_contents(__DIR__ . '/../tmp/tokens.reddit', $token);
        } else {
            $token = file_get_contents(__DIR__ . '/../tmp/tokens.reddit');
        }

        $this->client = $reddit->getHttpClient();
        $this->headers = $reddit->getHeaders($token);
    }

    public function getNewPosts()
    {
        foreach ($this->config['subreddits'] as $subreddit) {
            try {
                $request = $this->client
                    ->request('GET', sprintf('https://oauth.reddit.com/r/%s/new/.json', $subreddit), [
                        'headers' => $this->headers,
                        'query' => [
                            'limit' => 10
                        ]]);
                $response = json_decode($request->getBody(), true);
                $this->things[$subreddit] = $response['data']['children'];
            } catch (Exception $e) {
                if ($e->getCode() === 401) {
                    unlink(__DIR__ . '/../tmp/tokens.reddit');
                }

                empleadoEstatal::$log->addCritical('GetWorker: Failed to get subreddit /new posts: ' . $e->getMessage());
            }
        }

        return $this->things;
    }

    public function filterPosts()
    {
        foreach ($this->things as $subreddit => $things) {
            foreach ($things as $key => $thing) {
                foreach ($this->config['banned_domains'] as $banned_domain) {
                    // Match domains and full urls (full urls are matched to discard banned extensions, like *.pdf)
                    if (fnmatch($banned_domain, $thing['data']['domain'], FNM_CASEFOLD) || fnmatch($banned_domain, $thing['data']['url'], FNM_CASEFOLD)) {
                        empleadoEstatal::$log->addDebug(sprintf('GetWorker: Discarded %s. Banned domain: %s. Matched rule: %s', $thing['data']['name'], $thing['data']['domain'], $banned_domain));
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
            $parent_comment_id = null;

            /**
             * @var $thing Post
             */
            try {
                if (count($thing->parent()) > 0) {
                    // Is child, first check if parent already commented
                    if (is_null($thing->parent()->get()->first()->comment_id)) {
                        continue;
                    } else {
                        $parent_comment_id = $thing->parent()->get()->first()->comment_id;
                    }
                }
                $thing->tries++;

                $request = $this->client->request('POST', 'https://oauth.reddit.com/api/comment', [
                    'headers' => $this->headers,
                    'form_params' => [
                        'api_type' => 'json',
                        'thing_id' => $parent_comment_id ?? $thing->thing,
                        'text' => $thing->markdown
                    ]
                ]);

                /*
                 * Check for errors on the response and handle them.
                 */
                $response = json_decode((string)$request->getBody(), true);
                if (isset($response['json']['errors']) && !empty($response['json']['errors'])) {
                    throw new Exception(sprintf('Error from reddit: %s', json_encode($response['json']['errors'])));
                }

                $thing->status = empleadoEstatal::THING_POSTED;
                $thing->comment_id = $response['json']['data']['things'][0]['data']['name'];

                if (in_array($thing->subreddit, $this->config['distinguishable']) && count($thing->parent()) === 0) {
                    try {
                        $this->client->request('POST', 'https://oauth.reddit.com/api/distinguish', [
                            'headers' => $this->headers,
                            'form_params' => [
                                'api_type' => 'json',
                                'how' => 'yes',
                                'sticky' => true,
                                'id' => $response['json']['data']['things'][0]['data']['name']
                            ]
                        ]);
                    } catch (Exception $e) {
                        empleadoEstatal::$log->addError(sprintf('PostWorker: Failed to distinguish %s: %s. Are you a mod?', $thing->thing, $e->getMessage()));
                    }
                }

                empleadoEstatal::$log->addInfo(sprintf('PostWorker: posted %s.', $thing->thing));
            } catch (Exception $e) {
                $thing->info = substr($e->getMessage(), 0, 254);
                empleadoEstatal::$log->addCritical(sprintf('PostWorker: Failed to post %s: %s', $thing->thing, $e->getMessage()));
            } finally {
                $thing->save();
            }
        }
    }
}
