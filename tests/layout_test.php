<?php

declare(strict_types=1);

/**
 * Main\Modules\Layout и tools/layout.php (issue #55): раскладка модулей без портала.
 *
 * Что держит: копии install/ — содержимое from в to, с подкаталогами, мимо ссылок; заглушки из
 * files; модуль без install/layout.php — в missing, кривой — в errors; сверка видит новый и
 * изменённый файл, запись пишет только отличающиеся; скрипт — код 1 при расхождении, 0 после
 * --write; раскладка самого shef.options совпадает с installDir; разбор git status.
 */

$root = dirname(__DIR__);
require_once $root.'/tests/assert.php';
require_once $root.'/lib/main/modules/layout.php';

use Shef\Options\Main\Modules\Layout;

$portal = sys_get_temp_dir().'/shef-layout-'.getmypid();
$mod = $portal.'/bitrix/modules/shef.demo';
mkdir($mod.'/install/js/shef.demo/sub', 0755, true);
mkdir($portal.'/bitrix/modules/shef.bare/install', 0755, true);
mkdir($portal.'/bitrix/modules/shef.bad/install', 0755, true);
file_put_contents($mod.'/install/index.php', '<?php');
file_put_contents($mod.'/install/js/shef.demo/a.js', 'A');
file_put_contents($mod.'/install/js/shef.demo/sub/b.js', 'B');
symlink('/etc', $mod.'/install/js/shef.demo/link');
file_put_contents($mod.'/install/layout.php', <<<'PHP'
<?php
return static fn (string $documentRoot, string $moduleDir): array => [
    'copy' => [['from' => '/install/js', 'to' => '/bitrix/js']],
    'files' => ['/bitrix/admin/shef_demo.php' => "<?php require('".substr($moduleDir, strlen($documentRoot))."/admin/page.php');\n"],
];
PHP);
foreach (['shef.bare', 'shef.bad'] as $m) {
    file_put_contents($portal.'/bitrix/modules/'.$m.'/install/index.php', '<?php');
}
file_put_contents($portal.'/bitrix/modules/shef.bad/install/layout.php', '<?php return 5;');

Check::group('сборка');

$all = ['shef.bad' => $portal.'/bitrix/modules/shef.bad', 'shef.bare' => $portal.'/bitrix/modules/shef.bare', 'shef.demo' => $mod];
$layout = Layout::build($portal, $all);
Check::same('копия с подкаталогами, мимо ссылки, заглушка с путём модуля', $layout['files'], [
    '/bitrix/admin/shef_demo.php' => "<?php require('/bitrix/modules/shef.demo/admin/page.php');\n",
    '/bitrix/js/shef.demo/a.js' => 'A',
    '/bitrix/js/shef.demo/sub/b.js' => 'B',
]);
Check::same('без описания — в missing, кривое — в errors', [$layout['missing'], $layout['errors']], [['shef.bare'], ['shef.bad: /install/layout.php не вернул функцию раскладки']]);

Check::group('сверка и запись');

Check::same('пустое дерево — все новые', Layout::diff($portal, $layout['files']), ['+ /bitrix/admin/shef_demo.php', '+ /bitrix/js/shef.demo/a.js', '+ /bitrix/js/shef.demo/sub/b.js']);
Check::same('запись — все три', count(Layout::write($portal, $layout['files'])), 3);
Check::same('после записи — без расхождений, повтор ничего не пишет', [Layout::diff($portal, $layout['files']), Layout::write($portal, $layout['files'])], [[], []]);
file_put_contents($portal.'/bitrix/js/shef.demo/a.js', 'правка');
Check::same('изменённый — «~»', Layout::diff($portal, $layout['files']), ['~ /bitrix/js/shef.demo/a.js']);

Check::group('скрипт tools/layout.php');

$run = static function (string ...$args) use ($root): array {
    exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, $root.'/tools/layout.php', ...$args])).' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
};
unlink($portal.'/bitrix/modules/shef.bad/install/layout.php');
[$code, $out] = $run($portal);
Check::same('расхождение — код 1 и строка «~»', [$code, str_contains($out, '~ /bitrix/js/shef.demo/a.js'), str_contains($out, 'shef.bad')], [1, true, true]);
[$code] = $run($portal, '--write');
Check::same('--write — код 0, потом сверка чистая', [$code, $run($portal)[0]], [0, 0]);
Check::same('без корня — код 2', $run('/nonexistent')[0], 2);

Check::group('раскладка shef.options = installDir');

$settings = include $root.'/.settings.php';
$own = (include $root.'/install/layout.php')('/var/www', '/var/www/bitrix/modules/shef.options');
Check::same('копии те же, что installDir', $own['copy'], array_map(static fn (array $d): array => ['from' => $d['from'], 'to' => $d['to']], $settings['installDir']['value']));

Check::group('git status');

Check::same('только под корнем сайта, статус и путь', Layout::gitDrift([' M www/bitrix/js/x.js', '?? www/shef/new.php', ' M scripts/deploy.sh', 'x'], 'www/'), ['M www/bitrix/js/x.js', '?? www/shef/new.php']);

exec('rm -rf '.escapeshellarg($portal));

Check::finish();
