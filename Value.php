<?php

namespace cookpan001\Listener;

class Value
{
    public $key = null;
    public $val = null;
    public $timestamp = null;
    public $timer = null;
    
    public function __construct($key, $val)
    {
        $this->key = $key;
        $this->val = $val;
    }
    
    public function setVal($timestamp)
    {
        $this->timestamp = $timestamp;
        $diff = time() - $timestamp;
        if($diff <= 0){
            return;
        }
        if($this->timer){
            $this->timer->stop();
            $this->timer = null;
        }
        $this->timer = new \EvTimer(0, $diff, function ($w) use ($key, $val){
            $w->stop();
            $this->send($key, $val);
        });
    }
    
    public function __destruct()
    {
        $this->val = null;
        $this->timestamp = null;
        if($this->timer){
            $this->timer->stop();
        }
    }
}