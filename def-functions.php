<?php

if (!function_exists('_pr')) {
    function _pr($o, bool $show = false, bool $die = false, bool $fullBackTrace = true): void
    {
        if (\Bitrix\Main\Engine\CurrentUser::get()->isAdmin() || $show) {
            $bt = debug_backtrace();

            $firstBt = $bt[0];
            $dRoot = $_SERVER["DOCUMENT_ROOT"];
            $dRoot = str_replace("/", "\\", $dRoot);
            $firstBt["file"] = str_replace($dRoot, "", $firstBt["file"]);
            $dRoot = str_replace("\\", "/", $dRoot);
            $firstBt["file"] = str_replace($dRoot, "", $firstBt["file"]);

            ?>
            <div style="font-size:9pt; color:#000; background:#fff; border:1px dashed #000;">
            <div style="padding:3px 5px; background:#99CCFF;">
                <?php
                if ($fullBackTrace == false): ?>
                    File: <b><?=$firstBt["file"]?></b> [line: <?=$firstBt["line"]?>]
                <?php else: ?>
                    <?php
                    foreach ($bt as $value): ?>
                        <?php
                        $dRoot = str_replace("/", "\\", $dRoot);
                        $value["file"] = str_replace($dRoot, "", $value["file"]);
                        $dRoot = str_replace("\\", "/", $dRoot);
                        $value["file"] = str_replace($dRoot, "", $value["file"]);
                        ?>

                        File: <b><?=$value["file"]?></b> [line: <?=$value["line"]?>]<br>
                    <?php
                    endforeach ?>
                <?php
                endif; ?>
            </div>
            <pre style="color:#000; padding:10px;"><?php
                is_array($o)
                    ? print_r($o)
                    : print_r(htmlspecialcharsbx($o)) ?></pre>
            </div><?php
            if ($die == true) {
                die();
            }
        }
    }
}

if (!function_exists('shef_options_log_dir')) {
    /**
     * Каталог логов _log() (3.7.0, #47): вне корня сайта — лог с данными портала не открыть из браузера.
     * По порядку: константа SHEF_OPTIONS_LOG_DIR (проект может вернуть старый путь); штатный
     * временный каталог Битрикса BX_TEMPORARY_FILES_DIRECTORY/shef-log; каталог рядом с корнем сайта
     * (<родитель DOCUMENT_ROOT>/shef-log, на BitrixVM — /home/bitrix/shef-log), если туда можно писать;
     * иначе прежний <DOCUMENT_ROOT>/local/log с .htaccess «закрыто» (apache; nginx его не читает —
     * тогда закрыть /local/log в конфигурации сервера).
     */
    function shef_options_log_dir(?string $documentRoot = null): string
    {
        $root = rtrim((string)($documentRoot ?? ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
        if (defined('SHEF_OPTIONS_LOG_DIR') && (string)SHEF_OPTIONS_LOG_DIR !== '') {
            return rtrim((string)SHEF_OPTIONS_LOG_DIR, '/');
        }
        if (defined('BX_TEMPORARY_FILES_DIRECTORY') && (string)BX_TEMPORARY_FILES_DIRECTORY !== '') {
            return rtrim((string)BX_TEMPORARY_FILES_DIRECTORY, '/').'/shef-log';
        }
        $parent = $root !== '' ? dirname($root) : '';
        if ($parent !== '' && $parent !== $root && $parent !== '/' && (is_dir($parent.'/shef-log') ? is_writable($parent.'/shef-log') : is_writable($parent))) {
            return $parent.'/shef-log';
        }
        $old = $root.'/local/log';
        if (!is_file($old.'/.htaccess')) {
            @mkdir($old, defined('BX_DIR_PERMISSIONS') ? BX_DIR_PERMISSIONS : 0755, true);
            @file_put_contents($old.'/.htaccess', "Require all denied
Deny from all
");
        }

        return $old;
    }
}

if (!function_exists('_log')) {
    function _log(array $value = [], string $fileName = 'log'): void
    {
        $e = new \Exception();
        \Bitrix\Main\IO\File::putFileContents(
            shef_options_log_dir()."/".$fileName."_".date('dmY').".log",
            implode("\n", [
                '',
                ' >>> '.date('d.m.Y H:i:s').' >>>',
                print_r($value, true),
                ' >>> trace >>>',
                print_r(str_replace($_SERVER["DOCUMENT_ROOT"], '', $e->getTraceAsString()), true),
                ' >>> >>> >>>'
            ])
            //,\Bitrix\Main\IO\File::REWRITE ////
            ,
            \Bitrix\Main\IO\File::APPEND
        );
    }
}
