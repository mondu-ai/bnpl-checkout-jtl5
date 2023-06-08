<?php

namespace Plugin\MonduPayment\Src\Support\Facades\Filesystem;

class Storage
{
    public static function load_resources()
    {
        $loadingPathFrom = Directory::get_resources();
        $loadingPathTo = Directory::get_root() . '/mediafiles/Resources';
        if (!file_exists($loadingPathTo) && !is_dir($loadingPathTo)) {
            exec("cp -r $loadingPathFrom $loadingPathTo");
        } else {
            $loadingPathFrom .= '/*';
            exec("cp -r $loadingPathFrom $loadingPathTo");
        }
    }

    public static function unload_resources()
    {
        $unLoadingPath = Directory::get_root() . '/mediafiles/Resources';
        exec("rm -r $unLoadingPath");
    }
}
