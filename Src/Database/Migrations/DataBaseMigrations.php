<?php

namespace Plugin\MonduPayment\Src\Database\Migrations;

use Plugin\MonduPayment\Src\Database\Initialization\Migration;

class DataBaseMigrations extends Migration
{
    public function run_up()
    {
        $this->call([
            MonduOrdersTable::class,
            MonduInvoicesTable::class
        ], 'up');
    }

    public function run_down()
    {
        $this->call([
            MonduInvoicesTable::class,
            MonduOrdersTable::class,
        ], 'down');
    }
}
