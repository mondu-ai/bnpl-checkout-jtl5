<?php

namespace Plugin\MonduPayment\Src\Services\OrderServices;

abstract class AbstractOrderAdditionalCostsService
{
    /**
     * Additional costs associated with order in cents from basket (in admin panel)
     *
     * @param mixed $basket
     * @return int
     */
    abstract public function getAdditionalCostsCentsFromOrder(mixed $basket): int;
}
