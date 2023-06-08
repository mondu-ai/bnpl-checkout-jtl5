<?php

namespace Plugin\MonduPayment\Src\Exceptions;

use Plugin\MonduPayment\Src\Support\Debug\Debugger;

class InvalidRequestException extends \Exception
{    
    protected $_exceptionData;

    public function __construct($data)
    {
        $debugger = new Debugger();
        $debugger->log('[REQUEST FAIL]: Error ocurred at ' . $data->request_url . ' with data: ' . print_r($data->request_body, true));
        $debugger->log('[REQUEST FAIL]: Response: ' . print_r($data->response_body, true));

        $this->_exceptionData = $data;

        parent::__construct('Mondu Request Failed');
    }

    public function getExceptionData()
    {
        return $this->_exceptionData;
    }
}
