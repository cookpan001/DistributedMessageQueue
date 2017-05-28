<?php

namespace cookpan001\Listener\Bussiness;

class Waitor
{
    private $client;
    private $emiter;
    private $storage;
    public $logger = null;
    public $app = null;
    
    public function __construct($app, $client)
    {
        $this->app = $app;
        $this->logger = $this->app->logger;
        $this->storage = $this->app->storage;
        $this->emiter = $this->app->emiter;
        $this->client = $client;
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
    public function onConnect()
    {
        $this->logger->log(__FUNCTION__);
        $this->client->push('register', 'client', 1);
    }
    /**
     * 收到服务器的消息时触发
     */
    public function onMessage($data)
    {
        $this->logger->log(__FUNCTION__.': '.json_encode($data));
        if(empty($data)){
            return;
        }
        foreach($data as $param){
            $cmd = array_shift($param);
            $command = 'on'.ucfirst($cmd);
            if(method_exists($this, $command)){
                call_user_func(array($this, $command), ...$param);
            }else{
                $this->emiter->emit($cmd, ...$param);
            }
        }
    }
    /**
     * 处理由Acceptor发来的消息
     */
    public function onAcceptor($op, ...$data)
    {
        $this->client->push($op, ...$data);
    }
    /**
     * 收到mediator的消息, 由Socket传输, 自己处理或交由Acceptor处理
     */
    public function onMediator($op, ...$data)
    {
        switch ($op) {
            case 'push':
                $this->emiter->emit('waitor', $op, ...$data);
                break;
            case 'ack':
                list($key, $value) = $data;
                $this->storage->remove($key, $value);
                break;
            default:
                break;
        }
    }
}