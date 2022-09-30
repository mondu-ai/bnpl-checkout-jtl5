<?php

namespace Plugin\MonduPayment\Src\Models;

use Plugin\MonduPayment\Src\Database\Initialization\Model;

class MonduInvoice extends Model
{
    protected $table    = 'mondu_invoices';

    protected $primaryKey  = 'id';

    protected $fillable = [
        'order_id',
        'state',
        'external_reference_id',
        'invoice_uuid'
    ];
}
