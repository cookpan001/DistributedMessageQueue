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
    public $name = null;
    
    public function __construct($app, $name)
    {
        $this->app = $app;
        $this->logger = $this->app->logger;
        $this->storage = $this->app->storage;
        $this->emiter = $this->app->emiter;
        $this->name = strtolower($name);
    }
    
    public function __destruct()
    {
        $this->register = null;
        $this->subscriber = null;
        $this->emiter = null;
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
            $keys = $conn->keys();
            foreach($keys as $key){
                unset($this->subscriber[$key][$id]);
            }
            if($keys){
                $waitor = $this->getInstance('waitor');
                if($waitor){
                    $waitor->toMediator('unsubscribe', ...$keys);
                }
            }
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
        $this->logger->log(__CLASS__.':'.__FUNCTION__.': '.__LINE__);
        $commands = array();
        foreach($data as $param){
            if(!is_array($param)){
                $param = preg_split('#\s+#', (string)$param);
            }
            $cmd = array_shift($param);
            if(empty($param)){//无参数指令
                if(!isset($commands[$cmd])){
                    $commands[$cmd] = null;
                }
                continue;
            }
            $key = array_shift($param);
            if(empty($param)){
                $commands[$cmd][] = $key;
                continue;
            }
            foreach($param as $val){
                $commands[$cmd][$key][] = $val;
            }
        }
        foreach($commands as $cmd => $arr){
            if(method_exists($this, $cmd)){
                if(is_null($arr)){
                    $this->$cmd($conn);
                    continue;
                }else if(isset($arr[0])){
                    $this->$cmd($conn, ...$arr);
                    continue;
                }
                foreach($arr as $key => $param){
                    $this->$cmd($conn, $key, ...$param);
                }
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
        $this->logger->log(__CLASS__.':'.__FUNCTION__.': '.__LINE__);
        switch ($op) {
            case 'push':
                $key = array_shift($data);
                $this->send($key, $data, false);
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
        while(isset($this->subscriber[$key]) && count($this->subscriber[$key])){
            $connId = array_rand($this->subscriber[$key]);
            if(!isset($this->register[$connId])){
                unset($this->subscriber[$key][$connId]);
                continue;
            }
            if(is_array($value)){
                $this->register[$connId]->reply(...$value);
            }else{
                $this->register[$connId]->reply($value);
            }
            $this->storage->remove($key, $value);
            return true;
        }
        if($broadcast){
            $waitor = $this->getInstance('waitor');
            if($waitor){
                $waitor->toMediator('send', $key, $value);
            }
        }
        return false;
    }
    
    /**
     * 收到消息, 推送或定时推送
     */
    public function publish($conn, $key, ...$data)
    {
        $toSend = array();
        $toSet = array();
        $now = time();
        $count = count($data);
        $this->logger->log('++++++++++++++++++++++++++'.__CLASS__.'::'.__FUNCTION__);
        for($i = 0; $i < $count; ++$i){
            $value = $data[$i];
            $timestamp = $data[$i + 1];
            if(0 == $timestamp || ($diff = ($timestamp - $now)) <= 0){
                $toSend[$value] = $value;
            }else{
                //定时的消息
                $toSet[$value] = $diff;
            }
            ++$i;
        }
        $this->logger->log('++++++++++++++++++++++++++'.__CLASS__.'::'.__FUNCTION__);
        if($toSend){
            //立即发送的消息
            $this->send($key, $toSend);
        }
        if($toSet){
            $this->storage->setTimer($key, $toSet, $this, $diff);
        }
    }
    /**
     * 广播消息
     */
    public function broadcast($conn, $key, ...$data)
    {
        $waitor = $this->getInstance('waitor');
        if($waitor){
            $waitor->toMediator('broadcast', $key, $data);
        }
        if(isset($this->subscriber[$key])){
            foreach($this->subscriber[$key] as $connId => $__){
                if(!isset($this->register[$connId])){
                    unset($this->subscriber[$key][$connId]);
                    continue;
                }
                $this->register[$connId]->reply(...$data);
            }
        }
    }
    /**
     * 收到广播消息
     */
    public function receiveBroadcast($key, ...$data)
    {
        if(isset($this->subscriber[$key])){
            foreach($this->subscriber[$key] as $connId => $__){
                if(!isset($this->register[$connId])){
                    unset($this->subscriber[$key][$connId]);
                    continue;
                }
                $this->register[$connId]->reply(...$data);
            }
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
        $waitor = $this->getInstance('waitor');
        if($waitor){
            $waitor->toMediator('subscribe', $key);
        }
        if(method_exists($conn, 'subscribe')){
            $conn->subscribe($key);
        }else{
            
        }
        $this->logger->log(__CLASS__.':'.__FUNCTION__);
        //有没有暂存的消息
        if(!$this->storage->num($key)){
            return;
        }
        //发送暂存的消息
        $values = $this->storage->getAndRemove($key);
        if($values){
            $conn->reply(...$values);
        }
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
    
    public function info($conn)
    {
        $info = array(
            'subscribers: '. count($this->subscriber),
            'connections: '. count($this->register),
            'keys: '.json_encode(array_keys($this->subscriber)),
            'timers: '.json_encode(array_keys($this->timer)),
            'acceptor: '.$this->app->getConfig('acceptor', 'host').':'.$this->app->getConfig('acceptor', 'port'),
            'waitor: '.$this->app->getConfig('waitor', 'host').':'.$this->app->getConfig('waitor', 'port'),
            '',
        );
        $conn->reply($info);
    }
}