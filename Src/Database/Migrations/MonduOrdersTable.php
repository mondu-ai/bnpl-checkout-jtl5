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
            $table->string('reference_id');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mondu_orders');
    }
}
