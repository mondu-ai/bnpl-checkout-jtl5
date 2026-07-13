<?php

namespace Plugin\MonduPayment\Src\Helpers;

use JTL\Shop;
use Plugin\MonduPayment\Src\Models\MonduOrder;
use Plugin\MonduPayment\Src\Support\HttpClients\MonduClient;

class InvoiceHelper
{
    /**
     * Escape a value for safe interpolation into the string-based Model::where().
     * The base Model builds `WHERE col='$value'` without bindings, so any value
     * derived from a request must be escaped first.
     */
    public static function escape(string $value): string
    {
        return Shop::Container()->getDB()->escape($value);
    }

    /**
     * Convert an amount coming from a JTL Wawi workflow placeholder (e.g.
     * {{ Vorgang.Gesamtbetrag }}, a gross amount in the shop currency) into
     * integer cents. Handles both German ("1.234,56") and English ("1,234.56")
     * grouping/decimal separators.
     *
     * @return int|null cents, or null when the input is not a parseable amount
     */
    public static function parseAmountToCents($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);
        // strip currency symbols / whitespace, keep digits, separators and sign
        $s = preg_replace('/[^0-9.,-]/', '', $s);

        if ($s === null || $s === '' || !preg_match('/\d/', $s)) {
            return null;
        }

        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                // German format: '.' groups thousands, ',' is the decimal separator
                $s = str_replace(['.', ','], ['', '.'], $s);
            } else {
                // English format: ',' groups thousands, '.' is the decimal separator
                $s = str_replace(',', '', $s);
            }
        } elseif ($lastComma !== false) {
            // only a comma present -> treat it as the decimal separator
            $s = str_replace(',', '.', $s);
        }
        // only a dot (or no separator) -> already a valid float string

        if (!is_numeric($s)) {
            return null;
        }

        return (int) round(((float) $s) * 100);
    }

    /**
     * Resolve a Mondu invoice UUID for a locally stored invoice whose UUID is not
     * known yet (skip_invoice_create mode). Looks up the Mondu order and matches the
     * invoice by its number / external reference id.
     */
    public static function resolveInvoiceUuid(MonduClient $client, $orderId, string $invoiceId): ?string
    {
        $monduOrder = (new MonduOrder())
            ->select('order_uuid')
            ->where('order_id', self::escape((string) $orderId))
            ->first()[0] ?? null;

        if (!$monduOrder || empty($monduOrder->order_uuid)) {
            return null;
        }

        $response = $client->getInvoices($monduOrder->order_uuid);

        foreach ($response['invoices'] ?? [] as $invoice) {
            $number = isset($invoice['invoice_number']) ? (string) $invoice['invoice_number'] : null;
            $extRef = isset($invoice['external_reference_id']) ? (string) $invoice['external_reference_id'] : null;

            if ($number === $invoiceId || $extRef === $invoiceId) {
                return $invoice['uuid'] ?? null;
            }
        }

        return null;
    }
}
