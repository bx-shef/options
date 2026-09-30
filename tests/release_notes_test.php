<?php

declare(strict_types=1);

/**
 * Примечания к релизу собираются от предыдущего ВЫПУЩЕННОГО тега.
 *
 * Раньше release.yml брал из CHANGELOG одну секцию текущей версии. Всё, что
 * слили в main и не выпустили, из примечаний выпадало молча: между v3.0.6 и
 * v3.0.14 так накопилось семь секций, и на странице релиза был виден один
 * линтер, хотя в архиве лежали ещё и починка toArray(), \Stringable и снятие
 * _log1().
 *
 * Сборка живёт в build.sh (`--notes`), а не в yaml: то же самое получается
 * локально одной командой, и его можно проверить — вот этим тестом.
 *
 * Тест гоняет НАСТОЯЩИЙ build.sh на песочнице: свой CHANGELOG.md, свой
 * install/version.php и свой git-репозиторий с тегами. Ядро не нужно.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$sandbox = sys_get_temp_dir().'/shef-options-notes-'.getmypid();

register_shutdown_function(static function () use ($sandbox): void {
    exec('rm -rf '.escapeshellarg($sandbox));
});

exec('rm -rf '.escapeshellarg($sandbox));
mkdir($sandbox.'/install', 0777, true);
copy($root.'/build.sh', $sandbox.'/build.sh');
chmod($sandbox.'/build.sh', 0755);

file_put_contents($sandbox.'/install/version.php', <<<'PHP'
<?php

$arModuleVersion = [
    'VERSION' => '2.0.0',
    'VERSION_DATE' => '2026-01-04 00:00:00'
];
PHP);

// Версии нарочно такие: 1.10.0 старше 1.5.0 числами и МЛАДШЕ строкой.
// Сравнение строкой выбрало бы предыдущим 1.5.0, и секция 1.10.0 уехала бы
// в примечания второй раз.
file_put_contents($sandbox.'/CHANGELOG.md', <<<'MD'
# change log

## 2.0.0 — 2026-01-04
* строка 2.0.0

## 1.10.0 — 2026-01-03
* строка 1.10.0

## 1.5.0 — 2026-01-02
* строка 1.5.0

## 1.0.0 — 2026-01-01
* строка 1.0.0
MD);

/** Запуск build.sh в песочнице: [вывод, stderr, код возврата]. */
$run = static function (string $args = '') use ($sandbox): array {
    $out = $sandbox.'/.out';
    $err = $sandbox.'/.err';

    $code = 0;
    $ignored = [];
    exec(
        'cd '.escapeshellarg($sandbox).' && ./build.sh --notes '.$args
        .' >'.escapeshellarg($out).' 2>'.escapeshellarg($err),
        $ignored,
        $code
    );

    return [(string)file_get_contents($out), (string)file_get_contents($err), $code];
};

Check::group('предыдущая версия задана явно');

[$notes, , $code] = $run('1.10.0');

Check::same('код возврата ноль', $code, 0);
Check::same('секция текущей версии на месте', str_contains($notes, 'строка 2.0.0'), true);
Check::same('предыдущая не попала', str_contains($notes, 'строка 1.10.0'), false);

// Заголовок текущей версии не печатается: он и так стоит заголовком релиза.
Check::same('заголовков нет вовсе', preg_match('/^## /m', $notes), 0);
Check::same('строки-разделителя нет', str_contains($notes, 'отдельными релизами'), false);

Check::group('между выпусками накопилось несколько версий');

[$notes, , ] = $run('1.0.0');

Check::same('текущая на месте', str_contains($notes, 'строка 2.0.0'), true);
Check::same('невыпущенная 1.10.0 подхвачена', str_contains($notes, 'строка 1.10.0'), true);
Check::same('невыпущенная 1.5.0 подхвачена', str_contains($notes, 'строка 1.5.0'), true);

// Граница исключающая: до предыдущего выпуска, но не включая его.
Check::same('выпущенная 1.0.0 не попала', str_contains($notes, 'строка 1.0.0'), false);

// Без заголовков бульеты трёх выпусков слиплись бы в один список.
Check::same('заголовок 1.10.0 напечатан', str_contains($notes, '## 1.10.0 '), true);
Check::same('заголовка текущей версии нет', str_contains($notes, '## 2.0.0 '), false);
Check::same('строка-разделитель есть', str_contains($notes, 'отдельными релизами'), true);
Check::same('разделитель один', substr_count($notes, 'отдельными релизами'), 1);

Check::group('тег с «v» и без — одно и то же');

[$withV, , ] = $run('v1.0.0');

Check::same('вывод совпадает', $withV, $notes);

Check::group('секции предыдущей версии в CHANGELOG нет');

[$notes, $stderr, $code] = $run('0.9.0');

// Обрезать нечем, поэтому берём всё до конца файла — и говорим об этом.
// Молчаливое «одна секция» потеряло бы ровно то, ради чего тест написан.
Check::same('код возврата ноль', $code, 0);
Check::same('предупреждение напечатано', str_contains($stderr, 'нет секции 0.9.0'), true);
Check::same('дошли до конца файла', str_contains($notes, 'строка 1.0.0'), true);

Check::group('предыдущая версия берётся из тегов');

$git = 'cd '.escapeshellarg($sandbox)
    .' && git init -q -b main'
    .' && git -c user.email=t@t -c user.name=t commit -q --allow-empty -m init';

// v3.0.0 старше текущей 2.0.0 — его надо пропустить: ветка поддержки или
// ошибочный тег иначе увели бы отсчёт туда, где секции нет.
// vX.Y — не версия, такие теги тоже мимо.
foreach (['v1.0.0', 'v1.5.0', 'v1.10.0', 'v3.0.0', 'v2.0', 'release-1'] as $tag) {
    $git .= ' && git tag '.escapeshellarg($tag);
}

exec($git.' >/dev/null 2>&1', $ignored, $code);
Check::same('песочница с тегами готова', $code, 0);

[$notes, , $code] = $run();

Check::same('код возврата ноль', $code, 0);
Check::same('текущая версия на месте', str_contains($notes, 'строка 2.0.0'), true);

// Предыдущий выпуск — 1.10.0: самый старший тег СТРОГО МЛАДШЕ 2.0.0.
Check::same('отсчёт от 1.10.0', str_contains($notes, 'строка 1.10.0'), false);
Check::same('тег выше текущей версии пропущен', str_contains($notes, 'строка 1.0.0'), false);

Check::group('секции текущей версии в CHANGELOG нет');

file_put_contents($sandbox.'/CHANGELOG.md', "# change log\n\n## 1.0.0 — 2026-01-01\n* строка 1.0.0\n");

[$notes, , $code] = $run('1.0.0');

// Пусто и без ошибки: release.yml на пустых примечаниях подставляет свой
// текст. Ненулевой код уронил бы выпуск из-за оформления CHANGELOG.
Check::same('код возврата ноль', $code, 0);
Check::same('вывод пуст', trim($notes), '');

Check::finish();
