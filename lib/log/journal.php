<?php

declare(strict_types=1);

namespace Shef\Options\Log;

/**
 * Общий журнал модулей линейки (bx-shef/toolsai#612).
 *
 * <code>
 * Journal::for('shef.toolsai')->error('license', 'Сервер лицензий не ответил', ['code' => 'timeout']);
 * </code>
 *
 * Строка: `2026-11-04T10:15:02Z | ERROR | shef.toolsai | license | сообщение | {"code":"timeout"}`.
 * Файлы — по дням: `<каталог логов>/shef.logs/<модуль>/YYYY-MM-DD.log`. Каталог — вне корня
 * сайта (shef_options_log_dir(), #47): файл внутри DOCUMENT_ROOT отдаётся браузеру.
 * Хранение 5 дней, потолок 20 МБ на модуль. Ротация — при первой записи за сутки в процессе
 * и агентом rotateAgent() (его ставит модуль-потребитель).
 *
 * Секреты маскируются при записи (Mask). Тексты запросов сотрудников журнал не пишет —
 * в контекст кладут длину, модель, расход, код итога.
 *
 * Сбой записи модуль не роняет: журнал молчит.
 */
final class Journal
{
    public const LEVELS = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
    public const KEEP_DAYS = 5;
    public const MAX_BYTES = 20 * 1024 * 1024;
    public const MAX_LINE = 8192;
    public const MAX_CONTEXT = 4096;

    /** @var array<string, self> */
    private static array $instances = [];
    /** @var array<string, string> каталог => дата последней ротации в этом процессе */
    private static array $rotated = [];

    private string $module;
    private string $dir;
    /** @var callable(): int */
    private $clock;

    public function __construct(string $module, ?string $baseDir = null, ?callable $clock = null)
    {
        if (!self::validModule($module)) {
            throw new \InvalidArgumentException('bad module id');
        }
        $this->module = $module;
        $this->dir = rtrim($baseDir ?? self::baseDir(), '/').'/'.$module;
        $this->clock = $clock ?? static fn (): int => time();
    }

    public static function for(string $module): self
    {
        return self::$instances[$module] ??= new self($module);
    }

    public static function validModule(string $module): bool
    {
        return strlen($module) <= 64 && (bool)preg_match('/^[a-z0-9_]+(?:\.[a-z0-9_]+)*$/', $module);
    }

    public static function baseDir(): string
    {
        $root = function_exists('shef_options_log_dir') ? shef_options_log_dir() : sys_get_temp_dir();

        return rtrim($root, '/').'/shef.logs';
    }

    public function dir(): string
    {
        return $this->dir;
    }

    public function debug(string $tag, string $message, array $context = []): void
    {
        $this->write('DEBUG', $tag, $message, $context);
    }

    public function info(string $tag, string $message, array $context = []): void
    {
        $this->write('INFO', $tag, $message, $context);
    }

    public function warning(string $tag, string $message, array $context = []): void
    {
        $this->write('WARNING', $tag, $message, $context);
    }

    public function error(string $tag, string $message, array $context = []): void
    {
        $this->write('ERROR', $tag, $message, $context);
    }

    public function format(string $level, string $tag, string $message, array $context = []): string
    {
        $level = strtoupper($level);
        $level = in_array($level, self::LEVELS, true) ? $level : 'INFO';
        $tag = (string)preg_replace('/[^A-Za-z0-9_.:-]/', '_', $tag);
        $tag = $tag !== '' ? $tag : '-';
        $message = Mask::string(self::oneLine($message));
        $line = gmdate('Y-m-d\TH:i:s\Z', ($this->clock)()).' | '.$level.' | '.$this->module.' | '.$tag.' | '.$message;
        if ($context !== []) {
            $json = json_encode(
                Mask::context($context),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
            if (is_string($json)) {
                if (strlen($json) > self::MAX_CONTEXT) {
                    $json = (string)json_encode(['truncated' => strlen($json)]);
                }
                // Ещё раз по готовой строке: секрет мог прийти в ключе массива.
                $line .= ' | '.Mask::string($json);
            }
        }
        if (strlen($line) > self::MAX_LINE) {
            $line = mb_strcut($line, 0, self::MAX_LINE - 3).'…';
        }

        return $line;
    }

    public function write(string $level, string $tag, string $message, array $context = []): void
    {
        try {
            $line = $this->format($level, $tag, $message, $context);
            if (!$this->ensureDir()) {
                return;
            }
            $day = gmdate('Y-m-d', ($this->clock)());
            if ((self::$rotated[$this->dir] ?? '') !== $day) {
                self::$rotated[$this->dir] = $day;
                $this->rotate();
            }
            $file = $this->dir.'/'.$day.'.log';
            $isNew = !is_file($file);
            @file_put_contents($file, $line."\n", FILE_APPEND | LOCK_EX);
            if ($isNew) {
                @chmod($file, defined('BX_FILE_PERMISSIONS') ? BX_FILE_PERMISSIONS : 0640);
            }
        } catch (\Throwable) {
            // журнал не роняет модуль
        }
    }

    /**
     * Файлы журнала по дням, от старых к новым. Ссылки пропускаются.
     *
     * @return array<string, string> дата => путь
     */
    public function files(): array
    {
        $out = [];
        $list = @glob($this->dir.'/*.log');
        foreach (is_array($list) ? $list : [] as $path) {
            $name = basename($path, '.log');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $name) && is_file($path) && !is_link($path)) {
                $out[$name] = $path;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Срок 5 дней и потолок 20 МБ: сначала дни старше срока, потом самые старые файлы, пока
     * не влезет; один файл больше потолка — остаётся его вторая половина.
     */
    public function rotate(): void
    {
        clearstatcache();
        $oldest = gmdate('Y-m-d', ($this->clock)() - (self::KEEP_DAYS - 1) * 86400);
        $files = $this->files();
        foreach ($files as $day => $path) {
            if ($day < $oldest) {
                @unlink($path);
                unset($files[$day]);
            }
        }
        $size = 0;
        foreach ($files as $path) {
            $size += (int)@filesize($path);
        }
        while ($size > self::MAX_BYTES && count($files) > 1) {
            $path = array_shift($files);
            $size -= (int)@filesize($path);
            @unlink($path);
        }
        if ($size > self::MAX_BYTES && count($files) === 1) {
            $path = (string)reset($files);
            $keep = self::readTailRaw($path, intdiv(self::MAX_BYTES, 2));
            $pos = strpos($keep, "\n");
            @file_put_contents($path, $pos === false ? '' : substr($keep, $pos + 1), LOCK_EX);
        }
    }

    /**
     * Журнал модуля для архива: последние $maxBytes, маска ещё раз по всему тексту.
     * Обрезка отмечается первой строкой.
     */
    public function tail(int $maxBytes = 5 * 1024 * 1024): string
    {
        clearstatcache();
        $chunks = [];
        $left = $maxBytes;
        $cut = false;
        foreach (array_reverse($this->files()) as $path) {
            if ($left <= 0) {
                $cut = true;
                break;
            }
            $size = (int)@filesize($path);
            $part = self::readTailRaw($path, $left);
            if ($size > $left) {
                $cut = true;
            }
            $left -= strlen($part);
            array_unshift($chunks, $part);
        }
        $text = implode('', $chunks);
        if ($cut) {
            $pos = strpos($text, "\n");
            $text = '# журнал обрезан до последних '.$maxBytes." байт\n".($pos === false ? '' : substr($text, $pos + 1));
        }

        // Правила маски не выходят за строку — один проход по всему тексту.
        return Mask::string($text);
    }

    /** Агент: ротация журналов всех модулей. Ставит модуль-потребитель (shef.toolsai). */
    public static function rotateAgent(): string
    {
        try {
            $dirs = @glob(self::baseDir().'/*', GLOB_ONLYDIR);
            foreach (is_array($dirs) ? $dirs : [] as $dir) {
                $module = basename($dir);
                if (self::validModule($module) && !is_link($dir)) {
                    (new self($module))->rotate();
                }
            }
        } catch (\Throwable) {
        }

        return '\\'.self::class.'::rotateAgent();';
    }

    private function ensureDir(): bool
    {
        if (is_dir($this->dir)) {
            return is_writable($this->dir);
        }
        $perm = defined('BX_DIR_PERMISSIONS') ? BX_DIR_PERMISSIONS : 0750;
        if (!@mkdir($this->dir, $perm, true) && !is_dir($this->dir)) {
            return false;
        }
        $base = dirname($this->dir);
        if (!is_file($base.'/.htaccess')) {
            @file_put_contents($base.'/.htaccess', "Require all denied\nDeny from all\n");
        }

        return true;
    }

    private static function oneLine(string $text): string
    {
        return strtr($text, ["\r\n" => '\n', "\n" => '\n', "\r" => '\n', "\0" => '']);
    }

    private static function readTailRaw(string $path, int $bytes): string
    {
        $size = (int)@filesize($path);
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return '';
        }
        if ($size > $bytes) {
            fseek($fh, $size - $bytes);
        }
        // Не больше $bytes: файл мог вырасти после filesize().
        $data = (string)stream_get_contents($fh, $bytes);
        fclose($fh);

        return $data;
    }
}
