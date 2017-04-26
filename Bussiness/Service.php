<?php

namespace cookpan001\Listener\Bussiness;

use \cookpan001\Listener\Logger;

class Service
{
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
     * 主服务器收到从服务器时触发
     */
    public function onMessage($server, $conn, $data)
    {
        if(empty($data)){
            return;
        }
        $this->logger->log(__FUNCTION__.': '.json_encode($data));
        $server->reply($conn, 1);
    }
}