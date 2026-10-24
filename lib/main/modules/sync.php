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

    public function __construct(
        private readonly string $documentRoot,
        private readonly string $prefix = 'shef.'
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
                    str_starts_with($name, $this->prefix)
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
     * Состояние для Planner.
     *
     * @param array<string, string> $found
     * @return list<array{id: string, version: string, requires: list<string>, installed: bool, synced: string}>
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
