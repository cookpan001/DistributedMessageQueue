<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

include __DIR__.DIRECTORY_SEPARATOR.'autoload.php';
include __DIR__.DIRECTORY_SEPARATOR.'base.php';

class Pipeline
{
    public $listener = array();
    public $codec = array();
    
    public $config = array(
        6379 => array(
            'codec' => 'cookpan001\Listener\Codec\Redis',
        ),
        6380 => array(
            'codec' => 'cookpan001\Listener\Codec\Redis',
        ),
    );
    
    public function __construct()
    {
        
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
    
    public function init()
    {
        foreach($this->config as $port => $conf){
            $server = new \cookpan001\Listener\Listener($port, $this->getCodec($conf['codec']));
            $server->create();
            $server->setId($port);
            $server->start();
            $this->listener[$server->id] = $server;
        }
        Ev::run();
    }
    
    public function getServer($port)
    {
        if(isset($this->listener[$port])){
            return $this->listener[$port];
        }
        return null;
    }
    
    public function run()
    {
        $codec = new cookpan001\Listener\Codec\Redis();
        $server = new \cookpan001\Listener\Listener(6379, $codec);
        $server->create();
        $server->start();
        $server2 = new \cookpan001\Listener\Listener(6380, $codec);
        $server2->create();
        $server2->start();
        Ev::run();
    }
}

$app = new Pipeline();
$app->init();

