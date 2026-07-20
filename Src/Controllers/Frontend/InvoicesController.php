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

        $orderId = (string) ($requestData['order_id'] ?? '');
        $invoiceId = $requestData['invoice_id'] ?? null;

        $bestellung = $this->resolveBestellung($orderId);

        if (!$bestellung) {
            return Response::json([
                'error' => true,
                'message' => 'Order not found for order_id ' . $orderId
            ], Response::HTTP_NOT_FOUND);
        }

        $monduOrder = new MonduOrder();
        $monduOrder = $monduOrder->select('order_uuid')->where('external_reference_id', $bestellung->cBestellNr)->first()[0] ?? null;

        if (!$monduOrder || empty($monduOrder->order_uuid)) {
            return Response::json([
                'error' => true,
                'message' => 'Mondu order not found for order_id ' . $orderId
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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
            'currency' => method_exists($bestellung->Waehrung, 'getCode')
                ? $bestellung->Waehrung->getCode()
                : $bestellung->Waehrung->code,
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

    /**
     * Resolve a JTL order from the order_id sent by the Wawi workflow.
     *
     * The value depends on which workflow placeholder the merchant configured
     * ({{ Vorgang.Auftrag.ExterneAuftragsnummer }} / {{ Vorgang.Stammdaten.ExterneAuftragsnummer }}
     * / a numeric id), so it may be the shop order number (cBestellNr, e.g. "JTL5-10002")
     * or the internal numeric order id (kBestellung). Try the candidates in order of
     * reliability instead of assuming a single format.
     */
    private function resolveBestellung(string $orderId): ?Bestellung
    {
        $orderId = trim($orderId);

        if ($orderId === '') {
            return null;
        }

        // 1) Shop order number — what ExterneAuftragsnummer normally holds.
        $order = (new Order())->select('kBestellung')->where('cBestellNr', $orderId)->first()[0] ?? null;

        // 2) Internal numeric order id (kBestellung).
        if (!$order && ctype_digit($orderId)) {
            $order = (new Order())->select('kBestellung')->where('kBestellung', $orderId)->first()[0] ?? null;
        }

        // 3) Via a stored Mondu order reference -> its shop order id.
        if (!$order) {
            $monduOrder = (new MonduOrder())->select('order_id')->where('external_reference_id', $orderId)->first()[0] ?? null;
            if ($monduOrder && !empty($monduOrder->order_id)) {
                $order = (new Order())->select('kBestellung')->where('kBestellung', $monduOrder->order_id)->first()[0] ?? null;
            }
        }

        if (!$order || empty($order->kBestellung)) {
            return null;
        }

        return new Bestellung((int) $order->kBestellung, true);
    }

    public function cancel()
    {        
        $requestData = $_REQUEST;

        $invoiceNumber = $requestData['invoice_number'];
        
        $monduInvoice = new MonduInvoice();
        $monduInvoice = $monduInvoice->select('invoice_uuid, order_id')->where('external_reference_id', $invoiceNumber)->first()[0] ?? null;

        if (!$monduInvoice) {
            return Response::json([
                'error' => true,
                'message' => 'Invoice not found for invoice_number ' . $invoiceNumber
            ], Response::HTTP_NOT_FOUND);
        }

        $monduOrder = new MonduOrder();
        $monduOrder = $monduOrder->select('order_uuid')->where('order_id', $monduInvoice->order_id)->first()[0] ?? null;

        if (!$monduOrder || empty($monduOrder->order_uuid)) {
            return Response::json([
                'error' => true,
                'message' => 'Mondu order not found for invoice_number ' . $invoiceNumber
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->monduClient->cancelInvoice(['invoice_uuid' => $monduInvoice->invoice_uuid, 'order_uuid' => $monduOrder->order_uuid]);

        return Response::json([
                'error' => false
            ]
        );
    }
}