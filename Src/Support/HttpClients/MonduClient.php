<?php

namespace Plugin\MonduPayment\Src\Support\HttpClients;

use Plugin\MonduPayment\Src\Support\Http\HttpRequest;
use Plugin\MonduPayment\Src\Exceptions\InvalidRequestException;


class MonduClient
{
    private HttpRequest $client;

    public function __construct() 
    {
        $this->client = new HttpRequest(
            'http://localhost:3000/api/v1/',
            ['Content-Type: application/json', 'Api-Token: RN43AAI3LKN7IKQ2MJXWU53YJIEM8ZMX']
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
