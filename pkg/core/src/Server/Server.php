<?php
/**
 * Created by PhpStorm.
 * User: brewlin
 * Date: 2019/6/13
 * Time: 23:08
 */

namespace Core\Server;


use Core\Console\Console;
use Core\Container\Container;
use Core\Swoole\SwooleEvent;
use Process\ProcessManager;
use Log\Helper\CLog;
use Stdlib\Helper\Dir;
use Stdlib\Helper\Sys;
use Swoole\Server as SwooleServer;
use Task\Listeners\TaskEventListener;

class Server
{
    private static $server;
    /**
     * @var \Swoole\Server | \Swoole\WebSocket\Server
     */
    protected $swooleServer;

    protected $mode = SWOOLE_PROCESS;
    protected $type = SWOOLE_SOCK_TCP;

    protected $setting = [];
    public $master_pid;
    public $manager_pid;

    protected $httpListener;
    protected $listener = [];
    protected $panel;

    /**
     * Pid file
     *
     * @var string
     */
    protected $pidFile = '/runtime/cloud.pid';

    /**
     * Pid name
     *
     * @var string
     */
    protected $pidName = "im-cloud";

    /**
     * Record started server PIDs and with current workerId
     *
     * @var array
     */
    private $pidMap = [
        'masterPid'  => 0,
        'managerPid' => 0,
        // if = 0, current is at master/manager process.
        'workerPid'  => 0,
        // if < 0, current is at master/manager process.
        'workerId'   => -1,
    ];
    public static function getServer():?Server
    {
        return self::$server;
    }

    /**
     * @return \Swoole\Server | \Swoole\WebSocket\Server
     */
    public function getSwooleServer(){
       return $this->swooleServer;
    }
    public function __construct()
    {
        $this->setting = $this->defaultSetting();
        self::$server = $this;

    }
    /**
     * @return array
     */
    public function defaultEvents(): array
    {
        //check task worker enable coroutinue;
        return [
            SwooleEvent::START         => [$this, 'onStart'],
            SwooleEvent::SHUTDOWN      => [$this, 'onShutdown'],
            SwooleEvent::MANAGER_START => [$this, 'onManagerStart'],
            SwooleEvent::MANAGER_STOP  => [$this, 'onManagerStop'],
            SwooleEvent::WORKER_START  => [$this, 'onWorkerStart'],
            SwooleEvent::WORKER_STOP   => [$this, 'onWorkerStop'],
            SwooleEvent::WORKER_ERROR  => [$this, 'onWorkerError'],
            SwooleEvent::FINISH        => [bean(TaskEventListener::class),'onFinish'],
            SwooleEvent::TASK          => [
                bean(TaskEventListener::class),
                env("TASK_ENABLE_COROUTINE",false)?"onCoTask":'onTask']
        ];
    }
    /**
     * start the swoole
     */
    protected function startSwoole():void{
        //set the swoole setting
        $this->swooleServer->set($this->setting);

        $defaultEvents = $this->defaultEvents();
        $swooleEvents  = array_merge($defaultEvents, $this->httpListener);

        // Add event
        $this->addEvent($this->swooleServer, $swooleEvents, $defaultEvents);

        // Add port listener
        $this->addListener();



        // Storage server instance
        self::$server = $this;

        $this->consoleshow();
        //load child process
        ProcessManager::load($this->swooleServer);
        // Start swoole server
        $this->swooleServer->start();
    }
    /**
     * Add events
     *
     * @param SwooleServer|SwooleServer\Port $server
     * @param array                  $swooleEvents
     * @param array                  $defaultEvents
     *
     */
    protected function addEvent($server, array $swooleEvents, array $defaultEvents = []): void
    {
        foreach ($swooleEvents as $name => $listener) {
            // Default events
            $listenerMethod = sprintf('on%s', ucfirst($name));
            if (isset($defaultEvents[$name])) {
                $server->on($name,is_array($listener)?$listener:[$listener,$listenerMethod]);
                continue;
            }

            if (!isset(SwooleEvent::LISTENER_MAPPING[$name])) {
                throw new \Exception(sprintf('Swoole %s event is not defined!', $name));
            }
            $listenerInterface = SwooleEvent::LISTENER_MAPPING[$name];
            if (!($listener instanceof $listenerInterface)) {
                throw new \Exception(sprintf('Swoole %s event listener is not %s', $name, $listenerInterface));
            }

            $server->on($name, [$listener, $listenerMethod]);
        }
    }
    /**
     * Add listener
     *
     * @throws \Exception
     */
    protected function addListener(): void
    {
        foreach ($this->listener as $listener) {
            if (!$listener instanceof ServerInterface) {
                continue;
            }

            $host = $listener->getHost();
            $port = $listener->getPort();
            $type = $listener->getType();
            $on   = $listener->getOn();

            /* @var SwooleServer\Port $server */
            $server = $this->swooleServer->listen($host, $port, $type);
            $server->set([
                'open_eof_check'     => false,
                'package_max_length' => 2048
            ]);
            $this->addEvent($server, $on);
        }
    }
    /**
     * Set pid map
     *
     * @param SwooleServer $server
     */
    protected function setPidMap(SwooleServer $server): void
    {
        if ($server->master_pid > 0) {
            $this->pidMap['masterPid'] = $server->master_pid;
        }

        if ($server->manager_pid > 0) {
            $this->pidMap['managerPid'] = $server->manager_pid;
        }
    }
    /**
     * On master start event
     *
     * @param SwooleServer $server
     *
     * @return void
     */
    public function onStart(SwooleServer $server): void
    {
        // Save PID to property
        $this->setPidMap($server);

        $masterPid  = $server->master_pid;
        $managerPid = $server->manager_pid;

        $pidStr = sprintf('%s,%s', $masterPid, $managerPid);
        $title  = sprintf('%s master process (%s)', $this->pidName, ROOT);

        // Save PID to file
        $pidFile = ROOT.$this->pidFile;
        Dir::make(dirname($pidFile));
        file_put_contents($pidFile, $pidStr);
        // Set process title
        Sys::setProcessTitle($title);

        // Update setting property
//        $this->setSetting($server->setting);

    }

    /**
     * Manager start event
     * @param SwooleServer $server
     */
    public function onManagerStart(SwooleServer $server): void
    {
//        CLog::info("swoole manage  process pid: {$server->manager_pid} is start");
        // Server pid map
        $this->setPidMap($server);
        // Set process title
    }

    /**
     * Manager stop event
     * @param SwooleServer $server
     */
    public function onManagerStop(SwooleServer $server): void
    {
    }

    /**
     * Worker start event
     *
     * @param SwooleServer $server
     * @param int      $workerId
     */
    public function onWorkerStart(SwooleServer $server, int $workerId): void
    {
        CLog::info("swoole worker process pid:{$server->worker_pid} is start");
        // Save PID and workerId
        $this->pidMap['workerId']  = $workerId;
        $this->pidMap['workerPid'] = $server->worker_pid;
    }

    /**
     * Worker stop event
     *
     * @param SwooleServer $server
     * @param int      $workerId
     */
    public function onWorkerStop(SwooleServer $server, int $workerId): void
    {
        CLog::info("swoole worker process {$workerId} is stop");
    }

    /**
     * Worker error stop event
     *
     * @param SwooleServer $server
     * @param int      $workerId
     * @param int      $workerPid
     * @param int      $exitCode
     * @param int      $signal
     */
    public function onWorkerError(SwooleServer $server, int $workerId, int $workerPid, int $exitCode, int $signal): void
    {
    }

    /**
     * Shutdown event
     * @param SwooleServer $server
     */
    public function onShutdown(SwooleServer $server): void
    {
        var_dump("shutdown");
    }
    /**
     * console  write
     */
    public function consoleshow(){
        // Listener
        foreach ($this->listener as $name => $listener) {
            if (!$listener instanceof ServerInterface) {
                continue;
            }
            $this->panel[$name] = [
                'listen' => sprintf('%s:%s', $listener->getHost(), $listener->getPort()),
                'type'   => $listener->getTypeName(),
                "model" => "TCP",
                'worker' => env("WORKER_NUM",1)
            ];
        }

        show($this->panel,env("APP_NAME"));
        if(env("ENABLE_HTTP",false))
        Console::write("<success>HTTP server {$this->panel['HTTP']['listen']} start success !</success>");
        if(env("ENABLE_WS",false)){
            Console::write("<success>WEBSOCKET server {$this->panel['WEBSOCKET']['listen']} start success !</success>");
        }
        if(env("ENABLE_GRPC",false)){
            Console::write("<success>GRPC server {$this->panel['GRPC']['listen']} start success !</success>");
        }
        foreach ($this->listener as $name => $listener) {
            if (!$listener instanceof ServerInterface) {
                continue;
            }
            Console::write("<success>{$name} server {$listener->getPort()} start success !</success>");
        }
    }
}
