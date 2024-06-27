<?php

namespace Plugin\MonduPayment\Src\Services\OrderServices;

class OrderAdditionalCostsService extends AbstractOrderAdditionalCostsService {

    /**
     * @inheritDoc
     */
    public function getAdditionalCostsCentsFromOrder(mixed $basket): int
    {
        return round($basket->surcharge[0] * 100);
    }
}
