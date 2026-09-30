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
 * install/version.php и свой git-репозиторий с тегами, в том числе на
 * невлитой ветке. Ядро не нужно.
 *
 * Отдельно сторожатся деградированные пути. Они важнее нормального: когда
 * граница не определилась, примечания уходят до конца файла, и текст на
 * странице релиза не должен при этом врать.
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
    'VERSION_DATE' => '2026-01-06 00:00:00'
];
PHP);

/**
 * Версии подобраны нарочно:
 *
 * * 1.10.0 старше 1.5.0 числами и МЛАДШЕ строкой — сравнение строкой выбрало
 *   бы предыдущим выпуском не ту версию;
 * * 1.1.1 — префикс 1.1.15, и различает их только пробел на конце «## 1.1.1 »;
 * * 1a1a1 стоит между ними: если границу искать регулярным выражением, точки
 *   в «## 1.1.1 » совпадут с «a», и отсчёт оборвётся на этой секции.
 */
$changelog = <<<'MD'
# change log

## 2.0.0 — 2026-01-06
* строка 2.0.0

## 1.10.0 — 2026-01-05
* строка 1.10.0

## 1.5.0 — 2026-01-04
* строка 1.5.0

## 1.1.15 — 2026-01-03
* строка 1.1.15

## 1a1a1 — 2026-01-02
* строка 1a1a1

## 1.1.1 — 2026-01-01
* строка 1.1.1
MD;

file_put_contents($sandbox.'/CHANGELOG.md', $changelog);

/** Запуск build.sh в песочнице: [вывод, stderr, код возврата]. */
$run = static function (string $args = '') use ($sandbox): array {
    $out = $sandbox.'/.out';
    $err = $sandbox.'/.err';

    $code = 0;
    $ignored = [];
    exec(
        'cd '.escapeshellarg($sandbox).' && ./build.sh '.$args
        .' >'.escapeshellarg($out).' 2>'.escapeshellarg($err),
        $ignored,
        $code
    );

    return [(string)file_get_contents($out), (string)file_get_contents($err), $code];
};

Check::group('предыдущая версия задана явно');

[$notes, $stderr, $code] = $run('--notes 1.10.0');

Check::same('код возврата ноль', $code, 0);
Check::same('секция текущей версии на месте', str_contains($notes, 'строка 2.0.0'), true);
Check::same('предыдущая не попала', str_contains($notes, 'строка 1.10.0'), false);

// Заголовок текущей версии не печатается: он и так стоит заголовком релиза.
Check::same('заголовков нет вовсе', preg_match('/^## /m', $notes), 0);
Check::same('строки-разделителя нет', str_contains($notes, 'отдельными релизами'), false);

// От чего шёл отсчёт, видно в журнале релиза — иначе неверную границу
// не с чем сопоставить.
Check::same('отсчёт назван в stderr', str_contains($stderr, 'Предыдущий выпуск: 1.10.0'), true);

Check::group('между выпусками накопилось несколько версий');

[$notes, , ] = $run('--notes 1.5.0');

Check::same('текущая на месте', str_contains($notes, 'строка 2.0.0'), true);
Check::same('невыпущенная 1.10.0 подхвачена', str_contains($notes, 'строка 1.10.0'), true);

// Граница исключающая: до предыдущего выпуска, но не включая его.
Check::same('выпущенная 1.5.0 не попала', str_contains($notes, 'строка 1.5.0'), false);

// Без заголовков бульеты нескольких выпусков слиплись бы в один список.
Check::same('заголовок 1.10.0 напечатан', str_contains($notes, '## 1.10.0 '), true);
Check::same('заголовка текущей версии нет', str_contains($notes, '## 2.0.0 '), false);
Check::same('строка-разделитель есть', str_contains($notes, 'отдельными релизами'), true);
Check::same('разделитель один', substr_count($notes, 'отдельными релизами'), 1);

Check::group('номер версии — префикс другого номера');

[$notes, , ] = $run('--notes 1.1.1');

// «## 1.1.1» — начало «## 1.1.15», и разводит их только пробел на конце.
Check::same('1.1.15 подхвачена', str_contains($notes, 'строка 1.1.15'), true);
Check::same('граница на 1.1.1', str_contains($notes, 'строка 1.1.1' . "\n"), false);

// Точки в номере — точки, а не «любой символ»: иначе отсчёт оборвался бы
// на секции 1a1a1, которая лежит выше настоящей границы.
Check::same('1a1a1 границей не стала', str_contains($notes, 'строка 1a1a1'), true);

Check::group('тег с «v» и без — одно и то же');

[$withV, , ] = $run('--notes v1.5.0');
[$withoutV, , ] = $run('--notes 1.5.0');

Check::same('вывод совпадает', $withV, $withoutV);

Check::group('секции предыдущей версии в CHANGELOG нет');

[$notes, $stderr, $code] = $run('--notes 0.9.0');

// Обрезать нечем, поэтому берём всё до конца файла — и говорим об этом.
Check::same('код возврата ноль', $code, 0);
Check::same('предупреждение напечатано', str_contains($stderr, 'нет секции 0.9.0'), true);
Check::same('дошли до конца файла', str_contains($notes, 'строка 1.1.1'), true);

// Под шапкой «версии ниже отдельными релизами не выпускались» оказались бы
// выпущенные версии. Врать на странице релиза нельзя — тем более когда
// что-то пошло не так.
Check::same('разделителя нет: граница не известна', str_contains($notes, 'отдельными релизами'), false);

Check::group('секция предыдущей версии стоит ВЫШЕ текущей');

// Так выглядит и «--notes <текущая версия>»: границу awk не встретит и
// напечатает всё до конца файла. Молчать нельзя.
[$notes, $stderr, $code] = $run('--notes 2.0.0');

Check::same('код возврата ноль', $code, 0);
Check::same('предупреждение напечатано', str_contains($stderr, 'стоит не ниже'), true);
Check::same('дошли до конца файла', str_contains($notes, 'строка 1.1.1'), true);
Check::same('разделителя нет', str_contains($notes, 'отдельными релизами'), false);

Check::group('предыдущая версия берётся из тегов');

$git = static function (string $command) use ($sandbox): int {
    $code = 0;
    $ignored = [];
    exec(
        'cd '.escapeshellarg($sandbox).' && git -c user.email=t@t -c user.name=t '
        .$command.' >/dev/null 2>&1',
        $ignored,
        $code
    );

    return $code;
};

$prepared = $git('init -q -b main');
$prepared += $git('commit -q --allow-empty -m init');

// v2.0.0 равен текущей версии: порог обязан быть СТРОГИМ. На пуше тега
// release.yml работает именно так — тег уже стоит в чекауте.
// v3.0.0 старше текущей. vX.Y и release-1 — не версии.
foreach (['v1.1.1', 'v1.5.0', 'v1.10.0', 'v2.0.0', 'v3.0.0', 'v2.0', 'release-1'] as $tag) {
    $prepared += $git('tag '.escapeshellarg($tag));
}

// Тег на невлитой ветке: так выглядит ветка поддержки и тег, поставленный
// руками мимо main. v1.11.0 старше 1.10.0 и младше 2.0.0 — не отсеки его
// достижимостью, и предыдущим выпуском стала бы версия, которой в ЭТОМ
// CHANGELOG нет.
$prepared += $git('checkout -q -b support');
$prepared += $git('commit -q --allow-empty -m support');
$prepared += $git('tag v1.11.0');
$prepared += $git('checkout -q main');

Check::same('песочница с тегами готова', $prepared, 0);

[$notes, $stderr, $code] = $run('--notes');

Check::same('код возврата ноль', $code, 0);
Check::same('текущая версия на месте', str_contains($notes, 'строка 2.0.0'), true);

// Предыдущий выпуск — 1.10.0: самый старший тег СТРОГО МЛАДШЕ 2.0.0 и
// достижимый из HEAD.
Check::same('отсчёт от 1.10.0', str_contains($stderr, 'Предыдущий выпуск: 1.10.0'), true);
Check::same('секция 1.10.0 не попала', str_contains($notes, 'строка 1.10.0'), false);
Check::same('до конца файла не дошли', str_contains($notes, 'строка 1.1.1'), false);

Check::group('разбор аргументов');

[, , $code] = $run('--version');
Check::same('--version отрабатывает', $code, 0);

[, , $code] = $run('--version лишнее');
Check::same('--version с аргументом — отказ', $code, 2);

[, , $code] = $run('--notes 1.5.0 лишнее');
Check::same('--notes с двумя аргументами — отказ', $code, 2);

[, , $code] = $run('--чушь');
Check::same('неизвестный режим — отказ', $code, 2);

Check::group('CHANGELOG.md не читается');

rename($sandbox.'/CHANGELOG.md', $sandbox.'/CHANGELOG.hidden');
[$notes, $stderr, $code] = $run('--notes 1.5.0');
rename($sandbox.'/CHANGELOG.hidden', $sandbox.'/CHANGELOG.md');

// Ненулевой код: пустые примечания workflow подменит заглушкой, а пропавший
// CHANGELOG — это поломка, а не оформление.
Check::same('код возврата ненулевой', $code !== 0, true);
Check::same('сказано, чего не хватает', str_contains($stderr, 'CHANGELOG.md'), true);

Check::group('секции текущей версии в CHANGELOG нет');

file_put_contents($sandbox.'/CHANGELOG.md', "# change log\n\n## 1.5.0 — 2026-01-04\n* строка 1.5.0\n");

[$notes, , $code] = $run('--notes 1.5.0');

// Пусто и без ошибки: release.yml на пустых примечаниях подставляет свой
// текст. Ненулевой код уронил бы выпуск из-за оформления CHANGELOG, а саму
// пропажу секции ловит проверка check_changelog_section в ./build.sh --check.
Check::same('код возврата ноль', $code, 0);
Check::same('вывод пуст', trim($notes), '');

file_put_contents($sandbox.'/CHANGELOG.md', $changelog);

Check::finish();
