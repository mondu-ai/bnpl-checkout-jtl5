<?php

namespace Plugin\MonduPayment\Src\Database\Migrations;

use Plugin\MonduPayment\Src\Database\Initialization\Schema;
use Plugin\MonduPayment\Src\Database\Initialization\Table;

class MonduApiTable
{
    public function up()
    {
        Schema::create('mondu_api', function (Table $table) {
            $table->id();
            $table->string('api_secret');
            $table->string('webhooks_secret');
            $table->boolean('sandbox');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mondu_api');
    }
}
