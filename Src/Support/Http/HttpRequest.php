<?php

namespace Plugin\MonduPayment\Src\Support\Http;

use Plugin\MonduPayment\Src\Exceptions\InvalidRequestException;
use Plugin\MonduPayment\Src\Exceptions\UnsupportedAuthenticationType;
use Plugin\MonduPayment\Src\Exceptions\UnsupportedRequestType;

class HttpRequest
{
    /**
     * curl instance
     *
     * @var [curl]
     */
    private $curl;

    /**
     * headers of the request
     *
     * @var array
     */
    private array $headers;

    /**
     * baseUrl of the request
     *
     * @var array
     */
    private string $baseUrl;

    public function __construct(string $baseUrl, array $headers = ['Content-type' => 'application/json'])
    {
        $this->curl = curl_init();
        $this->headers = $headers;
        $this->baseUrl = $baseUrl;
    }

    /**
     * get Request
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return void
     */
    public function get(string $url, array $data = [], array $headers = ['Content-type' => 'application/json'])
    {
        $url = $this->baseUrl . $url;
        $this->headers = $headers;
        return $this->send_request($url, $data, 'GET');
    }

    /**
     * POST Request
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return void
     */
    public function post(string $url, array $data = [], array $headers = null)
    {
        $url = $this->baseUrl . $url;
        $this->headers = $headers == null ? $this->headers : $headers;
        return $this->send_request($url, $data, 'POST');
    }

    /**
     * PATCH Request
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return void
     */
    public function patch(string $url, array $data = [], array $headers = ['Content-type' => 'application/json'])
    {
        $url = $this->baseUrl . $url;
        $this->headers = $headers;
        return $this->send_request($url, $data, 'PATCH');
    }

    /**
     * PUT Request
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return void
     */
    public function put(string $url, array $data = [], array $headers = ['Content-type' => 'application/json'])
    {
        $url = $this->baseUrl . $url;
        $this->headers = $headers;
        return $this->send_request($url, $data, 'PUT');
    }

    /**
     * DELETE Request
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return void
     */
    public function delete(string $url, array $data = [], array $headers = ['Content-type' => 'application/json'])
    {
        $url = $this->baseUrl . $url;
        $this->headers = $headers;
        return $this->send_request($url, $data, 'DELETE');
    }

    /**
     * for sending request 
     *
     * @param array $data
     * @param boolean $token
     * @return array $response
     */
    public function send_request(string $url, array $data, string $method)
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);

        if ($method === 'POST') {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
       
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
       
        $response = curl_exec($this->curl);

        curl_close($this->curl);
        if (!$response) {
            throw new InvalidRequestException();
        }
        $response = json_decode($response, true);

        // print_r($response);
        
        if (@$response['errors'] != null) {
            throw new InvalidRequestException($response);
        }

        return $response;
    }
}
