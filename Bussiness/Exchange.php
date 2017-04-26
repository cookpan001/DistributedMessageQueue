<?php

namespace cookpan001\Listener\Bussiness;

use \cookpan001\Listener\Logger;

class Exchange
{
    public $register = array();
    public $keys = array();
    
    public $logger = null;
    
    public function __construct()
    {
        $this->logger = new Logger;
    }
    
    public function __destruct()
    {
        $this->logger = null;
    }
    
    /**
     * 服务器间信息交换时使用
     */
    public function onExchage($server, $conn, $data)
    {
        if(empty($data)){
            return;
        }
        $this->logger->log(__FUNCTION__.': '.json_encode($data));
        foreach($data as $param){
            $cmd = array_shift($param);
            array_unshift($param, $conn);
            array_unshift($param, $server);
            if(method_exists($this, $cmd)){
                call_user_func_array(array($this, $cmd), $param);
            }
        }
        $server->reply($conn, 1);
    }
    
    public function register($server, $conn, ...$para)
    {
        $this->register[$conn->id] = $conn;
        $conn->on('close', function($id){
            unset($this->register[$id]);
        });
        $server->reply($conn, $conn->id);
    }
}