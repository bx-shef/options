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
 *       'blocks' => ['/.top.menu_ext.php' => ['begin' => '// shef.x: menu begin', 'end' => '// shef.x: menu end',
 *           'note' => '— не править', 'body' => "\$aMenuLinks[] = [...];\n"]],
 *       'remove' => ['/shef/x/old' => null, '/local/old.php' => ['<md5>']],
 *   ];
 *
 * files — файл целиком принадлежит модулю. blocks — модулю принадлежит только блок между метками в
 * общем файле сайта (#68): блок вставляется в текущий файл дерева или заменяет свою прежнюю копию,
 * остальное (и блоки других модулей) — как было. remove — что установщик удаляет сам: null — файл
 * или каталог целиком, список md5 — файл, только если содержимое совпадает (чужой и правленый остаются).
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
     * @return array{files: array<string, string>, remove: array<string, list<string>|null>, missing: list<string>, errors: list<string>}
     */
    public static function build(string $documentRoot, array $modules): array
    {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $files = [];
        $blocks = [];
        $remove = [];
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
            foreach ($layout['blocks'] ?? [] as $path => $block) {
                $blocks[(string)$path][] = [$id, $block];
            }
            foreach ($layout['remove'] ?? [] as $path => $hashes) {
                $remove[(string)$path] = is_array($hashes) ? array_values(array_map('strval', $hashes)) : null;
            }
        }
        foreach ($blocks as $path => $list) {
            if (isset($files[$path])) {
                $errors[] = $path.': и файл целиком, и блок — файл не собран';
                unset($files[$path]);
                continue;
            }
            $target = $documentRoot.$path;
            $content = is_file($target) ? (string)file_get_contents($target) : null;
            try {
                foreach ($list as [$id, $block]) {
                    if (!is_array($block) || !is_string($block['begin'] ?? null) || !is_string($block['end'] ?? null)
                        || trim($block['begin']) === '' || trim($block['end']) === '' || !is_string($block['body'] ?? null)) {
                        throw new \RuntimeException($id.': блок без begin, end или body');
                    }
                    $content = static::mergeBlock($content, $block['begin'], $block['end'], $block['body'], (string)($block['note'] ?? ''));
                }
                if (!static::parses((string)$content)) {
                    throw new \RuntimeException('после вставки блока PHP не разбирается');
                }
                $files[$path] = (string)$content;
            } catch (\RuntimeException $e) {
                $errors[] = $path.': '.$e->getMessage().' — файл не тронут';
            }
        }
        ksort($files);
        ksort($remove);

        return ['files' => $files, 'remove' => $remove, 'missing' => $missing, 'errors' => $errors];
    }

    /**
     * Файл с блоком модуля: все прежние копии блока (между метками, со своими тегами PHP на строках
     * меток) вырезаются, свежий — в конец. Файла нет (null) или он пуст — «<?php», пустая строка и
     * блок. Файл кончается вне PHP («?>», HTML) — блок в своих тегах. Концы строк — как в файле.
     * Меток не поровну — файл правили руками: исключение.
     */
    public static function mergeBlock(?string $current, string $begin, string $end, string $body, string $note = ''): string
    {
        $block = static fn (bool $wrapped): string => ($wrapped ? '<?php ' : '').$begin.($note !== '' ? ' '.$note : '')."\n"
            .($body === '' ? '' : rtrim($body, "\r\n")."\n").$end.($wrapped ? ' ?>' : '')."\n";
        if ($current === null || trim($current) === '') {
            return "<?php\n\n".$block(false);
        }
        if (substr_count($current, $begin) !== substr_count($current, $end)) {
            throw new \RuntimeException('метки блока разбиты ('.$begin.')');
        }
        $eol = str_contains($current, "\r\n") ? "\r\n" : "\n";
        $rest = rtrim(static::stripBlock($current, $begin, $end));
        if ($rest === '') {
            return "<?php".$eol.$eol.str_replace("\n", $eol, $block(false));
        }
        $wrapped = static::endsOutsidePhp($rest);

        return $rest.$eol.($wrapped ? '' : $eol).str_replace("\n", $eol, $block($wrapped));
    }

    /** Файл без блока модуля (все копии, со своими тегами на строках меток). Меток нет — как был. */
    public static function stripBlock(string $current, string $begin, string $end): string
    {
        $pattern = '~(?:<\?php )?'.preg_quote($begin, '~').'.*?'.preg_quote($end, '~').'(?: \?>)?(?:\r?\n)?~s';

        return (string)preg_replace($pattern, '', $current);
    }

    /** Код разбирается PHP (короткие «<?» — как «<?php»: портал с short_open_tag, CLI — как придётся). */
    public static function parses(string $code): bool
    {
        try {
            token_get_all(static::longTags($code), TOKEN_PARSE);

            return true;
        } catch (\ParseError) {
            return false;
        }
    }

    /** Текст кончается вне PHP: последний токен — «?>» или HTML. */
    public static function endsOutsidePhp(string $code): bool
    {
        $tokens = token_get_all(static::longTags($code));
        $last = end($tokens);

        return is_array($last) && in_array($last[0], [T_CLOSE_TAG, T_INLINE_HTML], true);
    }

    private static function longTags(string $code): string
    {
        return (string)preg_replace('/<\?(?!php\b|=|xml)/i', '<?php ', $code);
    }

    /**
     * Что из remove лежит в дереве и будет удалено: каталог и файл без md5 — всегда, файл с md5 —
     * только при совпадении содержимого. Пути с «..» пропускаются.
     *
     * @param array<string, list<string>|null> $remove
     * @return list<string>
     */
    public static function removable(string $documentRoot, array $remove): array
    {
        $documentRoot = rtrim($documentRoot, '/');
        if ($documentRoot === '') {
            return [];
        }
        $found = [];
        foreach ($remove as $path => $hashes) {
            $path = (string)$path;
            if (trim($path, '/') === '' || str_contains($path, '..')) {
                continue;
            }
            $target = $documentRoot.'/'.ltrim($path, '/');
            if ($hashes === null ? (file_exists($target) || is_link($target))
                : (is_file($target) && !is_link($target) && in_array(md5_file($target), $hashes, true))) {
                $found[] = $path;
            }
        }

        return $found;
    }

    /**
     * Удалить из дерева то, что removable(); ссылки не разворачиваются — удаляется сама ссылка.
     *
     * @param array<string, list<string>|null> $remove
     * @return list<string> удалённые пути
     */
    public static function remove(string $documentRoot, array $remove): array
    {
        $documentRoot = rtrim($documentRoot, '/');
        $removed = [];
        foreach (static::removable($documentRoot, $remove) as $path) {
            static::removeTree($documentRoot.'/'.ltrim($path, '/'));
            $removed[] = $path;
        }

        return $removed;
    }

    private static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }
        foreach (scandir($path) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                static::removeTree($path.'/'.$name);
            }
        }
        rmdir($path);
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
