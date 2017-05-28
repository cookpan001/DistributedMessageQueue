<?php

namespace cookpan001\Listener\Bussiness;

use \cookpan001\Listener\Logger;

class Coordinator
{
    private $client;
    private $emiter;
    private $storage;
    public $logger = null;
    public $localHost = '';
    public $localPort = '';
    public $keys = array();
    public $conns = array();
    public $app = null;
    
    public function __construct($client, $storage, $emiter)
    {
        $this->logger = new Logger;
        $this->storage = $storage;
        $this->emiter = $emiter;
        $this->client = $client;
    }
    
    public function __destruct()
    {
        $this->logger = null;
    }
    
    public function setApp($app)
    {
        $this->app = $app;
    }
    
    public function getInstance($name)
    {
        return $this->app->getInstance($name);
    }
    
    /**
     * 连接到服务器时触发
     */
    public function onConnect()
    {
        $this->logger->log(__FUNCTION__);
        $this->client->push('register', $this->localHost, $this->localPort);
    }
    /**
     * Agent收到Socket的消息时触发
     */
    public function onMessage($from, $data)
    {
        $this->logger->log(__FUNCTION__.': '.json_encode($data));
        if(empty($data)){
            return;
        }
        foreach($data as $param){
            $cmd = array_shift($param);
            $command = 'agent'.ucfirst($cmd);
            if(method_exists($this, $command)){
                call_user_func(array($this, $command), $from, ...$param);
            }
        }
    }
    
    public function onLocal($host, $port)
    {
        $this->localHost = $host;
        $this->localPort = $port;
    }
    
    public function agentNotify($from, ...$keys)
    {
        foreach($keys as $key){
            $this->keys[$key][$from] = 1;
            $this->conns[$from][$key] = 1;
        }
    }
    
    /**
     * 收到本地mediator的消息, 向其他mediator广播或单向发送数据
     */
    public function onMediator($op, ...$data)
    {
        switch ($op) {
            case 'push':
                $from = array_shift($data);
                $this->client->push($from, $op, ...$data);
                break;
            case 'broadcast':
                $this->client->broadcast($op, ...$data);
                break;
            case 'exchange':
                
                break;
            case 'notify':
                
                break;
            default:
                break;
        }
    }
}