<?php

namespace cookpan001\Listener\Bussiness;

class Exchanger
{
    public $keys = array();
    public $connections = array();
    public $register = array();
    
    public $logger = null;
    public $app = null;
    
    public function __construct($app)
    {
        $this->app = $app;
    }
    
    public function __destruct()
    {
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
        $this->app->logger->log(__FUNCTION__.': '.json_encode($data));
        foreach($data as $param){
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
            if(isset($this->register[$id])){
                unset($this->register[$id]);
            }
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
                foreach($this->register as $brotherId => $__){
                    if(!isset($this->connections[$brotherId])){
                        continue;
                    }
                    $conn = $this->connections[$brotherId];
                    $conn->reply('notify', array_keys($update), array_values($update));
                }
            }
        });
    }
    
    //有其他Mediator连接到来，发送本地监听的key到该连接
    public function exRegister($conn, $host, $port)
    {
        $this->register[$conn->id] = $host .':'. $port;
        $conn->reply('notify', array_keys($this->keys), array_map('array_sum', $this->keys));
    }
    
    public function exInfo($conn)
    {
        $info = array(
            'connections: '.count($this->connections),
            'keys: '.json_encode(array_keys($this->keys)),
            'keys_detail: '.array_map('array_sum', $this->keys),
            'registered: '.json_encode(array_values($this->register)),
        );
        $conn->reply($info);
    }
}