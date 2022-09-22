<?php

namespace Plugin\MonduPayment\Src\Support\HttpClients;

use Plugin\MonduPayment\Src\Support\Http\HttpRequest;
use Plugin\MonduPayment\Src\Exceptions\InvalidRequestException;
use Plugin\MonduPayment\Src\Services\ConfigService;

class MonduClient
{
    private HttpRequest $client;
    private ConfigService $config;

    public function __construct() 
    {
        $this->config = new ConfigService();
        $this->client = new HttpRequest(
            $this->config->getApiUrl(),
            ['Content-Type: application/json', 'Api-Token: '. $this->config->getApiSecret()]
        );
    }

    public function createOrder(array $data = []): ?array
    {
        try {
            $order = $this->client->post('orders', $data);
            return $order;
        }
        catch (InvalidRequestException $e) {
            return ['error' => true];
        }
    }

    public function cancelOrder(array $data = []): ?array
    {
        try {
            $order = $this->client->post('orders/' . $data['order_uuid'] . '/cancel');

            return $order;
        }
        catch (InvalidRequestException $e) {
            return ['error' => true];
        }
    }

    public function cancelInvoice(array $data = []): ?array
    {
        try {
            $order = $this->client->post('orders/' . $data['order_uuid'] . '/invoices/' . $data['invoice_uuid'] . '/cancel');

            return $order;
        }
        catch (InvalidRequestException $e) {
            return ['error' => true];
        }
    }

    public function createInvoice(array $data = []): ?array
    {
        try {
            $invoice = $this->client->post('orders/' . $data['order_uuid'] . '/invoices', $data);

            return $invoice;
        }
        catch (InvalidRequestException $e) {
            return ['error' => true];
        }
    }

    public function updateExternalInfo(array $data = []): ?array
    {
        try {
            $order = $this->client->post('orders/' . $data['uuid'] . '/update_external_info', $data);

            return $order;
        }
        catch (InvalidRequestException $e) {
            return ['error' => true];
        }
    }
}
