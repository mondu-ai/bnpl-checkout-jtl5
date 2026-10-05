<?php

namespace Plugin\MonduPayment\Src\Controllers\Frontend;

use Plugin\MonduPayment\Src\Helpers\Response;
use Plugin\MonduPayment\Src\Helpers\InvoiceHelper;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;
use Plugin\MonduPayment\Src\Models\MonduInvoice;

class CreditNotesController
{
    private MonduClient $monduClient;

    public function __construct()
    {
        $this->monduClient = new MonduClient();
    }

    /**
     * Create a credit note for an existing invoice.
     *
     * JTL Wawi sends (application/x-www-form-urlencoded):
     *   gross_amount_cents  = {{ Vorgang.Gesamtbetrag }}  (gross amount in shop currency)
     *   invoice_id          = {{ Vorgang.Rechnung.Rechnungsnummer }}
     *   external_reference_id = {{ Vorgang.Rechnungskorrekturnummer }}
     *
     * The invoice is looked up by invoice_id (stored as external_reference_id on the
     * mondu_invoices row) and the credit note is submitted to Mondu with the amount
     * (converted to cents) and the credit note reference (external_reference_id).
     */
    public function create()
    {
        $requestData = $_REQUEST;

        $grossAmount = $requestData['gross_amount_cents'] ?? null;
        $invoiceId = $requestData['invoice_id'] ?? null;
        $externalReferenceId = $requestData['external_reference_id'] ?? null;

        if ($grossAmount === null || $grossAmount === '' || $invoiceId === null || $invoiceId === '') {
            return Response::json([
                'error' => true,
                'message' => 'Missing required parameters: gross_amount_cents and invoice_id'
            ], Response::HTTP_BAD_REQUEST);
        }

        $grossAmountCents = InvoiceHelper::parseAmountToCents($grossAmount);

        if ($grossAmountCents === null || $grossAmountCents <= 0) {
            return Response::json([
                'error' => true,
                'message' => 'Invalid gross amount: ' . $grossAmount
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $invoiceId = (string) $invoiceId;

        $monduInvoiceQuery = new MonduInvoice();
        $monduInvoice = $monduInvoiceQuery
            ->select('id, invoice_uuid, order_id')
            ->where('external_reference_id', InvoiceHelper::escape($invoiceId))
            ->first()[0] ?? null;

        if (!$monduInvoice) {
            return Response::json([
                'error' => true,
                'message' => 'Invoice not found for invoice_id ' . $invoiceId
            ], Response::HTTP_NOT_FOUND);
        }

        $invoiceUuid = $monduInvoice->invoice_uuid;

        // Fallback: when the invoice was stored locally only (skip_invoice_create), the
        // Mondu invoice UUID is not known yet. Resolve it from the Mondu order and
        // persist it for subsequent calls.
        if (empty($invoiceUuid)) {
            $invoiceUuid = InvoiceHelper::resolveInvoiceUuid($this->monduClient, $monduInvoice->order_id, $invoiceId);

            if (empty($invoiceUuid)) {
                return Response::json([
                    'error' => true,
                    'message' => 'Could not resolve Mondu invoice for invoice_id ' . $invoiceId
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $monduInvoiceQuery->update(['invoice_uuid' => $invoiceUuid], (int) $monduInvoice->id);
        }

        $creditNoteData = [
            'gross_amount_cents' => $grossAmountCents,
        ];

        if ($externalReferenceId !== null && $externalReferenceId !== '') {
            $creditNoteData['external_reference_id'] = (string) $externalReferenceId;
        }

        $result = $this->monduClient->createCreditNote($invoiceUuid, $creditNoteData);

        if (!$result || (isset($result['error']) && $result['error'])) {
            return Response::json([
                'error' => true,
                'message' => 'Failed to create credit note'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return Response::json([
            'error' => false
        ]);
    }
}
