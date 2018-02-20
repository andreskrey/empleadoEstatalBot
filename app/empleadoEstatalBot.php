<?php

namespace empleadoEstatalBot;

use empleadoEstatalBot\NewspaperProcessor\NewspaperProcessor;
use empleadoEstatalBot\RedditManager\RedditManager;
use empleadoEstatalBot\Utilities\Locker;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';

// Console only
if (PHP_SAPI !== 'cli') {
    return;
}

$console = new Application();
$console->register('get:start')
    ->setDescription('Start the Get worker.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $empleado->get();
    });

$console->register('fetch:start')
    ->setDescription('Start the Fetch worker.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $empleado->fetch();
    });

$console->register('post:start')
    ->addOption('pre-fetch',
        null,
        InputOption::VALUE_NONE,
        'Start the FetchWorker before and then the PostWorker')
    ->setDescription('Start the Post worker.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        if ($input->getOption('pre-fetch')) {
            $empleado->fetch();
        }
        $empleado->post();
    });

$console->register('config:seed')
    ->setDescription('Seed the db.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $empleado->seed();
    });

$console->register('config:clear-locks')
    ->setDescription('Clears all locks.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $empleado->clearLocks();
    });

$console->run();

class empleadoEstatal
{
    public static $log;
    public $db;
    protected $config;

    const THING_REJECTED = -1;
    const THING_TO_FETCH = 1;
    const THING_FETCHED = 2;
    const THING_POSTED = 3;

    const TMP_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;

    public function __construct()
    {
        try {
            $this->config = Yaml::parse(file_get_contents(__DIR__ . '/config/config.yml'));
        } catch (\Exception $e) {
            exit('Missing or wrong config on yml: ' . $e->getMessage());
        }

        self::$log = new Logger('ChePibe');
        self::$log->pushHandler(new RotatingFileHandler(__DIR__ . '/tmp/empleadoEstatalBot.log', 5, $this->config['bot']['log_level']));

        try {
            $capsule = new Capsule;

            $capsule->addConnection([
                'driver' => 'mysql',
                'host' => $this->config['database']['host'],
                'database' => $this->config['database']['name'],
                'username' => $this->config['database']['user'],
                'password' => $this->config['database']['pass'],
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_bin',
                'prefix' => '',
            ]);

            $capsule->setAsGlobal();
            $capsule->bootEloquent();

        } catch (\Exception $e) {
            self::$log->addCritical('Cannot connect to database: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Wrapper for workers. Catches any exception, logs and clears locks.
     *
     * @param $name
     * @param $arguments
     * @throws \Exception
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {
            try {
                return call_user_func_array([$this, $name], $arguments);
            } catch (\Exception $e) {
                self::$log->addEmergency(sprintf('empleadoEstatalBot: General exception. Error no: %s. File: %s. Line: %s. Message: %s ', $e->getCode(), $e->getFile(), $e->getLine(), $e->getMessage()));

                // TODO: Change this to clearLock but first map the worker that was running. Maybe read the PHPdoc of the func?
                Locker::clearLocks();
            }
        } else {
            throw new \BadMethodCallException(sprintf('Function "%s" not found.', $name));
        }
    }

    protected function get()
    {
        if (Locker::checkLock('GetWorker')) {
            self::$log->addInfo('GetWorker: Starting...');
            $reddit = new RedditManager($this->config['reddit']);
            $reddit->login();
            $reddit->getNewPosts();
            $reddit->filterPosts();
            $reddit->savePosts();
            self::$log->addInfo('GetWorker: End.');
            Locker::releaseLock('GetWorker');
        } else {
            self::$log->addNotice('GetWorker: Not allowed to start, lock present.');
        }
    }

    protected function fetch()
    {
        if (Locker::checkLock('FetchWorker')) {
            self::$log->addInfo('FetchWorker: Starting...');
            $news = new NewspaperProcessor($this->config['newspaper_processor']);
            $news->getNewspaperText();
            self::$log->addInfo('FetchWorker: End.');
            Locker::releaseLock('FetchWorker');
        } else {
            self::$log->addNotice('FetchWorker: Not allowed to start, lock present.');
        }
    }

    protected function post()
    {
        if (Locker::checkLock('PostWorker')) {
            self::$log->addInfo('PostWorker: Starting...');
            $reddit = new RedditManager($this->config['reddit']);
            $reddit->login();
            $reddit->postComments();
            self::$log->addInfo('PostWorker: End.');
            Locker::releaseLock('PostWorker');
        } else {
            self::$log->addNotice('PostWorker: Not allowed to start, lock present.');
        }
    }

    public function seed()
    {
        Capsule::schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->string('subreddit');
            $table->string('thing');
            $table->string('url');
            $table->text('markdown')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('tries')->unsigned()->default(0);
            $table->string('info')->nullable()->default(null);
            $table->string('comment_id')->nullable()->default(null);
            $table->integer('parent_id')->nullable()->default(null);
            $table->timestamps();
        });

        return 'Done.';
    }

    public function clearLocks()
    {
        return Locker::clearLocks();
    }
}
