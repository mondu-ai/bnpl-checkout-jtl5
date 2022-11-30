<?php declare(strict_types=1);

namespace Plugin\jtl_test\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

class Migration20221130105300 extends Migration implements IMigration
{
    public function up()
    {
        $this->execute("
          UPDATE `tzahlungsartsprache` zs
          inner join tzahlungsart z ON z.kZahlungsart = zs.kZahlungsart
          SET zs.cHinweisText = 'Hinweise zur Verarbeitung Ihrer personenbezogenen Daten durch die Mondu GmbH finden Sie [url=https://www.mondu.ai/de/datenschutzgrundverordnung-kaeufer/]hier[/url].'
          WHERE z.cAnbieter = 'Mondu';

          UPDATE `tzahlungsartsprache` zs
          SET zs.cName = REPLACE(zs.cName, 'Mondu ', '')
          WHERE zs.`cGebuehrname` = 'Mondu';
        ");
    }

    public function down()
    {

    }
}