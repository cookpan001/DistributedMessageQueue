<?php

namespace cookpan001\Listener;

class Server
{
    const OPTION_WORKER = 1;
    const OPTION_MULTI = 2;
    const OPTION_REQUEST = 4;
    const OPTION_REPLY = 8;
    const OPTION_HEARTBEAT = 16;
    const OPTION_NOTIFY = 32;
    const OPTION_TO_WORKER = 64;
    const OPTION_TO_SERVER = 128;
    const OPTION_WORKER_STATUS = 256;
    const OPTION_SERVER_STATUS = 512;
    
    const FRAME_SIZE = 1500;
    
    /**
     * 服务器Socket
     * @var resource 
     */
    public $socket = null;
    public $host = '0.0.0.0';
    public $port = 6379;
    public $interval = 900;
    public $path = './dump.file';
    public $logPath = __DIR__ . DIRECTORY_SEPARATOR;
    public $service = 'proxy';
    public $terminate = 0;
    
    public $serverWatcher = null;
    public $defaultLoop = null;
    public $socketLoop = null;
    /**
     * 客户端
     * @var array 
     */
    public $connections = array();
    /**
     * 预处理
     */
    public $handler = null;//hook
    /**
     * 处理服务器状态
     */
    public $daemon = null;
    public $logger = null;
    /**
     * 服务器启动时间
     * @var int
     */
    public $uptime = null;
    public $allConnections = 0;
    /**
     * 上次写回磁盘后添加更新数
     * @var type 
     */
    public $newUpdate = 0;
    /**
     * @var Codec
     */
    public $codec = null;
    
    public $watchers = array();

    public function __construct()
    {
        $this->daemonize();
        $this->setParam();
        $this->initStream();
        $this->uptime = time();
        $this->loop();
    }
    
    public function __destruct()
    {
        if($this->socket){
            socket_close($this->socket);
        }
        foreach($this->connections as $conn){
            $conn->close();
        }
    }
    
    public function setParam()
    {
        $this->handler = new Handler();
        $this->logger = new Logger();
        global $argc, $argv;
        if($argc < 3){
            $this->codec = new Codec\MessagePack();
            return;
        }
        $config = parse_ini_file($argv[1], true);
        $index = $argv[2];
        $this->port = $config[$index]['port'];
        $this->service = $config[$index]['service'];
        if(isset($config[$index]['host'])){
            $this->host = $config[$index]['host'];
        }
        if(isset($config[$index]['interval'])){
            $this->interval = $config[$index]['interval'];
        }
        if(isset($config[$index]['path'])){
            $this->path = $config[$index]['path'];
        }
        if(isset($config[$index]['log_path']) && $config[$index]['log_path']){
            $this->logPath = $config[$index]['log_path'] . DIRECTORY_SEPARATOR;
        }
        if(isset($config[$index]['codec']) && $config[$index]['codec']){
            $codec = $config[$index]['codec'];
            $className = "Codec\{$codec}";
            if(class_exists($className)){
                $this->codec = new $className;
            }else{
                $this->codec = new Codec\MessagePack();
            }
        }else{
            $this->codec = new Codec\MessagePack();
        }
    }
    /**
     * 生成服务器的socket
     */
    public function create()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if(!$socket){
            $this->log("Unable to create socket");
            exit(1);
        }
        if(!socket_bind($socket, $this->host, $this->port)){
            $this->log("Unable to bind socket");
            exit(1);
        }
        if(!socket_listen($socket)){
            $this->log("Unable to bind socket");
            exit(1);
        }
        socket_set_nonblock($socket);
        $this->socket = $socket;
    }
    
    public function loop()
    {
        $this->defaultLoop = \EvLoop::defaultLoop();
        if (\Ev::supportedBackends() & ~\Ev::recommendedBackends() & \Ev::BACKEND_KQUEUE) {
            if(PHP_OS != 'Darwin'){
                $this->socketLoop = new \EvLoop(\Ev::BACKEND_KQUEUE);
            }
        }
        if (!$this->socketLoop) {
            $this->socketLoop = $this->defaultLoop;
        }
    }
    /**
     * 生成守护进程
     */
    public function daemonize()
    {
        umask(0); //把文件掩码清0  
        if (pcntl_fork() != 0){ //是父进程，父进程退出  
            exit();  
        }  
        posix_setsid();//设置新会话组长，脱离终端  
        if (pcntl_fork() != 0){ //是第一子进程，结束第一子进程     
            exit();  
        }
    }
    
    public function initStream()
    {
        global $STDIN, $STDOUT, $STDERR;
        fclose(STDIN);  
        fclose(STDOUT);  
        fclose(STDERR);
        $filename = $this->logPath. "{$this->service}.log";
        $this->output = fopen($filename, 'a');
        $this->errorHandle = fopen($this->logPath . "{$this->service}.error", 'a');
        $STDIN  = fopen('/dev/null', 'r'); // STDIN
        $STDOUT = $this->output; // STDOUT
        $STDERR = $this->errorHandle; // STDERR
        $this->installSignal();
        if (function_exists('gc_enable')){
            gc_enable();
        }
        register_shutdown_function(array($this, 'fatalHandler'));
        set_error_handler(array($this, 'errorHandler'));
    }
    
    public function stop()
    {
        \Ev::stop();
    }
    
    public function restart()
    {
        global $argv;
        $cmd = 'php '.__FILE__ . implode(' ', $argv);
        exec($cmd);
    }
    
    public function installSignal()
    {
        $this->signalWatcher[] = new \EvSignal(SIGTERM, array($this, 'signalHandler'));
        $this->signalWatcher[] = new \EvSignal(SIGUSR2, array($this, 'signalHandler'));
    }
    
    public function signalHandler($w)
    {
        $this->log(json_encode(array(SIGTERM, SIGUSR2, $w->signum)));
        switch ($w->signum) {
            case SIGTERM:
                $this->terminate = 1;
                $this->stop();
                break;
            case SIGUSR2:
                $this->terminate = 1;
                $this->stop();
                $this->restart();
                break;
            default:
                break;
        }
    }
    
    public function log($message)
    {
        $this->logger->log($message);
    }
    
    public function error($message)
    {
        $this->logger->error($message);
    }
    
    public function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $str = sprintf("%s:%d\nerrcode:%d\t%s\n%s\n", $errfile, $errline, $errno, $errstr, json_encode($errcontext, JSON_PARTIAL_OUTPUT_ON_ERROR));
        $this->error($str);
    }
    
    public function fatalHandler()
    {
        if($this->terminate){
            return;
        }
        $error = error_get_last();
        $this->error(json_encode($error, JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
    
    /**
     * 读取连接中发来的数据
     * @return boolean|string
     */
    public function read($id)
    {
        $conn = $this->connections[$id];
        $tmp = '';
        $str = '';
        $i = 0;
        while(true){
            ++$i;
            //$this->log('step: '.$i);
            $num = socket_recv($conn->clientSocket, $tmp, self::FRAME_SIZE, MSG_DONTWAIT);
            if(is_int($num) && $num > 0){
                $str .= $tmp;
            }
            $errorCode = socket_last_error($conn->clientSocket);
            socket_clear_error($conn->clientSocket);
            $this->log("error:".socket_strerror($errorCode) . (', num=='. var_export($num, true)) . (', tmp=='. var_export($tmp, true)));
            if (EAGAIN == $errorCode || EINPROGRESS == $errorCode) {
                break;
            }
            if((0 === $errorCode && null === $tmp) || EPIPE == $errorCode || ECONNRESET == $errorCode){
                if(isset($this->connections[$conn->id])){
                    unset($this->connections[$conn->id]);
                }
                $conn->close();
                return false;
            }
            if(0 === $num){
                $this->log('all data readed');
                break;
            }
        }
        $this->log('receive message: '.$str);
        return $str;
    }
    /**
     * 向连接中写入数据
     * @return boolean
     */
    public function write(Connection $conn, $str)
    {
        $num = socket_write($conn->clientSocket, $str, strlen($str));
        $errorCode = socket_last_error($conn->clientSocket);
        socket_clear_error($conn->clientSocket);
        $this->log("write len: ".json_encode($num).", ". socket_strerror($errorCode).", ". $str);
        if((EPIPE == $errorCode || ECONNRESET == $errorCode)){
            $conn->close();
            if(isset($this->connections[$conn->id])){
                unset($this->connections[$conn->id]);
            }
            return false;
        }
        return $num;
    }
    /**
     * 返回消息
     */
    public function reply($conn, $message)
    {
        if(is_array($message)){
            $data = $this->codec->serialize($message);
        }else{
            $data = $message;
        }
        $this->write($conn, $data);
    }
    /**
     * 推送消息给服务端
     */
    public function push($kind, $seqId, $message)
    {
        $data = $this->codec->encode($kind, $seqId, $message);
        while(count($this->server)){
            if(count($this->server) > 1){
                $i = array_rand($this->server);
            }else{
                $i = key($this->server);
            }
            $connection = $this->server[$i];
            $ret = $this->write($connection, $data);
            if(false === $ret){
                continue;
            }
            $tmp = $this->read($connection->id);
            if (false === $tmp) {
                continue;
            }
            if(is_string($tmp) && strlen($tmp)){
                return $tmp;
            }
            return 1;
        }
        $this->log('push no worker');
        return 0;
    }
    /**
     * 开始监听
     */
    public function start()
    {
        $socket = $this->socket;
        $that = $this;
        $this->serverWatcher = new \EvIo($this->socket, \Ev::READ, function () use ($that, $socket){
            $clientSocket = socket_accept($socket);
            $that->process($clientSocket);
            ++$that->allConnections;
        });
        \Ev::run();
    }
    
    /**
     * 处理到来的新连接
     */
    public function process($clientSocket)
    {
        socket_set_nonblock($clientSocket);
        $conn = new Connection($clientSocket);
        $that = $this;
        $id = uniqid();
        $watcher = new \EvIo($clientSocket, \Ev::READ, function() use ($that, $id){
            $that->log('----------------HANDLE----------------');
            $str = $that->read($id);
            if(false !== $str){
                $commands = $that->codec->unserialize($str);
                $that->handle($id, $commands);
            }
            $that->log('----------------HANDLE FINISH---------');
        });
        $conn->setId($id);
        $conn->setWatcher($watcher);
        $this->connections[$id] = $conn;
        $this->socketLoop->run();
        //$this->log('connection '.$conn->id);
        \Ev::run();
    }
    
    /**
     * 处理连接中发来的指令
     */
    public function handle($id, $commands)
    {
        $conn = $this->connections[$id];
        foreach($commands as $arr){
            if(empty($arr) || !is_array($arr)){
                $this->log("wrong message: ". json_encode($arr));
                continue;
            }
            $this->log("incomming message: ". json_encode($arr, JSON_PARTIAL_OUTPUT_ON_ERROR));
            $this->handler->main($conn, $arr);
        }
    }
}

ini_set('display_errors', 'On');
error_reporting(E_ALL);

include __DIR__.DIRECTORY_SEPARATOR.'autoload.php';
include __DIR__.DIRECTORY_SEPARATOR.'base.php';

$server = new Server();
$server->create();
$server->start();