<?php

declare(strict_types=1);

/**
 * shef:modules:sync — что ставить, что обновлять и в каком порядке (issue #53).
 *
 * Planner — без ядра. Sync — на временном каталоге «портала» и заглушке ModuleManager: находит
 * модули в bitrix/modules и local/modules, читает версию и зависимости, обновление зовёт
 * InstallFiles() и installModuleData() установщика, синхронизированная версия — в настройках.
 */

namespace Bitrix\Main
{
    /** Подключение модуля: запоминаем, кого и до каких вызовов установщика. */
    class Loader
    {
        /** @var list<string> */
        public static array $included = [];

        public static function includeModule(string $moduleName): bool
        {
            static::$included[] = $moduleName;
            if (class_exists('shef_upd', false)) {
                \shef_upd::$calls[] = 'include:'.$moduleName;
            }
            return true;
        }
    }

    class ModuleManager
    {
        /** @var array<string, true> */
        public static array $installed = [];

        public static function isModuleInstalled(string $id): bool
        {
            return isset(static::$installed[$id]);
        }
    }
}

namespace {
    $root = dirname(__DIR__);

    require_once $root.'/tests/stub/bitrix.php';
    require_once $root.'/tests/assert.php';
    require_once $root.'/lib/main/modules/planner.php';
    require_once $root.'/lib/main/modules/sync.php';

    use Bitrix\Main\Config\Option;
    use Bitrix\Main\ModuleManager;
    use Shef\Options\Main\Modules\Planner;
    use Shef\Options\Main\Modules\Sync;

    $m = static fn (string $id, array $req = [], bool $inst = false, string $synced = '', string $ver = '1.0.0'): array
        => ['id' => $id, 'version' => $ver, 'requires' => $req, 'installed' => $inst, 'synced' => $synced];
    $actions = static fn (array $plan): array => array_map(static fn (array $s): string => $s['id'].':'.$s['action'], $plan);

    Check::group('Planner: порядок и действия');

    $line = [
        $m('shef.toolsai', ['shef.options', 'shef.problems', 'ai']),
        $m('shef.currency', ['shef.options', 'shef.problems', 'shef.insync', 'currency']),
        $m('shef.insync', ['shef.options', 'shef.problems']),
        $m('shef.problems', ['shef.options']),
        $m('shef.options', [], true, '3.0.16', '3.2.0'),
    ];
    Check::same('зависимости раньше зависимых, установленный с новыми файлами — обновить', $actions(Planner::plan($line, ['ai', 'currency'])), [
        'shef.options:update', 'shef.problems:install', 'shef.toolsai:install', 'shef.insync:install', 'shef.currency:install',
    ]);
    Check::same('порядок не зависит от порядка на диске', $actions(Planner::plan(array_reverse($line), ['ai', 'currency'])), $actions(Planner::plan($line, ['ai', 'currency'])));
    Check::same('нет модуля ядра — заблокирован, с причиной', array_values(array_filter(Planner::plan($line, ['ai']), static fn (array $s): bool => $s['id'] === 'shef.currency'))[0]['reason'], 'нет: currency');
    Check::same('зависимость заблокирована — зависимый тоже', $actions(Planner::plan([$m('a.b', ['x.y']), $m('c.d', ['a.b'])])), ['a.b:blocked', 'c.d:blocked']);
    Check::same('актуальный — пропустить; неизвестная синхронизация — обновить', $actions(Planner::plan([$m('a.b', [], true, '1.0.0'), $m('c.d', [], true, '')])), ['a.b:skip', 'c.d:update']);
    Check::same('установленный не блокируется отсутствующей зависимостью (его уже ставили)', $actions(Planner::plan([$m('a.b', ['x.y'], true, '0.9')])), ['a.b:update']);
    Check::same('цикл — заблокирован', $actions(Planner::plan([$m('a.b', ['c.d']), $m('c.d', ['a.b'])])), ['a.b:blocked', 'c.d:blocked']);
    Check::same('--module: только он и его зависимости, по порядку', $actions(Planner::plan($line, ['ai', 'currency'], ['shef.insync'])), ['shef.options:update', 'shef.problems:install', 'shef.insync:install']);

    Check::group('Planner: версии модулей портала из установщика (NEED_MODULES_BY_VERSION)');

    $n = static fn (string $id, array $needs, bool $inst = false): array
        => ['id' => $id, 'version' => '1.76.0', 'requires' => ['shef.options'], 'installed' => $inst, 'synced' => '', 'needs' => $needs];
    $opt = $m('shef.options', [], true, '3.8.0', '3.8.0');
    $needs = ['shef.options' => '3.0.0', 'ai' => '26.1000.0', 'crm' => '26.800.0'];
    $plan = Planner::plan([$opt, $n('shef.toolsai', $needs)], ['ai', 'crm'], null, ['ai' => '26.1000.0', 'crm' => '26.700.0']);
    Check::same('crm ниже — заблокирован, причина с нужной и установленной версией', [$plan[1]['action'], $plan[1]['reason']], [Planner::BLOCKED, 'нужно: crm 26.800.0+ (установлена 26.700.0)']);
    $plan = Planner::plan([$opt, $n('shef.toolsai', $needs)], ['crm'], null, ['crm' => '26.800.0']);
    Check::same('ai не установлен — заблокирован', $plan[1]['reason'], 'нужно: ai 26.1000.0+ (не установлен)');
    $plan = Planner::plan([$opt, $n('shef.toolsai', $needs, true)], ['ai', 'crm'], null, ['ai' => '26.900.0', 'crm' => '26.700.0']);
    Check::same('установленный на старом ядре — тоже заблокирован (обновление не идёт)', [$plan[1]['action'], $plan[1]['reason']], [Planner::BLOCKED, 'нужно: ai 26.1000.0+ (установлена 26.900.0), crm 26.800.0+ (установлена 26.700.0)']);
    $plan = Planner::plan([$opt, $n('shef.toolsai', $needs), array_merge($n('shef.toolsaiassistant', ['shef.toolsai' => '1.66.0']), ['requires' => ['shef.toolsai']])], ['ai', 'crm'], null, ['ai' => '26.1000.0', 'crm' => '26.700.0']);
    Check::same('зависимый от заблокированного — тоже заблокирован', $actions($plan), ['shef.options:skip', 'shef.toolsai:blocked', 'shef.toolsaiassistant:blocked']);
    $plan = Planner::plan([$opt, $n('shef.toolsai', $needs)], ['ai', 'crm'], null, ['ai' => '26.1000.0', 'crm' => '26.800.0']);
    Check::same('на границе — ставится', $actions($plan), ['shef.options:skip', 'shef.toolsai:install']);
    $plan = Planner::plan([$m('shef.options', [], true, '2.9.0', '2.9.0'), $n('shef.toolsai', ['shef.options' => '3.0.0'])]);
    Check::same('модуль линейки — по версии своих файлов', $plan[1]['reason'], 'нужно: shef.options 3.0.0+ (установлена 2.9.0)');
    $plan = Planner::plan([$opt, $n('shef.toolsai', ['ai' => ''])], [], null, []);
    Check::same('NEED_MODULES без версии: нет модуля — заблокирован', $plan[1]['reason'], 'нужно: ai (не установлен)');
    Check::same('версии портала: нужные по needs, вне списка; не установлен — нет ключа', Sync::outsideVersions([['id' => 'shef.a', 'needs' => ['ai' => '', 'crm' => '1', 'shef.b' => '1']], ['id' => 'shef.b', 'needs' => ['crm' => '2', 'im' => '', 'note' => '1']]], static fn (string $id): string|false => ['ai' => '26.1000.0', 'crm' => '26.700.0', 'note' => '', 'shef.b' => '9'][$id] ?? false), ['ai' => '26.1000.0', 'crm' => '26.700.0', 'note' => '']);
    Check::same('установлен, версия не читается — заблокирован с причиной', Planner::plan([$opt, $n('shef.toolsai', ['crm' => '26.800.0'])], [], null, ['crm' => ''])[1]['reason'], 'нужно: crm 26.800.0+ (версия не читается)');
    $synced = $n('shef.toolsai', $needs, true);
    $synced['synced'] = '1.76.0';
    $plan = Planner::plan([$opt, $synced], ['ai', 'crm'], null, ['ai' => '26.1000.0', 'crm' => '26.700.0']);
    Check::same('установленный и синхронизированный на старом ядре — skip с причиной (команда не падает)', [$plan[1]['action'], $plan[1]['reason']], [Planner::SKIP, 'актуален; нужно: crm 26.800.0+ (установлена 26.700.0)']);
    $plan = Planner::plan([$opt, $n('shef.toolsai', ['ai' => '', 'crm' => '26.800.0'], true)], ['crm'], null, ['crm' => '26.800.0']);
    Check::same('установленный: NEED_MODULES без версии не блокирует (его уже ставили)', $actions($plan), ['shef.options:skip', 'shef.toolsai:update']);
    $plan = Planner::plan([$opt, $m('shef.problems', ['x.y']), $n('shef.toolsai', ['shef.problems' => '2.0.0'])]);
    Check::same('needs на заблокированный модуль списка — «сама не встанет»', $plan[2]['reason'], 'нужно: shef.problems (сама не встанет)');

    Check::group('Sync::plan: склейка команды (ядро — через функции)');

    $states2 = [$opt, $n('shef.toolsai', $needs)];
    $core = ['shef.options' => '3.8.0', 'ai' => '26.1500.0', 'crm' => '26.700.0'];
    $isInst = static fn (string $id): bool => isset($core[$id]);
    $getVer = static fn (string $id): string => $core[$id] ?? '';
    Check::same('crm 26.700 на портале — заблокирован с версией', Sync::plan($states2, null, $isInst, $getVer)[1]['reason'], 'нужно: crm 26.800.0+ (установлена 26.700.0)');
    $core['crm'] = '26.1750.0';
    $isInst = static fn (string $id): bool => isset($core[$id]);
    $getVer = static fn (string $id): string => $core[$id] ?? '';
    Check::same('стенд ai 26.1500 / crm 26.1750 — ставится', $actions(Sync::plan($states2, null, $isInst, $getVer)), ['shef.options:skip', 'shef.toolsai:install']);

    Check::group('Sync: модули на диске');

    $portal = sys_get_temp_dir().'/shef-sync-'.getmypid();
    $make = static function (string $base, string $id, string $ver, array $req = [], string $installer = '') use ($portal): void {
        $dir = "$portal/$base/$id";
        @mkdir("$dir/install", 0777, true);
        file_put_contents("$dir/install/version.php", "<?php\n\$arModuleVersion = ['VERSION' => '$ver', 'VERSION_DATE' => '2026-10-24 12:00:00'];\n");
        file_put_contents("$dir/install/index.php", $installer ?: "<?php\n");
        file_put_contents("$dir/.settings.php", "<?php\nreturn ['requireModules' => ['value' => ".var_export($req, true).", 'readonly' => true]];\n");
    };
    $make('bitrix/modules', 'shef.options', '3.2.0');
    $make('bitrix/modules', 'shef.problems', '2.0.1', ['shef.options']);
    $make('bitrix/modules', 'shef.currency', '2.0.0', ['shef.options', 'currency']);
    $make('local/modules', 'shef.currency', '2.1.0', ['shef.options', 'currency']);
    $make('bitrix/modules', 'bitrix.other', '1.0.0');
    @mkdir("$portal/bitrix/modules/shef.broken", 0777, true);

    $sync = new Sync($portal);
    $found = $sync->discover();
    Check::same('только shef.* с установщиком и версией; local главнее bitrix', array_map(static fn (string $d): string => substr($d, strlen($portal)), $found), [
        'shef.currency' => '/local/modules/shef.currency', 'shef.options' => '/bitrix/modules/shef.options', 'shef.problems' => '/bitrix/modules/shef.problems',
    ]);
    ModuleManager::$installed = ['shef.options' => true, 'currency' => true];
    Option::set('shef.options', 'SYS_sync_shef.options', '3.2.0');
    $states = $sync->states($found);
    Check::same('состояние: версия файлов, зависимости, установлен, синхронизирован', $states[0], ['id' => 'shef.currency', 'version' => '2.1.0', 'requires' => ['shef.options', 'currency'], 'installed' => false, 'synced' => '', 'needs' => []]);
    Check::same('модули ядра, нужные линейке, — установлены ли', Sync::installedOutside($states, [ModuleManager::class, 'isModuleInstalled']), ['shef.options', 'currency']);
    Check::same('план по диску', $actions(Planner::plan($states, Sync::installedOutside($states, [ModuleManager::class, 'isModuleInstalled']))), ['shef.options:skip', 'shef.problems:install', 'shef.currency:install']);

    $make('bitrix/modules', 'shef.needs', '1.0.0', [], "<?php\nclass shef_needs { public \$NEED_MODULES = ['ai', 'crm']; public \$NEED_MODULES_BY_VERSION = ['shef.options' => '3.0.0', 'crm' => '26.800.0']; public function __construct() { throw new \RuntimeException('конструктор не зовём'); } }\n");
    Check::same('требования — из свойств установщика, без конструктора', Sync::needs('shef.needs', "$portal/bitrix/modules/shef.needs"), ['ai' => '', 'crm' => '26.800.0', 'shef.options' => '3.0.0']);
    Check::same('установщик без класса — требований нет', Sync::needs('shef.problems', "$portal/bitrix/modules/shef.problems"), []);

    Check::group('Sync: обновление установщиком');

    $make('bitrix/modules', 'shef.upd', '1.1.0', [], "<?php\nclass shef_upd { public static array \$calls = []; public function InstallFiles(): bool { static::\$calls[] = 'files'; return true; } public function installModuleData(): bool { static::\$calls[] = 'data'; return true; } public function InstallDB(): bool { static::\$calls[] = 'db'; return true; } }\n");
    $make('bitrix/modules', 'shef.nofiles', '1.0.0', [], "<?php\nclass shef_nofiles {}\n");
    require_once "$portal/bitrix/modules/shef.upd/install/index.php";
    Check::same('модуль подключён до установщика; файлы и данные — да, InstallDB — нет (регистрацию не трогаем)', [Sync::update('shef.upd', "$portal/bitrix/modules/shef.upd"), \shef_upd::$calls], [['файлы', 'данные'], ['include:shef.upd', 'files', 'data']]);
    Check::same('у установщика нечего звать — пусто, без ошибки', Sync::update('shef.nofiles', "$portal/bitrix/modules/shef.nofiles"), []);
    Sync::markSynced('shef.upd', '1.1.0');
    Check::same('синхронизированная версия — в настройках shef.options', Option::get('shef.options', 'SYS_sync_shef.upd', ''), '1.1.0');

    Check::same('чужой вендор не находится, отбор по одному ID — только он', [array_keys($sync->discover()) === array_keys(array_filter($sync->discover(), static fn (string $d, string $id): bool => str_starts_with($id, 'shef.'), ARRAY_FILTER_USE_BOTH)), array_keys((new Sync($portal, 'shef.problems'))->discover())], [true, ['shef.problems']]);

    Check::group('Sync::run: выполнение плана');

    $marked = [];
    $mark = static function (string $id, string $v) use (&$marked): void {
        $marked[] = $id.'@'.$v;
    };
    $plan = [
        ['id' => 'a.a', 'action' => Planner::INSTALL, 'reason' => '', 'version' => '1'],
        ['id' => 'b.b', 'action' => Planner::INSTALL, 'reason' => '', 'version' => '2'],
        ['id' => 'c.c', 'action' => Planner::UPDATE, 'reason' => '', 'version' => '3'],
        ['id' => 'd.d', 'action' => Planner::UPDATE, 'reason' => '', 'version' => '4'],
        ['id' => 'e.e', 'action' => Planner::SKIP, 'reason' => '', 'version' => '5'],
        ['id' => 'f.f', 'action' => Planner::BLOCKED, 'reason' => 'нет: x', 'version' => '6'],
    ];
    $r = Sync::run(
        $plan,
        static fn (string $id): array => $id === 'a.a' ? [true, 'установлен'] : [false, 'нет модуля currency'],
        static fn (string $id): array => $id === 'c.c' ? [true, 'файлы'] : throw new \RuntimeException('Class not found'),
        $mark
    );
    Check::same('версия — только у успешных', $marked, ['a.a@1', 'c.c@3']);
    Check::same('сбои: отказ установки, исключение обновления, блокировка', $r['failed'], 3);
    Check::same('текст отказа и исключения — в отчёте', [str_contains($r['lines'][1], 'ОШИБКА: нет модуля currency'), str_contains($r['lines'][3], 'ОШИБКА: Class not found'), str_ends_with($r['lines'][4], 'skip     ')], [true, true, true]);

    exec('rm -rf '.escapeshellarg($portal));

    Check::finish();
}
