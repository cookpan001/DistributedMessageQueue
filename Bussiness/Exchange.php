<?php

namespace cookpan001\Listener\Bussiness;

use \cookpan001\Listener\Logger;

class Exchange
{
    public $register = array();
    public $keys = array();
    
    public $logger = null;
    
    public function __construct($storage)
    {
        $this->logger = new Logger;
        $this->storage = $storage;
    }
    
    public function __destruct()
    {
        $this->logger = null;
    }
    
    /**
     * 服务器间信息交换时使用
     */
    public function onExchage($conn, $data)
    {
        if(empty($data)){
            return;
        }
        $this->logger->log(__FUNCTION__.': '.json_encode($data));
        foreach($data as $param){
            $cmd = array_shift($param);
            if(method_exists($this, $cmd)){
                call_user_func(array($this, $cmd), $conn, ...$param);
            }
        }
    }
    
    public function onConnect($conn)
    {
        if(!isset($this->register[$conn->id])){
            $this->register[$conn->id] = $conn;
        }
        $conn->on('close', function($id) use ($conn){
            unset($this->register[$id]);
        });
    }
    
    public function register($conn, ...$para)
    {
        $conn->reply($conn->id);
    }
    
    public function notify($conn, ...$para)
    {
        
    }
}