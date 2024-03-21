<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use JTL\Checkout\Bestellung;
use Plugin\MonduPayment\Src\Models\Order;
use Plugin\MonduPayment\Src\Models\MonduOrder;
use Plugin\MonduPayment\Src\Models\MonduInvoice;

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
            if ($lineItem->kArtikel == 0) {
                continue;
            }
            
            $invoiceLineItems[] = [
                'external_reference_id' => (string) $lineItem->kArtikel,
                'quantity' => $lineItem->nAnzahl,
            ];
        }

        $invoiceData = [
            'currency' => $bestellung->Waehrung->code,
            'order_uuid' => $monduOrder->order_uuid,
            'external_reference_id' => (string) $invoiceId,
            'invoice_url' => 'http://localhost',
            'gross_amount_cents' => round(round(floatval($bestellung->fGesamtsummeKundenwaehrung), 2) * 100),
            'line_items' => $invoiceLineItems
        ];

        $invoice = $this->monduClient->createInvoice($invoiceData);

        $monduInvoice = new MonduInvoice();
        $monduInvoice->create([
            'order_id' => $bestellung->kBestellung,
            'state' => 'created',
            'external_reference_id' => $invoiceId,
            'invoice_uuid' => $invoice['invoice']['uuid']
        ]);

        return Response::json([
                'error' => false
            ]
        );
    }

    public function cancel()
    {        
        $requestData = $_REQUEST;

        $invoiceNumber = $requestData['invoice_number'];
        
        $monduInvoice = new MonduInvoice();
        $monduInvoice = $monduInvoice->select('invoice_uuid, order_id')->where('external_reference_id', $invoiceNumber)->first()[0];

        $monduOrder = new MonduOrder();
        $monduOrder = $monduOrder->select('order_uuid')->where('order_id', $monduInvoice->order_id)->first()[0];

        $this->monduClient->cancelInvoice(['invoice_uuid' => $monduInvoice->invoice_uuid, 'order_uuid' => $monduOrder->order_uuid]);

        return Response::json([
                'error' => false
            ]
        );
    }
}