<?php

declare(strict_types=1);

use JTL\Shop;
use JTL\XMLParser;
use JTL\Plugin\Admin\Installation\Uninstaller;
use JTL\Plugin\Admin\Validation\LegacyPluginValidator;
use JTL\Plugin\Admin\Validation\PluginValidator;
use JTL\Plugin\Admin\Installation\Installer;

// use Plugin\MonduPayment\Src\Services\InstallService;
// require_once __DIR__ . '/../../admin/includes/admininclude.php';

define('PFAD_ROOT', '/var/www/html/');
require_once PFAD_ROOT . 'admin/includes/admininclude.php';

require_once PFAD_ROOT . PFAD_ADMIN . PFAD_INCLUDES . 'pluginverwaltung_inc.php';
require_once PFAD_ROOT . PFAD_INCLUDES . 'plugin_inc.php';

$db = Shop::Container()->getDB();
$cache = Shop::Container()->getCache();
$parser = new XMLParser();
$uninstaller = new Uninstaller($db, $cache);
$legacyValidator = new LegacyPluginValidator($db, $parser);
$pluginValidator = new PluginValidator($db, $parser);

$installer = new Installer($db, $uninstaller, $legacyValidator, $pluginValidator);

$installer->setDir(basename("MonduPayment"));
$res = $installer->prepare();
