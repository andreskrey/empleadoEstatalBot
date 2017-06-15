<?php

namespace empleadoEstatalBot;

use empleadoEstatalBot\NewspaperProcessor\NewspaperProcessor;
use empleadoEstatalBot\RedditManager\RedditManager;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../vendor/autoload.php';


$console = new Application();
$console->register('get:start')
    ->setDescription('Start the Get worker.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $output->writeln($empleado->get());
    });

$console->register('fetch:start')
    ->setDescription('Start the Fetch worker.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $output->writeln($empleado->fetch());
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
            $output->writeln($empleado->fetch());
        }
        $output->writeln($empleado->post());
    });

$console->register('config:seed')
    ->setDescription('Seed the db.')
    ->setCode(function (InputInterface $input, OutputInterface $output) {
        $empleado = new empleadoEstatal();
        $output->writeln($empleado->seed());
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

    public function __construct()
    {
        self::$log = new Logger('ChePibe');
        self::$log->pushHandler(new StreamHandler('tmp/empleadoEstatalBot.log'));

        try {
            $this->config = Yaml::parse(file_get_contents('config/config.yml'));
        } catch (\Exception $e) {
            self::$log->addCritical('Missing or wrong config: ' . $e->getMessage());
            throw $e;
        }

        try {
            $capsule = new Capsule;

            $capsule->addConnection([
                'driver' => 'mysql',
                'host' => $this->config['database']['host'],
                'database' => $this->config['database']['name'],
                'username' => $this->config['database']['user'],
                'password' => $this->config['database']['pass'],
                'charset' => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix' => '',
            ]);

            $capsule->setAsGlobal();
            $capsule->bootEloquent();

        } catch (\Exception $e) {
            self::$log->addCritical('Cannot connect to database: ' . $e->getMessage());
            throw $e;
        }

        if ($this->config['bot']['debug']) {
            $this->config['bot']['subreddits'] = 'empleadoEstatalBot';
        }
    }

    public function get()
    {
        self::$log->addInfo('GetWorker: Starting...');
        $reddit = new RedditManager($this->config['reddit']);
        $reddit->login();
        $reddit->getNewPosts();
        $reddit->filterPosts();
        $reddit->savePosts();
        self::$log->addInfo('GetWorker: End.');
    }

    public function fetch()
    {
        self::$log->addInfo('FetchWorker: Starting...');
        $news = new NewspaperProcessor($this->config['newspaper_processor']);
        $news->getNewspaperText();
        self::$log->addInfo('FetchWorker: End.');
    }

    public function post()
    {
        self::$log->addInfo('PostWorker: Starting...');
        $reddit = new RedditManager($this->config['reddit']);
        $reddit->login();
        $reddit->postComments();
        self::$log->addInfo('PostWorker: End.');
    }


    public function seed()
    {
        Capsule::schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->string('subreddit');
            $table->string('thing')->unique();
            $table->string('url');
            $table->text('markdown')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('tries')->unsigned()->default(0);
            $table->string('info')->nullable()->default(null);
            $table->timestamps();
        });

        return 'Done.';
    }
}
