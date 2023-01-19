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
            [
                'Content-Type: application/json',
                'Api-Token: '. $this->config->getApiSecret(),
                'x-plugin-name: '. $this->config->getPluginName(),
                'x-plugin-version: '.$this->config->getPluginVersion()
            ]
        );
    }

    public function logEvent($object)
    {
        try {
            $data = $object->getExceptionData();

            $this->client->post('plugin/events', [
                'plugin' => $this->config->getPluginName(),
                'version' => $this->config->getPluginVersion(),
                'response_status' => strval($data->response_code),
                'response_body' => json_decode($data->response_body) ?: null,
                'request_body' => $data->request_body ?: null,
                'origin_event' => $data->request_url
            ]);
        }
        catch (\Exception $e) { }
    }

    public function createOrder(array $data = []): ?array
    {
        try {
            $order = $this->client->post('orders', $data);
            return $order;
        }
        catch (InvalidRequestException $e) {
            $this->logEvent($e);
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
            $this->logEvent($e);
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
            $this->logEvent($e);
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
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function getPaymentMethods(): ?array
    {
        try {
            $paymentMethods = $this->client->get('payment_methods', []);

            return $paymentMethods;
        }
        catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function getNetTerms(): ?array
    {
        try {
            $paymentTerms = $this->client->get('payment_terms', []);

            return $paymentTerms;
        }
        catch (InvalidRequestException $e) {
            $this->logEvent($e);
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
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function confirmOrder(array $data = []): ?array
    {
        try {
            $order = $this->client->post('orders/' . $data['uuid'] . '/confirm', $data);

            return $order;
        }
        catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }
}
