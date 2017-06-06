<?php

namespace cookpan001\Listener\Bussiness;

class Exchanger
{
    public $keys = array();
    public $connections = array();
    public $register = array();
    
    public $logger = null;
    public $app = null;
    public $name = null;
    
    public function __construct($app, $name)
    {
        $this->app = $app;
        $this->logger = $app->logger;
        $this->name = strtolower($name);
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
        $this->logger->log(__CLASS__.':'.__FUNCTION__.': '.__LINE__);
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
        $this->logger->log(__CLASS__.':'.__FUNCTION__);
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
    public function exRegister($conn, $host, $port, $keys, $values)
    {
        $this->register[$conn->id] = $host .':'. $port;
        $mediator = $this->getInstance('mediator');
        if($mediator->keys){
            $conn->reply('notify', array_keys($mediator->keys), array_map('array_sum', $mediator->keys));
        }
        foreach($keys as $i => $key){
            $this->keys[$key][$conn->id] = $values[$i];
            $conn->subscribe($key);
        }
    }
    
    public function exNotify($conn, $keys, $values)
    {
        foreach($keys as $i => $key){
            if($values[$i] <= 0){
                unset($this->keys[$key][$conn->id]);
                $conn->unsubscribe($key);
            }else{
                $this->keys[$key][$conn->id] = $values[$i];
                $conn->subscribe($key);
            }
        }
    }
    /**
     * 查询服务状态
     */
    public function exInfo($conn)
    {
        $coordinator = $this->app->getInstance('coordinator');
        $info = array(
            'connections: '.count($this->connections),
            'keys: '.json_encode(array_keys($this->keys)),
            'keys_detail: '.json_encode(array_map('array_sum', $this->keys)),
            'registered: '.json_encode(array_values($this->register)),
            'mediator:' . $this->app->getConfig('mediator', 'host').':'.$this->app->getConfig('mediator', 'port'),
            'exchanger:' . $this->app->getConfig('exchanger', 'host').':'.$this->app->getConfig('exchanger', 'port'),
            'coordinator_keys: '. json_encode($coordinator->keys),
            '',
        );
        $conn->reply($info);
    }
    /**
     * 从Mediator收到的消息, 需要转发到一个监听的Coordinator
     */
    public function push($key, $value)
    {
        if(!isset($this->keys[$key])){
            return false;
        }
        foreach($this->keys[$key] as $connId => $num){
            if($num <= 0){
                continue;
            }
            $conn = $this->connections[$connId];
            $conn->reply('push', $key, $value);
            return true;
        }
        return false;
    }
}