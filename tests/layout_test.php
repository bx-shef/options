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

Check::group('блок в общем файле (#68)');

$b = ['begin' => '// shef.demo: menu begin', 'end' => '// shef.demo: menu end', 'note' => '— не править', 'body' => "\$a[] = 1;\n"];
$ours = "// shef.demo: menu begin — не править\n\$a[] = 1;\n// shef.demo: menu end\n";
$theirs = "// other: begin\n\$a[] = 2;\n// other: end\n";
$merge = static fn (?string $file): string => Layout::mergeBlock($file, $b['begin'], $b['end'], $b['body'], $b['note']);
Check::same('файла нет — открывающий тег и блок', $merge(null), "<?php\n\n".$ours);
Check::same('чужой файл — блок в конец, чужое как было', $merge("<?php\n\$a = [];\n"), "<?php\n\$a = [];\n\n".$ours);
Check::same('повтор ничего не меняет', $merge($merge("<?php\n\$a = [];\n")), $merge("<?php\n\$a = [];\n"));
Check::same('чужой блок после нашего — остаётся, наш — в конец', $merge("<?php\n\n".$ours."\n".$theirs), "<?php\n\n\n".$theirs."\n".$ours);
Check::same('старый вариант нашего блока заменяется', $merge("<?php\n// shef.demo: menu begin\nold();\n// shef.demo: menu end\n"), "<?php\n\n".$ours);
Check::same('CRLF сохраняется', $merge("<?php\r\n\$a = [];\r\n"), "<?php\r\n\$a = [];\r\n\r\n".str_replace("\n", "\r\n", $ours));
Check::same('файл кончается HTML — блок в своих тегах', $merge("<?php \$a = []; ?>\n<b>x</b>\n"), "<?php \$a = []; ?>\n<b>x</b>\n<?php ".rtrim($ours, "\n")." ?>\n");
Check::same('и снимается со своими тегами', Layout::stripBlock($merge("<?php \$a = []; ?>\n<b>x</b>\n"), $b['begin'], $b['end']), "<?php \$a = []; ?>\n<b>x</b>\n");
$thrown = false;
try {
    $merge("<?php\n// shef.demo: menu begin\n");
} catch (\RuntimeException) {
    $thrown = true;
}
Check::same('метки разбиты — исключение', $thrown, true);

$blk = $portal.'/bitrix/modules/shef.blk';
mkdir($blk.'/install', 0755, true);
file_put_contents($blk.'/install/index.php', '<?php');
file_put_contents($blk.'/install/layout.php', '<?php return static fn (string $r, string $d): array => [\'blocks\' => [\'/.top.menu_ext.php\' => '.var_export($b, true).'], \'remove\' => [\'/shef/old\' => null, \'/local/own.php\' => [md5(\'own\')], \'/local/edited.php\' => [md5(\'own\')], \'/../etc\' => null]];');
mkdir($portal.'/shef/old/sub', 0755, true);
mkdir($portal.'/local', 0755, true);
file_put_contents($portal.'/shef/old/sub/x.php', 'x');
file_put_contents($portal.'/local/own.php', 'own');
file_put_contents($portal.'/local/edited.php', 'правка');
file_put_contents($portal.'/.top.menu_ext.php', "<?php\n".$theirs);
$built = Layout::build($portal, ['shef.blk' => $blk]);
Check::same('сборка: блок от файла дерева, чужой блок цел', [$built['files'], $built['errors']], [['/.top.menu_ext.php' => "<?php\n".$theirs."\n".$ours], []]);
Check::same('remove: каталог и свой файл — да, правленый и «..» — нет', Layout::removable($portal, $built['remove']), ['/local/own.php', '/shef/old']);
[$code, $out] = $run($portal);
Check::same('скрипт: «~» блока и «-» удаляемого, код 1', [$code, str_contains($out, '~ /.top.menu_ext.php'), str_contains($out, '- /shef/old'), str_contains($out, '- /local/edited.php')], [1, true, true, false]);
[$code] = $run($portal, '--write');
Check::same('--write: блок вписан, удалено, правленый на месте, сверка чистая', [$code, file_get_contents($portal.'/.top.menu_ext.php'), is_dir($portal.'/shef/old'), is_file($portal.'/local/own.php'), is_file($portal.'/local/edited.php'), $run($portal)[0]], [0, "<?php\n".$theirs."\n".$ours, false, false, true, 0]);
file_put_contents($portal.'/.top.menu_ext.php', "<?php\n".$ours.$theirs);
Check::same('сосед дописал блок после нашего — повторная сборка его сохраняет', Layout::build($portal, ['shef.blk' => $blk])['files']['/.top.menu_ext.php'], "<?php\n".$theirs."\n".$ours);
file_put_contents($portal.'/.top.menu_ext.php', "<?php\n// shef.demo: menu begin\n");
$broken = Layout::build($portal, ['shef.blk' => $blk]);
Check::same('метки разбиты — ошибка, файла в раскладке нет', [isset($broken['files']['/.top.menu_ext.php']), count($broken['errors'])], [false, 1]);
exec('rm -rf '.escapeshellarg($blk));

Check::group('git status');

Check::same('только под корнем сайта, статус и путь', Layout::gitDrift([' M www/bitrix/js/x.js', '?? www/shef/new.php', ' M scripts/deploy.sh', 'x'], 'www/'), ['M www/bitrix/js/x.js', '?? www/shef/new.php']);

exec('rm -rf '.escapeshellarg($portal));

Check::finish();
