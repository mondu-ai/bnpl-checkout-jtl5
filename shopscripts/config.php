<?php

declare(strict_types=1);

use JTL\Shop;
use JTL\XMLParser;
use JTL\Plugin\Admin\Validation\LegacyPluginValidator;
use JTL\Plugin\Admin\Validation\PluginValidator;
use JTL\Plugin\Admin\Listing;
use JTL\Plugin\Admin\ListingItem;
use JTL\DB\ReturnType;
use JTL\Plugin\Admin\InputType;
use JTL\Plugin\Helper;
use JTL\Plugin\Helper as PluginHelper;
use JTL\Plugin\Admin\Markdown;
use JTL\Helpers\Text;

define('PFAD_ROOT', '/var/www/html/');
require_once PFAD_ROOT . 'admin/includes/admininclude.php';

require_once PFAD_ROOT . PFAD_ADMIN . PFAD_INCLUDES . 'pluginverwaltung_inc.php';
require_once PFAD_ROOT . PFAD_INCLUDES . 'plugin_inc.php';

$db = Shop::Container()->getDB();
$cache = Shop::Container()->getCache();
$parser = new XMLParser();
$legacyValidator = new LegacyPluginValidator($db, $parser);
$pluginValidator = new PluginValidator($db, $parser);

$listing = new Listing($db, $cache, $legacyValidator, $pluginValidator);

$pluginsInstalled = $listing->getInstalled();

$monduPlugin = $pluginsInstalled->first(static function ($e) {
    /** @var ListingItem $e */
    return $e->getName() === "Mondu Payment";
});

$monduPluginId = $monduPlugin->getID();


$_POST = [
    "kPlugin" => $monduPluginId,
    "kPluginAdminMenu" => 1,
    "Setting" => 1,
    "sandbox_mode" => 1,
    "api_secret" => getenv("BNPL_MERCHANT_API_TOKEN"),
    "webhooks_secret" => getenv("BNPL_MERCHANT_WEBHOOK_SECRET"),
    "mark_order_as_paid" => 1,
    "speichern" => "Speichern"
];

$notice = '';
$errorMsg = '';
$hasError = false;
$plugin = null;
$loader = null;


if ($monduPluginId > 0) {
    $plgnConf = isset($_POST['kPluginAdminMenu'])
        ? $db->queryPrepared(
            "SELECT *
                        FROM tplugineinstellungenconf
                        WHERE kPluginAdminMenu != 0
                            AND kPlugin = :plgn
                            AND cConf != 'N'
                            AND kPluginAdminMenu = :kpm",
            ['plgn' => $monduPluginId, 'kpm' => (Int) $_POST['kPluginAdminMenu']],
            ReturnType::ARRAY_OF_OBJECTS
        )
        : [];
    foreach ($plgnConf as $current) {
        if ($current->cInputTyp === InputType::NONE) {
            continue;
        }
        $db->delete(
            'tplugineinstellungen',
            ['kPlugin', 'cName'],
            [$monduPluginId, $current->cWertName]
        );
        $upd = new stdClass();
        $upd->kPlugin = $monduPluginId;
        $upd->cName = $current->cWertName;
        if (isset($_POST[$current->cWertName])) {
            if (is_array($_POST[$current->cWertName])) {
                if ($current->cConf === Config::TYPE_DYNAMIC) {
                    // selectbox with "multiple" attribute
                    $upd->cWert = serialize($_POST[$current->cWertName]);
                } else {
                    // radio buttons
                    $upd->cWert = $_POST[$current->cWertName][0];
                }
            } else {
                // textarea/text
                $upd->cWert = $_POST[$current->cWertName];
            }
        } else {
            // checkboxes that are not checked
            $upd->cWert = null;
        }
        if (!$db->insert('tplugineinstellungen', $upd)) {
            $hasError = true;
        }
    }
    if ($hasError) {
        $errorMsg = __('errorConfigSave');
    } else {
        $notice = __('successConfigSave');
    }
    $loader = Helper::getLoaderByPluginID($monduPluginId, $db, $cache);
    if ($loader !== null) {
        try {
            $plugin = $loader->init($monduPluginId, true);
        } catch (InvalidArgumentException $e) {
            echo "ERROR: Mondu plugin not found";
        }

        if ($plugin !== null && $plugin->isBootstrap()) {
            Helper::updatePluginInstance($plugin);
        }
    }

    $loader = $loader ?? Helper::getLoaderByPluginID($monduPluginId, $db, $cache);
    if ($loader !== null) {
        try {
            $plugin = $loader->init($monduPluginId, true);
        } catch (InvalidArgumentException $e) {
            echo "ERROR: Mondu plugin not found";
        }
    }
    if ($plugin !== null) {
        $oPlugin = $plugin;
        if (ADMIN_MIGRATION && $plugin instanceof Plugin) {
            Shop::Container()->getGetText()->loadAdminLocale('pages/dbupdater');
            $manager    = new MigrationManager(
                $db,
                $plugin->getPaths()->getBasePath() . PFAD_PLUGIN_MIGRATIONS,
                $plugin->getPluginID(),
                $plugin->getMeta()->getSemVer()
            );
            $migrations = count($manager->getMigrations());
            $smarty->assign('manager', $manager)
                ->assign('updatesAvailable', $migrations > count($manager->getExecutedMigrations()));
        }
        $smarty->assign('oPlugin', $plugin);

        executeHook(HOOK_PLUGIN_SAVE_OPTIONS, [
            'plugin'   => $plugin,
            'hasError' => &$hasError,
            'msg'      => &$notice,
            'error'    => $errorMsg,
            'options'  => $plugin->getConfig()->getOptions()
        ]);

        foreach ($plugin->getAdminMenu()->getItems() as $menu) {
            if ($menu->isMarkdown === true) {
                $markdown = new Markdown();
                $markdown->setImagePrefixURL($plugin->getPaths()->getBaseURL());
                $content    = $markdown->text(Text::convertUTF8(file_get_contents($menu->file)));
                $menu->html = $smarty->assign('content', $content)->fetch($menu->tpl);
            } elseif ($menu->configurable === false) {
                if ($menu->file !== '' && file_exists($plugin->getPaths()->getAdminPath() . $menu->file)) {
                    ob_start();
                    require $plugin->getPaths()->getAdminPath() . $menu->file;
                    $menu->html = ob_get_clean();
                } elseif (!empty($menu->tpl) && $menu->kPluginAdminMenu === -1) {
                    if (isset($menu->data)) {
                        $smarty->assign('data', $menu->data);
                    }
                    $menu->html = $smarty->fetch($menu->tpl);
                } elseif ($plugin->isBootstrap() === true) {
                    $menu->html = PluginHelper::bootstrap($monduPluginId, $loader)
                        ->renderAdminMenuTab($menu->name, $menu->id, $smarty);
                }
            } elseif ($menu->configurable === true) {
                $hidden = true;
                foreach ($plugin->getConfig()->getOptions() as $confItem) {
                    if ($confItem->inputType !== InputType::NONE && $confItem->confType === 'Y') {
                        $hidden = false;
                        break;
                    }
                }
                if ($hidden) {
                    $plugin->getAdminMenu()->removeItem($menu->kPluginAdminMenu);
                    continue;
                }
                $smarty->assign('oPluginAdminMenu', $menu);
                $menu->html = $smarty->fetch('tpl_inc/plugin_options.tpl');
            }
        }
    }
} else {
    echo "ERROR: Invalid id";
}
