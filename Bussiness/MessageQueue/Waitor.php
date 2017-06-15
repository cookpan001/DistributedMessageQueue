<?php

namespace cookpan001\Listener\Bussiness\MessageQueue;

class Waitor
{
    private $client;
    private $emiter;
    private $storage;
    public $logger = null;
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
    public function onConnect()
    {
        $this->logger->log(__CLASS__.':'.__FUNCTION__);
    }
    /**
     * 收到服务器的消息时触发
     */
    public function onMessage($data)
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
    public function toMediator($op, ...$data)
    {
        $this->logger->log(__CLASS__.':'.__FUNCTION__. ','.$op.','. __LINE__ . ', '. json_encode($data));
        $this->client->push($op, ...$data);
    }
    /**
     * 收到mediator的消息, 由Socket传输, 自己处理或交由Acceptor处理
     */
    public function onMediator($op, ...$data)
    {
        switch ($op) {
            case 'push':
                $acceptor = $this->getInstance('acceptor');
                if($acceptor){
                    $acceptor->send(...$data);
                }
                break;
            case 'ack':
                $this->storage->remove(...$data);
                break;
            case 'broadcast':
                $acceptor = $this->getInstance('acceptor');
                if($acceptor){
                    $acceptor->receiveBroadcast(...$data);
                }
                break;
            default:
                break;
        }
    }
}