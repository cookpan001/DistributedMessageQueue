<?php

namespace cookpan001\Listener;

class Storage
{
    public $keys = array();
    public $callback = array();
    public $timer = array();
    
    public function __construct()
    {
        
    }
    
    public function set($key, $values, $that = null, $diff = 0)
    {
        foreach((array)$values as $value){
            $field = $value;
            if(!$this->has($key, $field)){
                $this->keys[$key][$field] = $value;
            }
            if($diff > 0){
                if($this->timer[$key][$value]){
                    $this->timer[$key][$value]->stop();
                }
                if($that){
                    $this->timer[$key][$value] = new \EvTimer(0, $diff, function ($w) use ($that, $key, $value){
                        $w->stop();
                        unset($this->timer[$key][$value]);
                        $that->send($key, $value);
                    });
                }
            }
        }
    }
    
    public function get($key, $field)
    {
        return isset($this->keys[$key][$field]) ? $this->keys[$key][$field] : null;
    }
    
    public function has($key, $field)
    {
        return isset($this->keys[$key][$field]) ? true : false;
    }
    
    public function num($key)
    {
        return isset($this->keys[$key]) ? count($this->keys[$key]) : 0;
    }
    
    public function remove($key, $field)
    {
        foreach((array)$field as $f){
            unset($this->keys[$key][$f]);
            if(isset($this->timer[$key][$f])){
                $this->timer[$key][$f]->stop();
                unset($this->timer[$key][$f]);
            }
        }
    }
    
    public function getAndRemove($key)
    {
        if(empty($this->keys[$key])){
            return array();
        }
        $tmp = $this->keys[$key];
        unset($this->keys[$key]);
        foreach($tmp as $value){
            if(isset($this->timer[$key][$value])){
                $this->timer[$key][$value]->stop();
                unset($this->timer[$key][$value]);
            }
        }
        return $tmp;
    }
    
    public function timestamp($key, $value)
    {
        if(!isset($this->timer[$key][$value])){
            return null;
        }
        if($this->timer[$key][$value]->remaining <= 0){
            return 0;
        }
        return time() + $this->timer[$key][$value]->remaining;
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