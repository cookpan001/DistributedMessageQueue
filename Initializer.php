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
        $obj = new $conf['class']($this, $conf['name']);
        if(isset($conf['on'])){
            foreach($conf['on'] as $condition => $callback){
                if(is_callable($callback)){
                    $server->on($condition, $callback);
                }else{
                    $server->on($condition, array($obj, $callback));
                }
            }
            $this->handler[strtolower($conf['name'])] = $obj;
        }
        if(isset($conf['emit'])){
            foreach($conf['emit'] as $condition => $callback){
                $this->emiter->on($condition, array($obj, $callback));
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
        $obj = new $conf['class']($this, $conf['name'], $client);
        if(isset($conf['on'])){
            foreach($conf['on'] as $condition => $callback){
                if(is_callable($callback)){
                    $client->on($condition, $callback);
                }else{
                    $client->on($condition, array($obj, $callback));
                }
            }
            $this->handler[strtolower($conf['name'])] = $obj;
        }
        if(isset($conf['emit'])){
            foreach($conf['emit'] as $condition => $callback){
                $this->emiter->on($condition, array($obj, $callback));
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
//            if(isset($config['after'])){
//                foreach($config['after'] as $funcName){
//                    $after[] = array($this->listener[$app->id], $funcName);
//                }
//            }
            $this->config[strtolower($conf['name'])] = $conf;
        }
//        foreach($after as $func){
//            list($obj, $name) = $func;
//            if(method_exists($obj, $name)){
//                call_user_func($func);
//            }
//        }
        foreach($this->listener as $app){
            $app->start();
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
    
    public function start()
    {
        \Ev::run();
    }
}