<?php

namespace cookpan001\Listener;

class Initializer
{
    public $config = array();
    public $listener = array();
    public $handler = array();
    public $codec = array();
    public $logger = null;
    public $storage = null;
    public $emiter = null;
    public $ignore = array();
    public $terminate = 0;
    public $signalWatcher = array();
    
    public function __construct()
    {
        $this->logger = new Logger();
        $this->storage = new Storage();
        $this->emiter = new Emiter();
    }
    
    public function __destruct()
    {
        
    }
    
    public function __call($name, $arguments)
    {
        var_export('unknown method: '.$name . ', args: '. json_encode($arguments)."\n");
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
        $server = new Listener($conf['host'], $conf['port'], $this->getCodec($conf['codec']), $this->logger);
        $server->create();
        $server->setId($conf['name']);
        if(isset($conf['class']) && class_exists($conf['class'])){
            $obj = new $conf['class']($this, $conf['name']);
            foreach($conf['emit'] as $condition => $callback){
                $this->emiter->on($condition, array($obj, $callback));
            }
            $this->handler[strtolower($conf['name'])] = $obj;
            $server->setHandler($obj);
        }
        foreach($conf['on'] as $condition => $callback){
            if(is_callable($callback)){
                $server->on($condition, $callback);
            }else if(isset($obj)){
                $server->on($condition, array($obj, $callback));
            }
        }
        return $server;
    }
    
    public function createClient($conf)
    {
        if($conf['role'] == 'agent'){
            $instances = array();
            foreach($conf['instance'] as $line){
                if(!isset($this->ignore[implode(':', $line)])){
                    $instances[] = $line;
                }
            }
            $client = new Agent($instances);
        }else{
            $client = new Client($conf['host'], $conf['port']);
        }
        $client->setCodec($this->getCodec($conf['codec']));
        $client->setLogger($this->logger);
        $client->setId($conf['name']);
        if(isset($conf['class']) && class_exists($conf['class'])){
            $obj = new $conf['class']($this, $conf['name'], $client);
            foreach($conf['emit'] as $condition => $callback){
                $this->emiter->on($condition, array($obj, $callback));
            }
            $this->handler[strtolower($conf['name'])] = $obj;
            $client->setHandler($obj);
        }
        foreach($conf['on'] as $condition => $callback){
            if(is_callable($callback)){
                $client->on($condition, $callback);
            }else if(isset($obj)){
                $client->on($condition, array($obj, $callback));
            }
        }
        return $client;
    }
    /**
     * 单进程监听多个端口
     */
    public function init($config)
    {
        $after = array();
        foreach($config as $conf){
            if($conf['role'] == 'server'){
                $app = $this->createServer($conf);
                $this->listener[$app->id] = $app;
                $this->ignore[$conf['host'].':'. $conf['port']] = 1;
            }else{
                $app = $this->createClient($conf);
                $this->listener[$app->id] = $app;
            }
            if(isset($conf['after'])){
                foreach($conf['after'] as $funcName){
                    if(is_callable($funcName)){
                        $after[$app->id] = $funcName;
                    }else{
                        $after[$app->id] = array($app->handler, $funcName);
                    }
                }
            }
            $this->config[strtolower($conf['name'])] = $conf;
        }
        foreach($this->listener as $app){
            $app->start();
        }
        foreach($after as $appId => $func){
            if(is_callable($func)){
                call_user_func($func, $this->listener[$appId], $this->config[$appId]);
            }
        }
    }
    
    public function getInstance($name0)
    {
        $name = strtolower($name0);
        if(isset($this->handler[$name])){
            return $this->handler[$name];
        }
        return null;
    }
    
    public function getConfig($name0, $field = '')
    {
        $name = strtolower($name0);
        if($field){
            if(isset($this->config[$name][$field])){
                return $this->config[$name][$field];
            }
            return null;
        }
        if(isset($this->config[$name])){
            return $this->config[$name];
        }
        return null;
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
    
    public function initStream($service, $logPath = '')
    {
        global $STDIN, $STDOUT, $STDERR;
        fclose(STDIN);  
        fclose(STDOUT);  
        fclose(STDERR);
        if('' == $logPath || !file_exists($logPath)){
            $logPath = __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
        }
        $STDIN  = fopen('/dev/null', 'r'); // STDIN
        $STDOUT = fopen($logPath. "{$service}.log", 'a'); // STDOUT
        $STDERR = fopen($logPath . "{$service}.error", 'a'); // STDERR
        $this->signalWatcher[SIGTERM] = new \EvSignal(SIGTERM, function(){
            $this->terminate = 1;
            //$this->stop();
        });
        $this->signalWatcher[SIGUSR2] = new \EvSignal(SIGUSR2, function(){
            $this->terminate = 1;
            //$this->stop();
            //$this->restart();
        });
        if (function_exists('gc_enable')){
            gc_enable();
        }
        register_shutdown_function(function(){
            if($this->terminate){
                return;
            }
            $error = error_get_last();
            $this->logger->error(json_encode($error, JSON_PARTIAL_OUTPUT_ON_ERROR));
        });
        set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext){
            $str = sprintf("%s:%d\nerrcode:%d\t%s\n%s\n", $errfile, $errline, $errno, $errstr, json_encode($errcontext, JSON_PARTIAL_OUTPUT_ON_ERROR));
            $this->logger->error($str);
        });
    }
    
    public function stop()
    {
        
    }
    
    public function restart()
    {
        
    }
    
    public function start()
    {
        \Ev::run();
    }
}