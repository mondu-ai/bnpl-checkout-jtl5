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
            return $this->client->post('orders', $data);
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function cancelOrder(array $data = []): ?array
    {
        try {
            return $this->client->post( 'orders/' . $data['order_uuid'] . '/cancel');
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function cancelInvoice(array $data = []): ?array
    {
        try {
            return $this->client->post( 'orders/' . $data['order_uuid'] . '/invoices/' . $data['invoice_uuid'] . '/cancel');
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function createInvoice(array $data = []): ?array
    {
        try {
            return $this->client->post( 'orders/' . $data['order_uuid'] . '/invoices', $data);
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function getPaymentMethods(): ?array
    {
        try {
            return $this->client->get('payment_methods');
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function getOrder($uuid): ?array
    {
        try {
            return $this->client->get( 'orders/' . $uuid);
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function getNetTerms(): ?array
    {
        try {
            return $this->client->get('payment_terms');
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function updateExternalInfo(array $data = []): ?array
    {
        try {
            return $this->client->post( 'orders/' . $data['uuid'] . '/update_external_info', $data);
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function confirmOrder(array $data = []): ?array
    {
        try {
            return $this->client->post( 'orders/' . $data['uuid'] . '/confirm', $data);
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function registerWebhooks(array $data = []): ?array
    {
        try {
            return $this->client->post('webhooks', $data);
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

    public function getWebhookKeys(): ?array
    {
        try {
            return $this->client->get('webhooks/keys');
        } catch (InvalidRequestException $e) {
            $this->logEvent($e);
            return ['error' => true];
        }
    }

}
