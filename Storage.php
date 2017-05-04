<?php

class Storage
{
    public $keys = array();
    public $callback = array();
    public $timer = array();
    
    public function __construct()
    {
        
    }
    
    public function set($key, $field, $value)
    {
        $this->keys[$key][$field] = $value;
    }
    
    public function get($key, $field)
    {
        return isset($this->keys[$key][$field]) ? $this->keys[$key][$field] : null;
    }
    
    public function remove($key, $field)
    {
        unset($this->keys[$key][$field]);
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