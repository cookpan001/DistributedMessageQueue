<?php

namespace cookpan001\Listener\Bussiness;

use \cookpan001\Listener\Logger;

class Acceptor
{
    private $emmiter;
    public $logger = null;
    public $storage = null;
    public $register = array();
    public $keys = array();
    public $subscriber = array();
    public $timer = array();
    
    public function __construct($storage, $emmiter)
    {
        $this->logger = new Logger;
        $this->storage = $storage;
        $this->emmiter = $emmiter;
    }
    
    public function __destruct()
    {
        $this->logger = null;
        $this->register = null;
        $this->keys = null;
        $this->subscriber = null;
        foreach($this->timer as $w){
            $w->stop();
        }
        $this->timer = null;
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
        });
    }
    
    /**
     * 主服务器收到从服务器时触发
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
     * 推送消息
     */
    public function send($key, $value)
    {
        if(empty($this->subscriber[$key])){
            return false;
        }
        while(count($this->subscriber[$key])){
            $connId = array_rand($this->subscriber[$key]);
            if(isset($this->register[$connId])){
                unset($this->keys[$key][$value]);
                $this->register[$connId]->reply($value);
                if(isset($this->timer[$value])){
                    $this->timer[$value]->stop();
                    unset($this->timer[$value]);
                }
                return true;
            }
        }
        return false;
    }
    /**
     * 添加定时器
     */
    public function addTimer($key, $value, $diff = 0)
    {
        if(!isset($this->keys[$key][$value])){
            $this->keys[$key][$value] = 1;
        }
        if($diff > 0){
            if($this->timer[$value]){
                $this->timer[$value]->stop();
            }
            $this->timer[$value] = new \EvTimer(0, $diff, function ($w) use ($key, $value){
                $w->stop();
                unset($this->timer[$value]);
                $this->send($key, $value);
            });
        }
    }
    /**
     * 收到消息, 推送或定时推送
     */
    public function publish($conn, $key, $value, $timestamp = 0)
    {
        if(0 == $timestamp || ($diff = ($timestamp - time())) <= 0){
            //立即发送的消息
            $this->send($key, $value);
        }else{
            //定时的消息
            $this->addTimer($key, $value, $diff);
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
        if(!isset($this->keys[$key])){
            return;
        }
        //发送暂存的消息
        $values = array();
        foreach($this->keys[$key] as $value => $__){
            $values[] = $value;
            if(isset($this->timer[$value])){
                $this->timer[$value]->stop();
                unset($this->timer[$value]);
            }
        }
        unset($this->keys[$key]);
        $conn->reply(...$values);
    }
    /**
     * 移除消息
     */
    public function remove($conn, $key, $value)
    {
        unset($this->keys[$key][$value]);
        if(isset($this->timer[$value])){
            $this->timer[$value]->stop();
            unset($this->timer[$value]);
        }
    }
    /**
     * 获取消息的发送时间
     */
    public function timestamp($conn, $key, $value)
    {
        if(!isset($this->timer[$value])){
            return 0;
        }
        if($this->timer[$value]->remaining <= 0){
            return 0;
        }
        return time() + $this->timer[$value]->remaining;
    }
}