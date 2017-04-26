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
    
    public $config = array(
        array(
            'codec' => 'cookpan001\Listener\Codec\Redis',
            'class' => 'cookpan001\Listener\Bussiness\Service',
            'role' => 'server',
            'port' => 6379,
            'worker' => 1,
            'on' => array(
                'message' => 'onMessage',
            ),
        ),
        array(
            'codec' => 'cookpan001\Listener\Codec\Redis',
            'class' => 'cookpan001\Listener\Bussiness\Exchange',
            'role' => 'server',
            'port' => 6380,
            'worker' => 1,
            'on' => array(
                'message' => 'onExchage',
            ),
        ),
        array(
            'codec' => 'cookpan001\Listener\Codec\Redis',
            'class' => 'cookpan001\Listener\Bussiness\Pubsub',
            'role' => 'client',
            'port' => 6380,
            'worker' => 1,
            'on' => array(
                'connect' => 'onConnect',
                'message' => 'onReceive',
            ),
        ),
    );
    
    public function __construct()
    {
        $this->logger = new cookpan001\Listener\Logger();
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
            $obj = isset($conf['class']) ? new $conf['class'] : $this;
            foreach($conf['on'] as $condition => $callback){
                if(is_callable($callback)){
                    $server->on($condition, $callback);
                }else{
                    $server->on($condition, array($obj, $callback));
                }
            }
        }
        return $server;
    }
    
    public function createClient($conf)
    {
        $client = new \cookpan001\Listener\Client('127.0.0.1', $conf['port']);
        $client->setCodec($this->getCodec($conf['codec']));
        $client->setLogger($this->logger);
        $client->setId('client');
        $client->connect();
        $client->process();
        if(isset($conf['on'])){
            $obj = isset($conf['class']) ? new $conf['class'] : $this;
            foreach($conf['on'] as $condition => $callback){
                if(is_callable($callback)){
                    $client->on($condition, $callback);
                }else{
                    $client->on($condition, array($obj, $callback));
                }
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
    
    public function getServer($port)
    {
        if(isset($this->listener[$port])){
            return $this->listener[$port];
        }
        return null;
    }
//    /**
//     * 主服务器收到从服务器时触发
//     */
//    public function onMessage($server, $conn, $data)
//    {
//        if(empty($data)){
//            return;
//        }
//        $this->logger->log(__FUNCTION__.': '.json_encode($data));
//        $server->reply($conn, 1);
//    }
//    /**
//     * 服务器间信息交换时使用
//     */
//    public function onExchage($server, $conn, $data)
//    {
//        if(empty($data)){
//            return;
//        }
//        $this->logger->log(__FUNCTION__.': '.json_encode($data));
//        $server->reply($conn, 2);
//    }
//    /**
//     * 连接到服务器时触发
//     */
//    public function onConnect($client)
//    {
//        $this->logger->log(__FUNCTION__);
//        $client->push('register', 'client', 1);
//    }
//    /**
//     * 收到服务器的消息时触发
//     */
//    public function onReceive($client, $data)
//    {
//        if(empty($data)){
//            return;
//        }
//        $this->logger->log(__FUNCTION__.': '.json_encode($data));
//    }
}

$app = new Pipeline();
$app->init();
Ev::run();