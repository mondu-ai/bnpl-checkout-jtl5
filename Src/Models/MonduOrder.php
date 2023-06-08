<?php

namespace Plugin\MonduPayment\Src\Models;

use Plugin\MonduPayment\Src\Database\Initialization\Model;

class MonduOrder extends Model
{
    protected $table    = 'mondu_orders';

    protected $primaryKey  = 'id';

    protected $fillable = [
        'order_id',
        'state',
        'external_reference_id',
        'order_uuid',
        'authorized_net_term'
    ];
}
