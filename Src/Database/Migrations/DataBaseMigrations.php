<?php

namespace Plugin\MonduPayment\Src\Database\Migrations;

use Plugin\MonduPayment\Src\Database\Initialization\Migration;

class DataBaseMigrations extends Migration
{
    public function run_up()
    {
        $this->call([
            MonduOrdersTable::class,
            MonduApiTable::class
        ], 'up');
    }

    public function run_down()
    {
        $this->call([
            MonduApiTable::class,
            MonduOrdersTable::class,
        ], 'down');
    }
}
