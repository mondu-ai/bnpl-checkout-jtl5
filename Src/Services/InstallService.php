<?php

namespace Plugin\MonduPayment\Src\Services;

use Plugin\MonduPayment\Src\Database\Migrations\DataBaseMigrations;
use Plugin\MonduPayment\Src\Database\Seeders\DatabaseSeeder;
use Plugin\MonduPayment\Src\Support\Facades\Filesystem\Storage;

class InstallService
{
    public function install()
    {

        $databaseMigrations = new DataBaseMigrations;
        $databaseMigrations->run_up();

        # $runSeeder = new DatabaseSeeder();
        # $runSeeder->run();
        Storage::load_resources();
    }

    public function unInstall()
    {

        $databaseMigrations = new DataBaseMigrations;
        $databaseMigrations->run_down();

        Storage::unload_resources();
    }
}
