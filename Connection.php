<?php

namespace cookpan001\Loop;

class Connection
{
    public $watcher;
    public $clientSocket;
    public $data = array();
    public $userId = 0;
    public $id = null;
    
    public $keys = array();
    
    public function __construct($socket)
    {
        $this->clientSocket = $socket;
    }
    
    public function __destruct()
    {
        $this->_close();
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
    }
    
    public function getSocket()
    {
        return $this->clientSocket;
    }
}