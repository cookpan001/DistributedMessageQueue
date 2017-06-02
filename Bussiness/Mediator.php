<?php

namespace cookpan001\Listener\Bussiness;

class Mediator
{
    private $emiter;
    private $storage;
    
    public $keys = array();
    public $connections = array();
    
    public $logger = null;
    public $app = null;
    public $name = null;
    
    public function __construct($app, $name)
    {
        $this->app = $app;
        $this->logger = $this->app->logger;
        $this->storage = $this->app->storage;
        $this->emiter = $this->app->emiter;
        $this->name = strtolower($name);
    }
    
    public function __destruct()
    {
        $this->logger = null;
        $this->app = null;
    }
    
    public function getInstance($name)
    {
        return $this->app->getInstance($name);
    }
    
    /**
     * Acceptor间信息交换时使用, Socket
     */
    public function onExchage($conn, $data)
    {
        if(empty($data)){
            return;
        }
        $this->logger->log(__CLASS__.':'.__FUNCTION__.':: '.json_encode($data));
        foreach($data as $param){
            if(!is_array($param)){
                $param = preg_split('#\s+#', (string)$param);
            }
            $cmd = array_shift($param);
            $command = 'ex'.ucfirst($cmd);
            if(method_exists($this, $command)){
                call_user_func(array($this, $command), $conn, ...$param);
            }
        }
    }
    
    public function onConnect($conn)
    {
        if(!isset($this->connections[$conn->id])){
            $this->connections[$conn->id] = $conn;
        }
        $conn->on('close', function($id){
            $conn = $this->connections[$id];
            $keys = $conn->keys();
            $update = array();
            foreach($keys as $key){
                if(isset($this->keys[$key][$id])){
                    unset($this->keys[$key][$id]);
                }
                $update[$key] = count($this->keys[$key]);
            }
            unset($this->connections[$id]);
            if($update){
                $coordinator = $this->app->getInstance('coordiantor');
                if($coordinator){
                    $coordinator->notify(array_keys($update), array_values($update));
                }
            }
        });
    }
    
    public function exSend($conn, $key, $value)
    {
        $conn->reply('mediator', 'ack', $key, $value);
        if(isset($this->keys[$key])){
            foreach($this->keys[$key] as $id => $num){
                if($id == $conn->id){
                    continue;
                }
                if($num <= 0){
                    unset($this->keys[$key][$id]);
                    continue;
                }
                if(!isset($this->connections[$id])){
                    unset($this->keys[$key][$id]);
                    continue;
                }
                $this->connections[$id]->reply('mediator', 'push', $key, $value);
                return true;
            }
        }
        $this->storage->set($key, $value);
        $exchanger = $this->app->getInstance('exchanger');
        if($exchanger){
            $ret = $exchanger->push($key, $value);
            if($ret){
                $this->storage->remove($key, $value);
                return true;
            }
        }
        return false;
    }
    
    public function exSubscribe($conn, ...$para)
    {
        $update = array();
        foreach($para as $key){
            if(!isset($this->keys[$key][$conn->id])){
                $this->keys[$key][$conn->id] = 0;
            }
            $this->keys[$key][$conn->id] += 1;
            $conn->subscribe($key);
            $tmp = $this->storage->getAndRemove($key);
            if($tmp){
                $conn->reply('mediator', 'push', $key, ...$tmp);
            }
            $update[$key] = count($this->keys[$key]);
        }
        $this->logger->log(__CLASS__.'::'.__FUNCTION__.', '. json_encode($update));
        if($update){
            $coordinator = $this->app->getInstance('coordinator');
            if($coordinator){
                $coordinator->notify(array_keys($update), array_values($update));
            }
        }
    }
    
    public function exUnsubscribe($conn, ...$para)
    {
        $update = array();
        foreach($para as $key){
            if(isset($this->keys[$key][$conn->id])){
                $this->keys[$key][$conn->id] -= 1;
            }
            if($this->keys[$key][$conn->id] <= 0){
                $conn->unsubscribe($key);
                unset($this->keys[$key][$conn->id]);
            }
            $update[$key] = count($this->keys[$key]);
        }
        $this->logger->log(__CLASS__.'::'.__FUNCTION__.', '. json_encode($update));
        if($update){
            $coordinator = $this->app->getInstance('coordinator');
            if($coordinator){
                $coordinator->notify(array_keys($update), array_values($update));
            }
        }
    }
    
    public function push($key, $value)
    {
        if(empty($this->keys[$key])){
            $this->storage->set($key, $value);
            return false;
        }
        foreach($this->keys[$key] as $id => $num){
            if($num <= 0){
                unset($this->keys[$key][$id]);
                continue;
            }
            if(!isset($this->connections[$id])){
                unset($this->keys[$key][$id]);
                continue;
            }
            $this->connections[$id]->reply('mediator', 'push', $key, $value);
            return true;
        }
        return false;
    }
}