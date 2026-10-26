<?php

declare(strict_types=1);

/**
 * Каталог логов _log() (3.7.0, #47): вне корня сайта. Замер на стенде 2026-10-26: файл в
 * <DOCUMENT_ROOT>/local/log отдавался браузеру (200, содержимое видно) — лог с данными портала
 * открывался по прямой ссылке.
 *
 * Порядок проверок важен: константы в процессе определяются один раз, поэтому сначала —
 * случай без констант, потом BX_TEMPORARY_FILES_DIRECTORY, потом SHEF_OPTIONS_LOG_DIR.
 */

require __DIR__.'/assert.php';
require __DIR__.'/stub/bitrix.php';
require dirname(__DIR__).'/def-functions.php';

$tmp = sys_get_temp_dir().'/shef-logdir-'.getmypid();
@mkdir($tmp.'/home/www', 0777, true);
$root = $tmp.'/home/www';

Check::group('без констант');
Check::same('рядом с корнем сайта, не внутри', shef_options_log_dir($root), $tmp.'/home/shef-log');
Check::same('путь не начинается с корня сайта', str_starts_with(shef_options_log_dir($root), $root.'/'), false);

Check::group('BX_TEMPORARY_FILES_DIRECTORY');
define('BX_TEMPORARY_FILES_DIRECTORY', $tmp.'/bxtemp/');
Check::same('штатный временный каталог Битрикса', shef_options_log_dir($root), $tmp.'/bxtemp/shef-log');

Check::group('SHEF_OPTIONS_LOG_DIR');
define('SHEF_OPTIONS_LOG_DIR', $root.'/local/log/');
Check::same('константа проекта главнее — можно вернуть старый путь', shef_options_log_dir($root), $root.'/local/log');

Check::group('_log пишет туда же');
$src = (string)file_get_contents(dirname(__DIR__).'/def-functions.php');
Check::same('_log() берёт каталог из shef_options_log_dir(), не из DOCUMENT_ROOT/local/log', [str_contains($src, 'shef_options_log_dir()."/".$fileName'), str_contains($src, '$_SERVER["DOCUMENT_ROOT"]."/local/log/"')], [true, false]);

exec('rm -rf '.escapeshellarg($tmp));
Check::finish();
