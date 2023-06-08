<?php

namespace Plugin\MonduPayment\Src\Support\Facades\Filesystem;

class Directory
{
    public string $pluginRoot;

    private const LEVEL_TO_BACK = 5;

    public function __construct()
    {
        $this->pluginRoot = dirname(__FILE__, self::LEVEL_TO_BACK);
    }

    public static function get_root(): string
    {
        return $_SERVER['DOCUMENT_ROOT'];
    }

    public static function get_resources(): string
    {
        return self::get_root()  . '/plugins/MonduPayment/Src/Resources';
    }

    public static function get_logs(): string
    {
        return self::get_root()  . '/plugins/MonduPayment/Logs/';
    }
}
