<?php

namespace Plugin\MonduPayment\Src\Database\Migrations;

use Plugin\MonduPayment\Src\Database\Initialization\Schema;
use Plugin\MonduPayment\Src\Database\Initialization\Table;

class MonduOrdersTable
{
    public function up()
    {
        Schema::create('mondu_orders', function (Table $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->string('state');
            $table->string('external_reference_id');
            $table->string('order_uuid');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mondu_orders');
    }
}
