<?php

declare(strict_types=1);

namespace Shef\Options\Main\Modules;

use Bitrix\Main\Config\Option;
use Bitrix\Main\ModuleManager;

/**
 * Синхронизация модулей линейки после выкладки файлов (git, Composer) — то, что делает команда
 * shef:modules:sync. Находит модули на диске, собирает состояние для Planner и выполняет план:
 * установку — в отдельном процессе (канонический установщик при отказе показывает форму и делает
 * die()), обновление — здесь: InstallFiles() и installModuleData(), если он есть у модуля.
 *
 * Замена файлов установщик не запускает — новые файлы для /bitrix/css, /bitrix/js, компоненты,
 * повторяемые данные в БД портал без этой команды не получил бы.
 */
class Sync
{
    /** Версия, на которой команда последний раз синхронизировала модуль: SYS_sync_<id> в настройках shef.options. */
    public const SYNCED_PREFIX = 'SYS_sync_';
    public const OWNER = 'shef.options';

    /** Только модули линейки: чужим модулям команда установщики не запускает. */
    public const PREFIX = 'shef.';

    public function __construct(
        private readonly string $documentRoot,
        private readonly string $only = ''
    ) {
    }

    /**
     * Модули на диске: bitrix/modules и local/modules (local главнее, как у ядра).
     *
     * @return array<string, string> ID => каталог модуля
     */
    public function discover(): array
    {
        $found = [];
        foreach (['/bitrix/modules', '/local/modules'] as $base) {
            $dir = rtrim($this->documentRoot, '/').$base;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $name) {
                if (
                    str_starts_with($name, self::PREFIX)
                    && ($this->only === '' || $name === $this->only)
                    && preg_match('/^[a-z0-9]+\.[a-z0-9]+$/', $name)
                    && is_file($dir.'/'.$name.'/install/index.php')
                    && is_file($dir.'/'.$name.'/install/version.php')
                ) {
                    $found[$name] = $dir.'/'.$name;
                }
            }
        }
        ksort($found);

        return $found;
    }

    /** Версия файлов — install/version.php модуля. */
    public static function fileVersion(string $moduleDir): string
    {
        $arModuleVersion = [];
        include $moduleDir.'/install/version.php';

        return (string)($arModuleVersion['VERSION'] ?? '');
    }

    /**
     * Зависимости — requireModules из .settings.php модуля (канон линейки).
     *
     * @return list<string>
     */
    public static function requires(string $moduleDir): array
    {
        $file = $moduleDir.'/.settings.php';
        if (!is_file($file)) {
            return [];
        }
        $settings = include $file;
        $list = $settings['requireModules']['value'] ?? [];

        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    /**
     * Требования установщика к модулям портала: NEED_MODULES (версия '') и NEED_MODULES_BY_VERSION —
     * значения свойств класса по умолчанию, без конструктора. Нет класса или свойств — пусто: такой
     * модуль проверяет только его установщик. Файл установщика подключается в процессе команды (как
     * при обновлении): канонический установщик — только объявление класса; фатальная ошибка в нём
     * остановит и план.
     *
     * @return array<string, string> модуль => наименьшая версия
     */
    public static function needs(string $id, string $moduleDir): array
    {
        try {
            $class = str_replace('.', '_', $id);
            if (!class_exists($class, false)) {
                require_once $moduleDir.'/install/index.php';
            }
            if (!class_exists($class, false)) {
                return [];
            }
            $props = (new \ReflectionClass($class))->getDefaultProperties();
        } catch (\Throwable) {
            return [];
        }
        $needs = [];
        foreach (is_array($props['NEED_MODULES'] ?? null) ? $props['NEED_MODULES'] : [] as $dep) {
            if (is_string($dep) && $dep !== '') {
                $needs[$dep] = '';
            }
        }
        foreach (is_array($props['NEED_MODULES_BY_VERSION'] ?? null) ? $props['NEED_MODULES_BY_VERSION'] : [] as $dep => $ver) {
            if (is_string($dep) && $dep !== '' && is_string($ver)) {
                $needs[$dep] = $ver;
            }
        }

        return $needs;
    }

    /**
     * Версии модулей портала вне списка, которые нужны линейке по needs. Не установлен — нет ключа,
     * установлен без читаемой версии — ''.
     *
     * @param list<array{id: string, needs?: array<string, string>}> $states
     * @param callable(string): (string|false) $getVersion false — не установлен
     * @return array<string, string>
     */
    public static function outsideVersions(array $states, callable $getVersion): array
    {
        $own = array_column($states, 'id', 'id');
        $versions = [];
        foreach ($states as $s) {
            foreach (array_keys($s['needs'] ?? []) as $dep) {
                $dep = (string)$dep;
                if (!isset($own[$dep]) && !array_key_exists($dep, $versions)) {
                    $v = $getVersion($dep);
                    if ($v !== false) {
                        $versions[$dep] = (string)$v;
                    }
                }
            }
        }

        return $versions;
    }

    /**
     * План команды по состоянию: зависимости ядра (установлены ли) и версии модулей портала.
     *
     * @param list<array{id: string, version: string, requires: list<string>, installed: bool, synced: string, needs?: array<string, string>}> $states
     * @param list<string>|null $only
     * @param callable(string): bool $isInstalled
     * @param callable(string): (string|false) $getVersion
     * @return list<array{id: string, action: string, reason: string, version: string}>
     */
    public static function plan(array $states, ?array $only, callable $isInstalled, callable $getVersion): array
    {
        $versions = static::outsideVersions($states, static fn (string $id): string|false => $isInstalled($id) ? $getVersion($id) : false);

        return Planner::plan($states, static::installedOutside($states, $isInstalled), $only, $versions);
    }

    /**
     * Состояние для Planner.
     *
     * @param array<string, string> $found
     * @return list<array{id: string, version: string, requires: list<string>, installed: bool, synced: string, needs: array<string, string>}>
     */
    public function states(array $found): array
    {
        $states = [];
        foreach ($found as $id => $dir) {
            $states[] = [
                'id' => $id,
                'version' => static::fileVersion($dir),
                'requires' => static::requires($dir),
                'installed' => ModuleManager::isModuleInstalled($id),
                'synced' => (string)Option::get(self::OWNER, self::SYNCED_PREFIX.$id, ''),
                'needs' => static::needs($id, $dir),
            ];
        }

        return $states;
    }

    /**
     * Модули портала вне линейки, которые нужны линейке (ядро): установлены ли.
     *
     * @param list<array{requires: list<string>}> $states
     * @return list<string>
     */
    public static function installedOutside(array $states, callable $isInstalled): array
    {
        $deps = [];
        foreach ($states as $s) {
            foreach ($s['requires'] as $dep) {
                $deps[$dep] = true;
            }
        }

        return array_values(array_filter(array_keys($deps), static fn (string $id): bool => (bool)$isInstalled($id)));
    }

    /** Отметить модуль синхронизированным на версии. */
    public static function markSynced(string $id, string $version): void
    {
        Option::set(self::OWNER, self::SYNCED_PREFIX.$id, $version);
    }

    /**
     * Обновить установленный модуль файлами: InstallFiles() и installModuleData() установщика,
     * если они есть. Канонические InstallFiles() копируют поверх (CopyDirFiles с перезаписью),
     * installModuleData() по канону повторяем (shef.insync).
     *
     * @return list<string> что сделано
     */
    public static function update(string $id, string $moduleDir): array
    {
        $done = [];
        // Установленный модуль подключается до установщика: его InstallFiles() зовёт классы модуля
        // (shef.toolsai — Kb\KbFiles, PublicPage), а автозагрузку они получают только так
        \Bitrix\Main\Loader::includeModule($id);
        $module = static::installer($id, $moduleDir);
        if (method_exists($module, 'InstallFiles')) {
            $module->InstallFiles();
            $done[] = 'файлы';
        }
        if (method_exists($module, 'installModuleData')) {
            $module->installModuleData();
            $done[] = 'данные';
        }

        return $done;
    }

    /**
     * Выполнить план: установка и обновление — переданными функциями (в команде — дочерний процесс
     * и update()), версия отмечается только при успехе. blocked — тоже сбой (код выхода).
     *
     * @param list<array{id: string, action: string, reason: string, version: string}> $plan
     * @param callable(string): array{0: bool, 1: string} $install
     * @param callable(string): array{0: bool, 1: string} $update
     * @param callable(string, string): void $mark
     * @return array{failed: int, lines: list<string>}
     */
    public static function run(array $plan, callable $install, callable $update, callable $mark): array
    {
        $failed = 0;
        $lines = [];
        foreach ($plan as $step) {
            $line = sprintf('%-26s %-8s %-8s %s', $step['id'], $step['version'], $step['action'], $step['reason']);
            if ($step['action'] === Planner::SKIP || $step['action'] === Planner::BLOCKED) {
                $failed += $step['action'] === Planner::BLOCKED ? 1 : 0;
                $lines[] = $line;
                continue;
            }
            try {
                [$ok, $details] = $step['action'] === Planner::INSTALL ? $install($step['id']) : $update($step['id']);
            } catch (\Throwable $e) {
                [$ok, $details] = [false, $e->getMessage()];
            }
            if ($ok) {
                $mark($step['id'], $step['version']);
            }
            $failed += $ok ? 0 : 1;
            $lines[] = $line.' — '.($ok ? 'готово' : 'ОШИБКА').($details !== '' ? ': '.$details : '');
        }

        return ['failed' => $failed, 'lines' => $lines];
    }

    /** Объект установщика модуля (класс — ID через подчёркивание, как требует ядро). */
    public static function installer(string $id, string $moduleDir): object
    {
        $class = str_replace('.', '_', $id);
        if (!class_exists($class, false)) {
            require_once $moduleDir.'/install/index.php';
        }

        return new $class();
    }
}
