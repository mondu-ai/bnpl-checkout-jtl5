<?php declare(strict_types=1);

namespace Plugin\MonduPayment\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20230413140600 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute("
          ALTER TABLE `mondu_orders` ADD `authorized_net_term` INT NULL AFTER `updated_at`;
        ");
    }

    public function down()
    {
      $this->execute("
          ALTER TABLE `mondu_orders` DROP COLUMN `authorized_net_term`;
        ");
    }
}
