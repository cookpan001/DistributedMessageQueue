<?php

namespace cookpan001\Listener\Reply;

class Bulk
{
    public $str = '';
    
    public function __construct($str)
    {
        $this->str = $str;
    }
}