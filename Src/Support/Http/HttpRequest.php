<?php

namespace Plugin\MonduPayment\Src\Support\Http;

use Plugin\MonduPayment\Src\Exceptions\InvalidRequestException;

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
     * @var string
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
     * @param array|null $headers
     *
     * @return array
     * @throws InvalidRequestException
     */
    public function get(string $url, array $data = [], array $headers = null)
    {
        $url = $this->baseUrl . $url;
        $this->headers = $headers == null ? $this->headers : $headers;
        return $this->send_request($url, $data, 'GET');
    }

    /**
     * POST Request
     *
     * @param string $url
     * @param array $data
     * @param array|null $headers
     *
     * @return array
     * @throws InvalidRequestException
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
     *
     * @return array
     * @throws InvalidRequestException
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
     *
     * @return array
     * @throws InvalidRequestException
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
     *
     * @return array
     * @throws InvalidRequestException
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
     * @param string $url
     * @param array $data
     * @param string $method
     *
     * @return array $response
     * @throws InvalidRequestException
     */
    public function send_request(string $url, array $data, string $method)
    {
        $this->curl = curl_init();
        
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->headers);

        if ($method === 'POST') {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
       
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 0); 
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($this->curl);
        $info = curl_getinfo($this->curl);

        curl_close($this->curl);

        if (!$response || !($info['http_code'] >= 200 && $info['http_code'] <= 299)) {
            $exception = new \stdClass();
            $exception->method = $method;
            $exception->request_url = $url;
            $exception->request_body = $data;
            $exception->response_body = $response;
            $exception->response_code = $info['http_code'];

            throw new InvalidRequestException($exception);
        }

        return json_decode($response, true);
    }
}
