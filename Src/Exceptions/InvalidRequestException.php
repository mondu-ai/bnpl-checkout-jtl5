<?php

namespace Plugin\MonduPayment\Src\Exceptions;

use Plugin\MonduPayment\Src\Support\Debug\Debugger;

class InvalidRequestException extends \Exception
{    
    public function __construct($message = 'this request is invalid')
    {
        $debugger = new Debugger();
        $debugger->log($this->message);
    }
}
