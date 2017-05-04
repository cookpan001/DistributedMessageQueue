<?php

namespace cookpan001\Listener;

class Connection
{
    public $watcher;
    public $clientSocket;
    public $data = array();
    public $userId = 0;
    public $id = null;
    
    public $keys = array();
    public $callback = array();
    public $server = null;
    
    public function __construct($socket, $server)
    {
        $this->clientSocket = $socket;
        $this->server = $server;
    }
    
    public function __destruct()
    {
        $this->close();
        var_export("__destruct connection\n");
    }
    
    public function __call($name, $arguments)
    {
        var_export('unknown method: '.$name . ', args: '. json_encode($arguments)."\n");
    }
    
    public function setWatcher($watcher)
    {
        $this->watcher = $watcher;
    }
    
    public function setId($id)
    {
        $this->id = $id;
    }
    
    public function close()
    {
        if($this->watcher){
            $this->watcher->stop();
        }
        $this->watcher = null;
        if($this->clientSocket){
            socket_close($this->clientSocket);
        }
        $this->clientSocket = null;
        $this->emit('close', $this->id);//close函数还要用到下面的变量
        $this->callback = null;
        if($this->server){
            $this->server = null;
        }
        $this->keys = null;
    }
    
    public function getSocket()
    {
        return $this->clientSocket;
    }
    
    public function on($condition, callable $func)
    {
        $this->callback[$condition][] = $func;
        return $this;
    }
    
    public function emit($condition, ...$param)
    {
        if(!isset($this->callback[$condition])){
            return false;
        }
        foreach($this->callback[$condition] as $callback){
            call_user_func_array($callback, $param);
        }
        return true;
    }
    
    public function reply(...$param)
    {
        if($this->server){
            $this->server->reply($this, ...$param);
        }
    }
    
    public function subscribe($key)
    {
        $this->keys[$key] = $key;
    }
}