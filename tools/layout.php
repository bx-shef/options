<?php

declare(strict_types=1);

/**
 * Раскладка модулей линейки в репозитории портала — без портала, базы и ядра (issue #55).
 *
 * Портал, который выкладывается из git, держит в git файлы, которые установщики модулей кладут
 * вне своего каталога (/bitrix/css, /bitrix/js, заглушки страниц, компоненты). Скрипт собирает их
 * из install/layout.php каждого модуля shef.* и сверяет с деревом или пишет.
 *
 *   php www/bitrix/modules/shef.options/tools/layout.php www            # сверить: код 1, если разошлось
 *   php www/bitrix/modules/shef.options/tools/layout.php www --write    # записать
 *
 * На сервере не нужен: там модули ставит php bitrix.php shef:modules:sync.
 */

require_once dirname(__DIR__).'/lib/main/modules/layout.php';

$args = array_slice($argv, 1);
$write = in_array('--write', $args, true);
$root = (string)(array_values(array_filter($args, static fn (string $a): bool => !str_starts_with($a, '--')))[0] ?? '');
if ($root === '' || !is_dir($root.'/bitrix/modules')) {
    fwrite(STDERR, "Укажите корень сайта (каталог с bitrix/modules): php tools/layout.php <корень> [--write]\n");
    exit(2);
}
$root = (string)realpath($root);

$modules = [];
foreach (['/bitrix/modules', '/local/modules'] as $base) {
    foreach (glob($root.$base.'/shef.*', GLOB_ONLYDIR) ?: [] as $dir) {
        if (is_file($dir.'/install/index.php')) {
            $modules[basename($dir)] = $dir;
        }
    }
}
ksort($modules);

$layout = \Shef\Options\Main\Modules\Layout::build($root, $modules);
foreach ($layout['errors'] as $error) {
    fwrite(STDERR, 'ОШИБКА '.$error."\n");
}
foreach ($layout['missing'] as $id) {
    echo "нет описания раскладки (install/layout.php): $id\n";
}
if ($write) {
    foreach (\Shef\Options\Main\Modules\Layout::write($root, $layout['files']) as $path) {
        echo "записан $path\n";
    }
    exit($layout['errors'] === [] ? 0 : 1);
}
$diff = \Shef\Options\Main\Modules\Layout::diff($root, $layout['files']);
foreach ($diff as $line) {
    echo $line."\n";
}
echo count($layout['files']).' файлов раскладки, расходится '.count($diff)."\n";
exit($diff === [] && $layout['errors'] === [] ? 0 : 1);
