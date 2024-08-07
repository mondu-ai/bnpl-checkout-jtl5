<?php

namespace Plugin\MonduPayment\Src\Support\Http;

class Request
{
    private static array $data = [];
    private static array $rawData = [];
    private static array $headers = [];

    /**
     * @var false|string
     */
    private static $body;

    public function __construct()
    {
        $headers = [];
        foreach($_SERVER as $key => $value) {
            if (substr($key, 0, 5) != 'HTTP_') {
                continue;
            }
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
        self::$headers = $headers;

        if (!!$_GET) {
            foreach ($_GET as $key => $item) {
                self::$data[$key] = filter_input(INPUT_GET, $key,  FILTER_SANITIZE_SPECIAL_CHARS);
                self::$rawData[$key] = $item;
            }
        }
        if (!!$_POST) {
            foreach ($_POST as $key => $item) {
                self::$data[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
                self::$rawData[$key] = $item;
            }
        }
        $data = file_get_contents('php://input');
        self::$body = $data;

        if (!!$data) {
            $data = json_decode($data, true);
            if ((is_array($data)) && (count($data) > 0)) {
                foreach ($data as $key => $item) {
                    self::$data[$key] = filter_var($item,  FILTER_SANITIZE_SPECIAL_CHARS);
                    self::$rawData[$key] = $item;
                }
            }
        }
    }

    public static function type()
    {
        if (isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
            return strtoupper($_POST['_method']);
        }
        if (isset($_POST['_method']) && $_POST['_method'] === 'PATCH') {
            return strtoupper($_POST['_method']);
        }
        if (isset($_GET['_method']) && $_GET['_method'] === 'DELETE') {
            return strtoupper($_GET['_method']);
        }
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    public static function uri()
    {
        return $_SERVER['REQUEST_URI'];
    }

    public function all()
    {
        return self::$data;
    }

    public function headers(): array
    {
        return self::$headers;
    }

    public function header($key)
    {
        return self::$headers[$key] ?? null;
    }

    public function getBody()
    {
        return self::$body;
    }

    public function allRaw()
    {
        return self::$rawData;
    }

    public function unset(...$elements)
    {
        foreach ($elements as $element) {
            unset(self::$data[$element]);
        }
    }
}
