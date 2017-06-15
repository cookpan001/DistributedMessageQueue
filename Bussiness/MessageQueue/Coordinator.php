<?php

namespace cookpan001\Listener\Bussiness\MessageQueue;

class Coordinator
{
    private $client;
    private $emiter;
    private $storage;
    public $logger = null;
    public $keys = array();
    //public $connections = array();
    public $app = null;
    public $name = null;
    
    public function __construct($app, $name, $client)
    {
        $this->app = $app;
        $this->logger = $this->app->logger;
        $this->storage = $this->app->storage;
        $this->emiter = $this->app->emiter;
        $this->client = $client;
        $this->name = strtolower($name);
    }
    
    public function __destruct()
    {
        $this->logger = null;
    }
    
    public function getInstance($name)
    {
        return $this->app->getInstance($name);
    }
    
    /**
     * 连接到服务器时触发
     */
    public function onConnect($from)
    {
        $this->logger->log(__CLASS__.':'.__FUNCTION__);
        $mediator = $this->getInstance('mediator');
        $keys = array_keys($mediator->keys);
        $values = array_map('array_sum', $mediator->keys);
        $host = $this->app->getConfig('exchanger', 'host');
        $port = $this->app->getConfig('exchanger', 'port');
        $this->client->push($from, 'register', $host, $port, $keys, $values);
    }
    /**
     * Agent收到Socket的消息时触发
     */
    public function onMessage($from, $data)
    {
        $this->logger->log(__CLASS__.':'.__FUNCTION__.': '.__LINE__);
        if(empty($data)){
            return;
        }
        foreach($data as $param){
            if(!is_array($param)){
                $param = preg_split('#\s+#', (string)$param);
            }
            $cmd = array_shift($param);
            $command = 'ex'.ucfirst($cmd);
            if(method_exists($this, $command)){
                call_user_func(array($this, $command), $from, ...$param);
            }
        }
    }
    
    public function exNotify($from, $keys, $values)
    {
        foreach($keys as $i => $key){
            if($values[$i] <= 0){
                unset($this->keys[$key][$from]);
                //unset($this->connections[$from][$key]);
            }else{
                $this->keys[$key][$from] = $values[$i];
                //$this->connections[$from][$key] = 1;
            }
        }
    }
    
    public function exPush($from, $key, $value)
    {
        if(!isset($this->keys[$key])){
            return false;
        }
        $localExchanger = $this->app->getConfig('exchanger', 'host').':'.$this->app->getConfig('exchanger', 'port');
        $mediator = $this->getInstance('mediator');
        foreach($this->keys[$key] as $from => $num){
            if($from == $localExchanger){
                continue;
            }
            if($num <= 0){
                continue;
            }
            if($mediator){
                $mediator->push($key, $value);
                return true;
            }
        }
        return false;
    }
    
    public function exBroadcast($from, $key, $values)
    {
        if(!isset($this->keys[$key])){
            return false;
        }
        $localExchanger = $this->app->getConfig('exchanger', 'host').':'.$this->app->getConfig('exchanger', 'port');
        $mediator = $this->getInstance('mediator');
        foreach($this->keys[$key] as $from => $num){
            if($from == $localExchanger){
                continue;
            }
            if($num <= 0){
                continue;
            }
            if($mediator){
                $mediator->receiveBroadcast($key, $values);
            }
        }
        return false;
    }
    
    public function notify($keys, $values)
    {
        $this->client->broadcast('notify', $keys, $values);
        foreach($keys as $i => $key){
            if($values[$i] <= 0){
                unset($this->keys[$key][0]);
            }else{
                $this->keys[$key][0] = $values[$i];
            }
        }
    }
}