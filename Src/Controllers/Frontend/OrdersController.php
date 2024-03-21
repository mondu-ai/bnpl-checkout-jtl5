<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use Plugin\MonduPayment\Src\Models\MonduOrder;

class OrdersController
{
    private MonduClient $monduClient;
    
    public function __construct()
    {
        $this->monduClient = new MonduClient();
    }

    /**
     * @return null
     */
    public function cancel()
    {        
        $requestData = $_REQUEST;

        $orderNumber = $requestData['order_number'];
        
        $monduOrder = new MonduOrder();
        $monduOrder = $monduOrder->select('order_uuid')->where('external_reference_id', $orderNumber)->first()[0];

        $this->monduClient->cancelOrder(['order_uuid' => $monduOrder->order_uuid]);

        return Response::json(
            [
                'error' => false
            ]
        );
    }
}