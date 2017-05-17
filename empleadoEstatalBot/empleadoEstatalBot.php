<?php
namespace empleadoEstatalBot;

use empleadoEstatalBot\NewspaperProcessor\NewspaperProcessor;
use empleadoEstatalBot\RedditManager\RedditManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

define('APP_PATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getenv('CURRENT_ENV') == 'HEROKU') {
    require_once(APP_PATH . '../config/config.heroku.php');
    new Config();
} else {
    require_once(APP_PATH . '../config/config.php');
}
require APP_PATH . '../vendor/autoload.php';


class empleadoEstatal
{
    static public $log;

    static public $debug = false;

    public function __construct()
    {
        if (php_sapi_name() == "cli") self::$debug = true;

        self::$log = new Logger('chePibe');
        self::$log->pushHandler(new StreamHandler('php://stderr'));
    }

    private function generatePosts($things)
    {
        foreach ($things as $k => $i) {

            $parser = new NewspaperProcessor($i['data']['url'], [
                'fixRelativeURLs' => true,
                'normalizeEntities' => true
            ]);

            $text = $parser->getNewspaperText();
            $things[$k]['parsed'] = $parser->parse($text);
            if (!$things[$k]['parsed']) {
                self::$log->addInfo('Post ' . $i['data']['id'] . ' discarded. Failed to parse.');
                unset($things[$k]);
            }
        }

        return $things;
    }

    public function laburar()
    {
        $reddit = new RedditManager();
        $reddit->login();
        $posts = $reddit->getNewPosts();

        if ($posts) {
            $posts = $this->generatePosts($posts);
            $reddit->postComments($posts);
            self::$log->addInfo('Done posting comments.');
        } else {
            self::$log->addInfo('No new posts.');
        }

        return count($posts);
    }

    public function laburarPost($id = null)
    {
        if (!$id) return false;

        $reddit = new RedditManager();
        $reddit->login();
        $posts = $reddit->getPost($id);

        if ($posts) {
            $posts = $this->generatePosts($posts);
            $reddit->postComments($posts);
            self::$log->addInfo('Done posting comments.');
        } else {
            self::$log->addInfo('No new posts.');
        }

        return count($posts);
    }
}
