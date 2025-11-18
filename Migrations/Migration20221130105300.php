<?php declare(strict_types=1);

namespace Plugin\MonduPayment\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20221130105300 extends Migration implements IMigration
{
    public function up()
    {
        // Clear all hint texts for Mondu payment methods instead of setting them
        $this->execute("
          UPDATE `tzahlungsartsprache` zs
            SET zs.`cHinweisTextShop` = '', zs.`cHinweisText` = ''
            WHERE zs.`cGebuehrname` = 'Mondu';
        ");
    }

    public function down()
    {

    }
}

