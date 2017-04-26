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
    
    public function __construct($socket, $server = null)
    {
        $this->clientSocket = $socket;
    }
    
    public function __destruct()
    {
        $this->close();
        var_export('__destruct connection');
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
        $this->emit('close', $this->id);
        $this->callback = null;
        if($this->server){
            $this->server = null;
        }
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
}