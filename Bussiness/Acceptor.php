<?php

namespace cookpan001\Listener\Bussiness;

class Acceptor
{
    private $emiter;
    public $logger = null;
    public $storage = null;
    public $register = array();
    public $subscriber = array();
    public $timer = array();
    public $app = null;
    
    public function __construct($app)
    {
        $this->app = $app;
        $this->logger = $this->app->logger;
        $this->storage = $this->app->storage;
        $this->emiter = $this->app->emiter;
    }
    
    public function __destruct()
    {
        $this->register = null;
        $this->subscriber = null;
        $this->emiter = null;
    }
    
    public function setApp($app)
    {
        
    }
    
    public function getInstance($name)
    {
        return $this->app->getInstance($name);
    }
    
    public function onConnect($conn)
    {
        if(!isset($this->register[$conn->id])){
            $this->register[$conn->id] = $conn;
        }
        $conn->on('close', function($id) use ($conn){
            unset($this->register[$id]);
            foreach($conn->keys as $key){
                unset($this->subscriber[$key][$id]);
            }
            $this->emiter->emit('notify', 'unsubscribe', ...$conn->keys);
        });
    }
    
    /**
     * 主服务器收到从socket来的消息时触发
     */
    public function onMessage($conn, $data)
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
    /**
     * 内部信息交换处理由waitor发来的消息
     */
    public function onWaitor($op, ...$data)
    {
        if(empty($data)){
            return;
        }
        $this->logger->log(__FUNCTION__.': '.json_encode($data));
        switch ($op) {
            case 'push':
                $key = array_shift($data);
                foreach($data as $value){
                    $this->send($key, $value, false);
                }
                break;
            default:
                break;
        }
    }
    /**
     * 推送消息
     */
    public function send($key, $value, $broadcast = true)
    {
        if(empty($this->subscriber[$key])){
            return false;
        }
        while(count($this->subscriber[$key])){
            $connId = array_rand($this->subscriber[$key]);
            if(!isset($this->register[$connId])){
                unset($this->subscriber[$key][$connId]);
                continue;
            }
            $this->storage->remove($key, $value);
            $this->register[$connId]->reply($value);
            return true;
        }
        if($broadcast){
            $this->emiter->emit('acceptor', 'send', $key, $value);
        }
        return false;
    }
    
    /**
     * 收到消息, 推送或定时推送
     */
    public function publish($conn, $key, $value, $timestamp = 0)
    {
        if(0 == $timestamp || ($diff = ($timestamp - time())) <= 0){
            if($this->storage->has($key, $value)){
                $this->storage->remove($key, $value);
            }
            //立即发送的消息
            $this->send($key, $value);
        }else{
            //定时的消息
            $this->storage->set($key, $value, $value, $this, $diff);
        }
    }
    /**
     * 收到订阅消息
     */
    public function subscribe($conn, $key)
    {
        //登记订阅信息
        if(!isset($this->subscriber[$key][$conn->id])){
            $this->subscriber[$key][$conn->id] = 1;
        }
        //有没有暂存的消息
        if(!$this->storage->num($key)){
            return;
        }
        //发送暂存的消息
        $values = $this->storage->getAndRemove($key);
        $conn->reply(...$values);
        $this->emiter->emit('acceptor', 'subscribe', ...$values);
    }
    /**
     * 移除消息
     */
    public function remove($conn, $key, $value)
    {
        $this->storage->remove($key, $value);
    }
    /**
     * 获取消息的发送时间
     */
    public function timestamp($conn, $key, $value)
    {
        return $this->storage->timestamp($key, $value);
    }
}