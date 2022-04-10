<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use Plugin\MonduPayment\Src\Support\Http\Request;
use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use JTL\Shop;
use JTL\Cart\CartHelper;
use JTL\Session\Frontend;
use Plugin\MonduPayment\Src\Support\Debug\Debugger;
use JTL\Checkout\Bestellung;
use Plugin\MonduPayment\Src\Models\Order;
use Plugin\MonduPayment\Src\Models\MonduOrder;


class InvoicesController
{
    private MonduClient $monduClient;
    
    public function __construct()
    {
        $this->monduClient = new MonduClient();
    }

    public function create()
    {
        $requestData = $_REQUEST;

        $orderId = $requestData['order_id'];
        $invoiceId = $requestData['invoice_id'];

        $orderQuery = new Order();
        $order = $orderQuery->select('kBestellung')->where('cBestellNr', $orderId)->first()[0];
        $bestellung = new Bestellung($order->kBestellung, true);

        $monduOrder = new MonduOrder();
        $monduOrder = $monduOrder->select('order_uuid')->where('external_reference_id', $bestellung->cBestellNr)->first()[0];

        $invoiceLineItems = [];

        foreach ($bestellung->Positionen as $lineItem) {
            if ($lineItem->kArtikel == 0)
            {
                continue;
            }
            
            $invoiceLineItems[] = [
                'external_reference_id' => strval($lineItem->kArtikel),
                'quantity' => $lineItem->nAnzahl,
            ];
        }

        $invoiceData = [
            'order_uuid' => $monduOrder->order_uuid,
            'external_reference_id' => $invoiceId,
            'invoice_url' => 'http://localhost',
            'gross_amount_cents' => round($bestellung->fGesamtsummeKundenwaehrung, 2) * 100,
            'line_items' => $invoiceLineItems
        ];

        $invoice = $this->monduClient->createInvoice($invoiceData);

        return Response::json(
            [
                'error' => false,
                'token' => ''
            ]
        );
    }
}