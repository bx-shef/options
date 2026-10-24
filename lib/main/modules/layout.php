<?php

declare(strict_types=1);

namespace Shef\Options\Main\Modules;

/**
 * Раскладка модулей линейки без портала (issue #55): какие файлы установщики кладут в корень сайта
 * вне своего каталога — копии install/ и заглушки страниц.
 *
 * Зачем: портал, который выкладывается из git, держит эти файлы в git ровно такими, какими их
 * запишет установщик, иначе установка оставит «грязное» дерево и следующий деплой остановится.
 * Получать их установкой на стенде не нужно — модуль описывает раскладку сам, в install/layout.php:
 *
 *   return static fn (string $documentRoot, string $moduleDir): array => [
 *       'copy' => [['from' => '/install/css', 'to' => '/bitrix/css']],
 *       'files' => ['/bitrix/admin/x.php' => "<?php require(...);\n"],
 *   ];
 *
 * Функция чистая: без ядра, базы и автозагрузки модуля (классы — явным require_once). Копирование —
 * как CopyDirFiles установщика: содержимое from в to, с подкаталогами, мимо ссылок.
 * Класс без ядра: его подключает tools/layout.php в репозитории портала.
 */
final class Layout
{
    public const FILE = '/install/layout.php';

    /**
     * Раскладка модулей: путь от корня сайта => содержимое.
     *
     * @param array<string, string> $modules ID => каталог модуля
     * @return array{files: array<string, string>, missing: list<string>, errors: list<string>}
     */
    public static function build(string $documentRoot, array $modules): array
    {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $files = [];
        $missing = [];
        $errors = [];
        foreach ($modules as $id => $dir) {
            $dir = rtrim(str_replace('\\', '/', $dir), '/');
            if (!is_file($dir.self::FILE)) {
                $missing[] = $id;
                continue;
            }
            $make = include $dir.self::FILE;
            $layout = is_callable($make) ? $make($documentRoot, $dir) : null;
            if (!is_array($layout)) {
                $errors[] = $id.': '.self::FILE.' не вернул функцию раскладки';
                continue;
            }
            foreach ($layout['copy'] ?? [] as $copy) {
                foreach (static::walk($dir.$copy['from']) as $rel => $content) {
                    $files[rtrim($copy['to'], '/').$rel] = $content;
                }
            }
            foreach ($layout['files'] ?? [] as $path => $content) {
                $files[(string)$path] = (string)$content;
            }
        }
        ksort($files);

        return ['files' => $files, 'missing' => $missing, 'errors' => $errors];
    }

    /**
     * Чем лежащее отличается от раскладки: «+» — файла нет, «~» — другой.
     *
     * @param array<string, string> $files
     * @return list<string>
     */
    public static function diff(string $documentRoot, array $files): array
    {
        $documentRoot = rtrim($documentRoot, '/');
        $lines = [];
        foreach ($files as $path => $content) {
            $target = $documentRoot.$path;
            if (!is_file($target)) {
                $lines[] = '+ '.$path;
            } elseif (file_get_contents($target) !== $content) {
                $lines[] = '~ '.$path;
            }
        }

        return $lines;
    }

    /**
     * Записать раскладку (только отличающиеся файлы).
     *
     * @param array<string, string> $files
     * @return list<string> записанные пути
     */
    public static function write(string $documentRoot, array $files): array
    {
        $documentRoot = rtrim($documentRoot, '/');
        $written = [];
        foreach ($files as $path => $content) {
            $target = $documentRoot.$path;
            if (is_file($target) && file_get_contents($target) === $content) {
                continue;
            }
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            file_put_contents($target, $content);
            $written[] = $path;
        }

        return $written;
    }

    /**
     * Файлы каталога с подкаталогами: путь от каталога («/a/b.css») => содержимое; ссылки — мимо.
     *
     * @return array<string, string>
     */
    public static function walk(string $dir): array
    {
        if (!is_dir($dir) || is_link($dir)) {
            return [];
        }
        $found = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || is_link($dir.'/'.$name)) {
                continue;
            }
            if (is_dir($dir.'/'.$name)) {
                foreach (static::walk($dir.'/'.$name) as $rel => $content) {
                    $found['/'.$name.$rel] = $content;
                }
            } elseif (is_file($dir.'/'.$name)) {
                $found['/'.$name] = (string)file_get_contents($dir.'/'.$name);
            }
        }
        ksort($found);

        return $found;
    }

    /**
     * Строки git status --porcelain, которые касаются раскладки: файл модуля в дереве изменён или
     * не внесён в git. Пути porcelain — от корня репозитория, $prefix — корень сайта в нём («www/»).
     *
     * @param list<string> $porcelain
     * @return list<string>
     */
    public static function gitDrift(array $porcelain, string $prefix = ''): array
    {
        $lines = [];
        foreach ($porcelain as $row) {
            if (strlen($row) < 4) {
                continue;
            }
            $path = substr($row, 3);
            if ($prefix !== '' && !str_starts_with($path, $prefix)) {
                continue;
            }
            $lines[] = trim(substr($row, 0, 2)).' '.$path;
        }

        return $lines;
    }
}
