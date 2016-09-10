<?php
namespace empleadoEstatalBot;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use empleadoEstatalBot\NewspaperProcessor;
use empleadoEstatalBot\RedditManager;

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

            $newspaper = 'empleadoEstatalBot\NewspaperProcessor\Parsers\\' . str_replace('.', '', $i['data']['domain']) . 'Parser';
            if (class_exists($newspaper)) {
                $parser = new $newspaper;
            } else {
                throw new \BadFunctionCallException();
            }

            $text = $parser->getNewspaperText($i['data']['url']);
            $things[$k]['parsed'] = $parser->parse($text);
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
}
