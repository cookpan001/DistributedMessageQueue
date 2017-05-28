<?php
//http://www.jianshu.com/p/f440c19e77ac
ini_set('display_errors', 'On');
error_reporting(E_ALL);

include __DIR__.DIRECTORY_SEPARATOR.'autoload.php';
include __DIR__.DIRECTORY_SEPARATOR.'base.php';

class Pipeline
{
    public $listener = array();
    public $worker = array();
    public $codec = array();
    public $parent = null;
    public $logger = null;
    public $storage = null;
    public $emiter = null;
    
    public $config = array(
        array(
            'codec' => 'cookpan001\Listener\Codec\Redis',
            'class' => 'cookpan001\Listener\Bussiness\Acceptor',
            'role' => 'server',
            'port' => 6379,
            'worker' => 1,
            'on' => array(
                'connect' => 'onConnect',
                'message' => 'onMessage',
            ),
            'emit' => array(
                'waitor' => 'onWaitor',
            ),
        ),
        array(
            'codec' => 'cookpan001\Listener\Codec\Redis',
            'class' => 'cookpan001\Listener\Bussiness\Mediator',
            'role' => 'server',
            'port' => 6380,
            'worker' => 1,
            'on' => array(
                'connect' => 'onConnect',
                'message' => 'onExchage',
            ),
            'emit' => array(
                '' => '',
            ),
        ),
        array(
            'codec' => 'cookpan001\Listener\Codec\Redis',
            'class' => 'cookpan001\Listener\Bussiness\Waitor',
            'role' => 'client',
            'host' => '127.0.0.1',
            'port' => 6380,
            'worker' => 1,
            'on' => array(
                'connect' => 'onConnect',
                'message' => 'onReceive',
            ),
            'emit' => array(
                'notify' => 'onNotify',
            ),
        ),
        array(
            'codec' => 'cookpan001\Listener\Codec\Redis',
            'class' => 'cookpan001\Listener\Bussiness\Waitor',
            'role' => 'client',
            'host' => '127.0.0.1',
            'port' => 6379,
            'worker' => 1,
            'on' => array(
                'connect' => 'onConnect',
                'message' => 'onReceive',
            ),
            'emit' => array(
                'notify' => 'onNotify',
            ),
        ),
    );
    
    public function __construct()
    {
        $this->logger = new cookpan001\Listener\Logger();
        $this->storage = new cookpan001\Listener\Storage();
        $this->emiter = new cookpan001\Listener\Emiter();
    }
    
    public function __destruct()
    {
        
    }
    
    public function __call($name, $arguments)
    {
        var_export('unknown method: '.$name . ', args: '. json_encode($arguments)."\n");
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
    
    public function getCodec($codec)
    {
        if(!isset($this->codec[$codec])){
            $this->codec[$codec] = new $codec;
        }
        return $this->codec[$codec];
    }
    
    public function createServer($conf)
    {
        $server = new \cookpan001\Listener\Listener($conf['port'], $this->getCodec($conf['codec']), $this->logger);
        $server->create();
        $server->setId($conf['port']);
        $server->start();
        if(isset($conf['on'])){
            $obj = isset($conf['class']) ? new $conf['class']($this->storage, $this->emiter) : $this;
            foreach($conf['on'] as $condition => $callback){
                if(is_callable($callback)){
                    $server->on($condition, $callback);
                }else{
                    $server->on($condition, array($obj, $callback));
                }
            }
        }
        if(isset($conf['emit'])){
            foreach($conf['emit'] as $condition => $callback){
                $this->emiter->on($condition, array($server, $callback));
            }
        }
        return $server;
    }
    
    public function createClient($conf)
    {
        $client = new \cookpan001\Listener\Client($conf['host'], $conf['port']);
        $client->setCodec($this->getCodec($conf['codec']));
        $client->setLogger($this->logger);
        $client->setId('client');
        $client->connect();
        $client->process();
        if(isset($conf['on'])){
            $obj = isset($conf['class']) ? new $conf['class']($client, $this->storage, $this->emiter) : $this;
            foreach($conf['on'] as $condition => $callback){
                if(is_callable($callback)){
                    $client->on($condition, $callback);
                }else{
                    $client->on($condition, array($obj, $callback));
                }
            }
        }
        if(isset($conf['emit'])){
            foreach($conf['emit'] as $condition => $callback){
                $this->emiter->on($condition, array($client, $callback));
            }
        }
        return $client;
    }
    /**
     * 单进程监听多个端口
     */
    public function init()
    {
        foreach($this->config as $conf){
            if($conf['role'] == 'server'){
                $server = $this->createServer($conf);
                $this->listener[$server->id] = $server;
            }else{
                $client = $this->createClient($conf);
                $this->listener[$client->id] = $client;
            }
        }
    }
    /**
     * 每个子进程监听一个端口
     */
    public function initByFork()
    {
        foreach($this->config as $port => $conf){
            if(!is_null($this->parent)){
                return;
            }
            $this->fork($port, $conf);
        }
    }
    
    public function fork($port, $conf)
    {
        $ary = array();
        if (socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $ary) === false) {
            echo "socket_create_pair() failed. Reason: " . socket_strerror(socket_last_error());
        }
        $pid = pcntl_fork();
        if ($pid == -1) {
            echo 'Could not fork Process.';
        } elseif ($pid) {/* parent */
            socket_close($ary[0]);
            $this->worker[$port] = $ary[1];
        } else {/* child */
            socket_close($ary[1]);
            $this->parent = $ary[0];
            if($conf['role'] == 'server'){
                $server = $this->createServer($conf);
                //$this->listener[$server->id] = $server;
            }else{
                $client = $this->createClient($conf);
                //$this->listener[$client->id] = $client;
            }
        }
    }
}

$app = new Pipeline();
$app->init();
Ev::run();
