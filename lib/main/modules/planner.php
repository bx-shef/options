<?php

declare(strict_types=1);

namespace Shef\Options\Main\Modules;

/**
 * План синхронизации модулей линейки (команда shef:modules:sync): что ставить, что обновлять,
 * в каком порядке. Чистый класс — без ядра: состояние ему дают, решения он возвращает.
 *
 * Состояние модуля — массив:
 *   id        — ID модуля (shef.toolsai);
 *   version   — версия файлов (install/version.php);
 *   requires  — модули, без которых он не встанет (requireModules его .settings.php);
 *   installed — зарегистрирован ли на портале;
 *   synced    — версия, на которой команда его последний раз ставила или обновляла ('' — не знаем);
 *   needs     — необязательно: модули портала с наименьшей версией из установщика (NEED_MODULES —
 *               версия '', NEED_MODULES_BY_VERSION — например '26.800.0').
 *
 * Действия: install — не установлен; update — установлен, версия файлов не та, что при последней
 * синхронизации (в том числе неизвестна); skip — установлен и уже синхронизирован; blocked — нужна
 * зависимость, которой нет ни в файлах, ни на портале, или зависимость сама не встанет; а также
 * модуль, которому не хватает версии модуля портала по needs. Причина называет модуль, нужную и
 * установленную версию. Установленный модуль сверяется только по версиям (NEED_MODULES_BY_VERSION):
 * обновление файлов на старом ядре — blocked, уже синхронизированный — skip с той же причиной
 * (команда не падает на каждом запуске, пока ядро не обновят).
 */
final class Planner
{
    public const INSTALL = 'install';
    public const UPDATE = 'update';
    public const SKIP = 'skip';
    public const BLOCKED = 'blocked';

    /**
     * @param list<array{id: string, version: string, requires: list<string>, installed: bool, synced: string}> $modules
     * @param list<string> $installedOutside модули портала вне списка (ядро: main, crm, currency …)
     * @param list<string>|null $only только эти (с их зависимостями из списка — тоже по порядку)
     * @param array<string, string> $versions версии модулей портала вне списка (не установлен — нет ключа)
     * @return list<array{id: string, action: string, reason: string, version: string}>
     */
    public static function plan(array $modules, array $installedOutside = [], ?array $only = null, array $versions = []): array
    {
        $byId = [];
        foreach ($modules as $m) {
            $byId[$m['id']] = $m;
        }

        $order = static::order($byId);
        if ($only !== null) {
            $want = static::withDependencies($only, $byId);
            $order = array_values(array_filter($order, static fn (string $id): bool => isset($want[$id])));
        }

        $plan = [];
        $blocked = [];
        foreach ($order as $id) {
            $m = $byId[$id];
            $missing = [];
            foreach ($m['requires'] as $dep) {
                if (isset($blocked[$dep])) {
                    $missing[] = $dep.' (сама не встанет)';
                } elseif (!isset($byId[$dep]) && !in_array($dep, $installedOutside, true)) {
                    $missing[] = $dep;
                }
            }
            if (static::inCycle($id, $byId)) {
                $missing[] = 'циклическая зависимость';
            }

            $needs = $m['needs'] ?? [];
            if ($m['installed']) {
                $needs = array_filter($needs, static fn (string $min): bool => $min !== '');
            }
            $short = static::shortVersions($needs, $byId, $versions, $blocked);
            $absent = $missing !== [] && !$m['installed'];

            if ($m['installed'] && !$absent && $short !== [] && $m['synced'] === $m['version']) {
                $plan[] = ['id' => $id, 'action' => self::SKIP, 'reason' => 'актуален; нужно: '.implode(', ', $short), 'version' => $m['version']];
            } elseif ($absent || $short !== []) {
                $blocked[$id] = true;
                $why = array_merge($absent ? ['нет: '.implode(', ', $missing)] : [], $short !== [] ? ['нужно: '.implode(', ', $short)] : []);
                $plan[] = ['id' => $id, 'action' => self::BLOCKED, 'reason' => implode('; ', $why), 'version' => $m['version']];
            } elseif (!$m['installed']) {
                $plan[] = ['id' => $id, 'action' => self::INSTALL, 'reason' => 'не установлен', 'version' => $m['version']];
            } elseif ($m['synced'] !== $m['version']) {
                $plan[] = ['id' => $id, 'action' => self::UPDATE, 'reason' => $m['synced'] === '' ? 'версия синхронизации неизвестна' : 'файлы '.$m['synced'].' → '.$m['version'], 'version' => $m['version']];
            } else {
                $plan[] = ['id' => $id, 'action' => self::SKIP, 'reason' => 'актуален', 'version' => $m['version']];
            }
        }

        return $plan;
    }

    /**
     * Чего не хватает по версиям: «crm 26.800.0+ (установлена 26.700.0)», «ai (не установлен)».
     * Модуль из списка сверяется по версии своих файлов (его поставят или обновят раньше), если сам
     * не заблокирован. Версия модуля портала '' — установлен, но версия не читается.
     *
     * @param array<string, string> $needs
     * @param array<string, array{version: string}> $byId
     * @param array<string, string> $versions
     * @param array<string, true> $blocked
     * @return list<string>
     */
    public static function shortVersions(array $needs, array $byId, array $versions, array $blocked = []): array
    {
        $short = [];
        foreach ($needs as $dep => $min) {
            $have = isset($byId[$dep]) ? $byId[$dep]['version'] : ($versions[$dep] ?? null);
            if (isset($blocked[$dep])) {
                $short[] = $dep.' (сама не встанет)';
            } elseif ($have === null) {
                $short[] = $dep.($min !== '' ? ' '.$min.'+' : '').' (не установлен)';
            } elseif ($min !== '' && $have === '') {
                $short[] = $dep.' '.$min.'+ (версия не читается)';
            } elseif ($min !== '' && version_compare($have, $min) < 0) {
                $short[] = $dep.' '.$min.'+ (установлена '.$have.')';
            }
        }

        return $short;
    }

    /**
     * Порядок: зависимости раньше зависимых, при равенстве — по ID (результат не зависит от того,
     * в каком порядке модули нашлись на диске). Модули в цикле — в конце, по ID.
     *
     * @param array<string, array{requires: list<string>}> $byId
     * @return list<string>
     */
    public static function order(array $byId): array
    {
        $ids = array_keys($byId);
        sort($ids);
        $done = [];
        $result = [];
        $progress = true;
        while ($progress) {
            $progress = false;
            foreach ($ids as $id) {
                if (isset($done[$id])) {
                    continue;
                }
                $ready = true;
                foreach ($byId[$id]['requires'] as $dep) {
                    if (isset($byId[$dep]) && !isset($done[$dep])) {
                        $ready = false;
                        break;
                    }
                }
                if ($ready) {
                    $done[$id] = true;
                    $result[] = $id;
                    $progress = true;
                }
            }
        }
        foreach ($ids as $id) {
            if (!isset($done[$id])) {
                $result[] = $id;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $only
     * @param array<string, array{requires: list<string>}> $byId
     * @return array<string, true>
     */
    private static function withDependencies(array $only, array $byId): array
    {
        $want = [];
        $stack = $only;
        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($want[$id]) || !isset($byId[$id])) {
                continue;
            }
            $want[$id] = true;
            foreach ($byId[$id]['requires'] as $dep) {
                $stack[] = $dep;
            }
        }

        return $want;
    }

    /** @param array<string, array{requires: list<string>}> $byId */
    private static function inCycle(string $id, array $byId): bool
    {
        $seen = [];
        $stack = $byId[$id]['requires'];
        while ($stack !== []) {
            $dep = array_pop($stack);
            if ($dep === $id) {
                return true;
            }
            if (isset($seen[$dep]) || !isset($byId[$dep])) {
                continue;
            }
            $seen[$dep] = true;
            array_push($stack, ...$byId[$dep]['requires']);
        }

        return false;
    }
}
