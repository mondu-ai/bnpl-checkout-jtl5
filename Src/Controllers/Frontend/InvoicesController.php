<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Helpers\InvoiceHelper;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use JTL\Checkout\Bestellung;
use Plugin\MonduPayment\Src\Models\Order;
use Plugin\MonduPayment\Src\Models\MonduOrder;
use Plugin\MonduPayment\Src\Models\MonduInvoice;
use Plugin\MonduPayment\Src\Services\ConfigService;

class InvoicesController
{
    private MonduClient $monduClient;
    private ConfigService $configService;

    public function __construct()
    {
        $this->monduClient = new MonduClient();
        $this->configService = new ConfigService();
    }

    public function create()
    {
        $requestData = $_REQUEST;

        $orderId = $requestData['order_id'];
        $invoiceId = $requestData['invoice_id'];

        $orderQuery = new Order();
        $order = $orderQuery->select('kBestellung')->where('cBestellNr', InvoiceHelper::escape((string) $orderId))->first()[0];
        $bestellung = new Bestellung($order->kBestellung, true);

        // Workaround (PT-4010): optionally skip the create-invoice call to Mondu. The
        // invoice is created in Mondu through a separate PDF-submission flow, so instead
        // of creating it we fetch its details (the Mondu invoice UUID) from the Mondu
        // order and store them locally, as required by the ticket. If the Mondu invoice
        // is not available yet, the UUID stays empty and is resolved on demand later
        // (credit-note / cancel via InvoiceHelper::resolveInvoiceUuid).
        if ($this->configService->shouldSkipInvoiceCreation()) {
            $invoiceUuid = InvoiceHelper::resolveInvoiceUuid(
                $this->monduClient,
                $bestellung->kBestellung,
                (string) $invoiceId
            ) ?? '';

            $monduInvoice = new MonduInvoice();
            $monduInvoice->create([
                'order_id' => $bestellung->kBestellung,
                'state' => 'pending',
                'external_reference_id' => $invoiceId,
                'invoice_uuid' => $invoiceUuid
            ]);

            return Response::json([
                    'error' => false
                ]
            );
        }

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

    public function cancel()
    {
        $requestData = $_REQUEST;

        $invoiceNumber = (string) ($requestData['invoice_number'] ?? '');

        $monduInvoiceQuery = new MonduInvoice();
        $monduInvoice = $monduInvoiceQuery
            ->select('id, invoice_uuid, order_id')
            ->where('external_reference_id', InvoiceHelper::escape($invoiceNumber))
            ->first()[0] ?? null;

        if (!$monduInvoice) {
            return Response::json([
                'error' => true,
                'message' => 'Invoice not found for invoice_number ' . $invoiceNumber
            ], Response::HTTP_NOT_FOUND);
        }

        $monduOrder = (new MonduOrder())
            ->select('order_uuid')
            ->where('order_id', InvoiceHelper::escape((string) $monduInvoice->order_id))
            ->first()[0] ?? null;

        if (!$monduOrder || empty($monduOrder->order_uuid)) {
            return Response::json([
                'error' => true,
                'message' => 'Mondu order not found for invoice_number ' . $invoiceNumber
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $invoiceUuid = $monduInvoice->invoice_uuid;

        // Fallback: invoice stored locally only (skip_invoice_create) has no UUID yet.
        // Resolve it from the Mondu order and persist it before cancelling.
        if (empty($invoiceUuid)) {
            $invoiceUuid = InvoiceHelper::resolveInvoiceUuid($this->monduClient, $monduInvoice->order_id, $invoiceNumber);

            if (empty($invoiceUuid)) {
                return Response::json([
                    'error' => true,
                    'message' => 'Could not resolve Mondu invoice for invoice_number ' . $invoiceNumber
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $monduInvoiceQuery->update(['invoice_uuid' => $invoiceUuid], (int) $monduInvoice->id);
        }

        $this->monduClient->cancelInvoice(['invoice_uuid' => $invoiceUuid, 'order_uuid' => $monduOrder->order_uuid]);

        return Response::json([
                'error' => false
            ]
        );
    }
}