<?php

namespace cookpan001\Listener\Bussiness;

use \cookpan001\Listener\Logger;

class Waitor
{
    private $emmiter;
    private $storage;
    public $logger = null;
    
    public function __construct($storage, $emmiter)
    {
        $this->logger = new Logger;
        $this->storage = $storage;
        $this->emmiter = $emmiter;
    }
    
    public function __destruct()
    {
        $this->logger = null;
    }
    
    /**
     * 连接到服务器时触发
     */
    public function onConnect($client)
    {
        $this->logger->log(__FUNCTION__);
        $client->push('register', 'client', 1);
    }
    /**
     * 收到服务器的消息时触发
     */
    public function onReceive($client, $data)
    {
        $this->logger->log(__FUNCTION__.': '.json_encode($data));
        if(empty($data)){
            return;
        }
    }
    
    public function register($client, ...$param)
    {
        $client->push('register', ...$param);
    }
}