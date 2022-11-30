<?php declare(strict_types=1);

namespace Plugin\MonduPayment\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20221130105300 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute("
          UPDATE `tzahlungsartsprache` zs
            SET zs.cHinweisText = 'Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie [url=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/]hier[/url].',       
                zs.`cHinweisTextShop` = 'Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie [url=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/]hier[/url].'
            WHERE zs.`cGebuehrname` = 'Mondu';
        ");
    }

    public function down()
    {

    }
}
