<?php declare(strict_types=1);

namespace Plugin\MonduPayment\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20230413140600 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute("
          CREATE TABLE IF NOT EXISTS `mondu_orders` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `order_id` int(11) NOT NULL,
              `state` varchar(256) DEFAULT NULL,
              `external_reference_id` varchar(256) DEFAULT NULL,
              `order_uuid` varchar(256) DEFAULT NULL,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            
            ALTER TABLE `mondu_orders` ADD `authorized_net_term` INT NULL AFTER `updated_at`;
        ");
    }

    public function down()
    {
      
    }
}
