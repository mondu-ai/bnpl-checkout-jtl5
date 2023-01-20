<?php

namespace Plugin\MonduPayment\Src\Helpers;

class OrderHashHelper {
    public static function getOrderHash($order) {
        unset($order['external_reference_id']);
        unset($order['buyer']['is_registered']);
        return md5(json_encode($order));
    }
}
