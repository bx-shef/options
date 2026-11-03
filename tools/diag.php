<?php

declare(strict_types=1);

/**
 * Диагностика портала по модулям линейки shef.* (issue #70). ТОЛЬКО ЧТЕНИЕ — ничего не меняет.
 *
 *   php bitrix/bitrix.php shef:modules:diag [--json] [--no-network] [--mcp-dir=…] [--targets=…]
 *   php diag.php [--json] [--no-network] [--root=/home/bitrix/www] [--mcp-dir=…] [--targets=…]
 *
 * --targets=файл.json — цели выкатки {"release": "…", "targets": {"shef.toolsai": "1.91.0", …}}
 * вместо зашитых (Diag::TARGETS, выкатка 10.11.2026). Корень сайта одиночного файла: --root,
 * DOCUMENT_ROOT, каталог модуля (файл лежит в bitrix/modules/shef.options/tools), /home/bitrix/www.
 * Каталог MCP по умолчанию — <родитель корня>/mcp. Нужен PHP 8.1+.
 *
 * Второй способ — для портала без shef.options: файл самодостаточный, его можно скопировать куда
 * угодно. Запуск — от пользователя веб-сервера (на BitrixVM — bitrix), без sudo.
 *
 * Выводит: окружение (PHP, Битрикс), модули shef.* (установлен, версия и дата файлов), shef.cowork
 * (адрес и версия по /health, образ из .env MCP), file-extract, направления shef.toolsai (провайдер,
 * модель, адрес, ключ — только «задан / не задан»), локальные модели и их ответ, лицензию,
 * совпадение /bitrix/js с install/js модулей (toolsai-assistant#336), права на запись, агенты и
 * cron, правки ядра (shef.haschangefiles), готовность к выкатке и путь обновления.
 *
 * Секреты не выводятся: значения настроек с key/token/secret/pass в имени заменяются на «задан /
 * не задан», из адресов убираются логин и параметры, и перед печатью весь отчёт ещё раз чистится
 * от собранных значений секретов (Diag::redact()).
 *
 * Код выхода: 0 — отчёт выведен; 2 — не найден корень сайта, цели не читаются или запуск от root
 * без --allow-root (от root права на запись и владельцы файлов показываются неверно, а код модулей
 * и CLI shef.haschangefiles исполнялся бы от root).
 */

namespace Shef\Options\Tools;

final class Diag
{
    public const FORMAT = 1;

    /** Версия скрипта: отличает старые копии у клиентов. */
    public const VERSION = '1.0.0';

    /** Выкатка, к которой относятся TARGETS. */
    public const RELEASE = '2026-11-10';

    /** Модули линейки, о которых отчёт говорит, даже если их нет на диске. */
    public const MODULES = [
        'shef.options', 'shef.problems', 'shef.toolsai', 'shef.toolsaiassistant', 'shef.toolsaimemory',
        'shef.toolsaieval', 'shef.toolsaikb', 'shef.toolsaiannounce',
    ];

    /** Целевые версии выкатки 10.11.2026 (plan/ВЫКАТКА-2026-11-10.md, шаг 2). */
    public const TARGETS = [
        'shef.options' => '3.9.0',
        'shef.problems' => '2.0.3',
        'shef.toolsaikb' => '1.13.3',
        'shef.toolsai' => '1.91.0',
        'shef.toolsaieval' => '1.16.0',
        'shef.toolsaiassistant' => '1.95.1',
        'shef.toolsaimemory' => '0.4.0',
        'shef.toolsaiannounce' => '1.15.4',
        'shef.cowork' => '1.11.0',
    ];

    /** Направления shef.toolsai: подпись => префикс настроек API_<префикс>*. */
    public const DIRECTIONS = [
        'Текст' => 'llm',
        'Распознавание' => 'asr',
        'Анализ сделок' => 'deal',
        'Помощник' => 'assistant',
        'Лёгкая модель' => 'light',
        'Эмбеддинги' => 'embed',
        'Поиск в интернете' => 'web',
    ];

    /** Ключи .env сервера MCP, которые можно показать значением. */
    public const MCP_ENV_SHOW = ['MCP_IMAGE', 'FILE_EXTRACT_URL', 'FILE_EXTRACT_TAG'];

    /** @var string[] значения секретов, встреченные при сборе: их не должно быть в выводе */
    private array $secrets = [];

    /** @var array<string, list<array{0: string, 1: string, 2: string}>> раздел => строки [уровень, что, значение] */
    private array $sections = [];

    /** @var array<string, mixed> машинные данные для --json */
    private array $data = [];

    /**
     * @param \Closure(string): array{0: int, 1: string} $http GET адреса => [код ответа (0 — нет связи), тело]
     * @param \Closure(string): array<string, string> $options настройки модуля (все, с умолчаниями)
     * @param \Closure(string): bool $installed модуль зарегистрирован на портале
     * @param ?\Closure(string): ?array{url: string, key: string, model: string, keyFrom?: string} $endpoint
     *        направление по Config shef.toolsai (пресеты, синонимы адресов) — точнее разбора настроек
     * @param array<string, string> $targets цели выкатки
     */
    public function __construct(
        private readonly string $root,
        private readonly \Closure $options,
        private readonly \Closure $installed,
        private readonly ?\Closure $http = null,
        private readonly string $mcpDir = '',
        private readonly ?\Closure $endpoint = null,
        private readonly array $targets = self::TARGETS,
        private readonly string $release = self::RELEASE,
    ) {
        $this->started = microtime(true);
    }

    private float $started;

    // ------------------------------------------------------------------ чистые функции

    /** Имя настройки, значение которой — секрет. */
    public static function isSecretName(string $name): bool
    {
        return (bool)preg_match('/(key|token|secret|pass|pwd|auth|cookie|salt|webhook)/i', $name);
    }

    /** Адрес без логина, пароля, параметров и якоря; не адрес — пусто. */
    public static function cleanUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        // Токен в пути (вебхук /rest/1/<код>/, прокси /<токен>/mcp): сегмент из 16+ букв и цифр — ***
        $path = (string)preg_replace_callback('~[^/]+~', static fn (array $m): string => strlen($m[0]) >= 16 && preg_match('~\d~', $m[0]) && preg_match('~[a-z]~i', $m[0]) && !str_contains($m[0], '.') ? '***' : $m[0], (string)($parts['path'] ?? ''));

        return strtolower($parts['scheme']).'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$path;
    }

    /** Адрес смотрит на эту машину или частную сеть (локальная модель, сервис рядом). */
    public static function isLocal(string $url): bool
    {
        $host = strtolower(trim((string)parse_url($url, PHP_URL_HOST), '[]'));
        if ($host === '') {
            return false;
        }
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || !str_contains($host, '.') && !str_contains($host, ':')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }

    /** Адрес /health сервера по адресу подключения (MCP: …/mcp → …/health). */
    public static function healthUrl(string $url): string
    {
        $clean = self::cleanUrl($url);

        // https://портал/mcp-ai/mcp → https://портал/mcp-ai/health: префикс прокси сохраняется
        return $clean === '' ? '' : (string)preg_replace('~/(mcp|v1)?/?$~', '', $clean).'/health';
    }

    /**
     * Направление shef.toolsai из настроек — по правилам Config shef.toolsai (resolveAccess):
     * распознавание и текст без своего адреса — общий (API_baseurl); сделки, помощник, лёгкая и
     * эмбеддинги — как у «Текст»; выбран пресет без адреса — адрес пресета (показывается именем).
     * Ключ наследуется, только если адрес тот же, что у родителя. Поиск в интернете — без адреса,
     * только свой ключ. Ключ — только «задан».
     *
     * @param array<string, string> $o
     * @return array{provider: string, model: string, url: string, urlShared: bool, key: bool, keyShared: bool, urlFrom: string, keyFrom: string, local: bool}
     */
    public static function direction(array $o, string $prefix): array
    {
        $o = array_change_key_case($o); // ядро хранит имена настроек строчными (api_llmmodel)
        $get = static fn (string $name): string => trim((string)($o[$name] ?? ''));
        $norm = static fn (string $u): string => rtrim(strtolower($u), '/');
        $resolve = static function (string $p) use (&$resolve, $get, $norm): array {
            if ($p === '') {
                return ['url' => $get('api_baseurl'), 'key' => $get('api_apikey'), 'urlFrom' => '', 'keyFrom' => '', 'from' => 'общий'];
            }
            $parent = $resolve(in_array($p, ['asr', 'llm'], true) ? '' : 'llm');
            $parentName = $p === 'asr' || $p === 'llm' ? 'общий' : 'как у «Текст»';
            $url = $get('api_'.$p.'baseurl');
            $urlFrom = '';
            if ($url === '' && $get('api_'.$p.'preset') !== '') {
                $urlFrom = 'пресет '.$get('api_'.$p.'preset');
            } elseif ($url === '') {
                $url = $parent['url'];
                $urlFrom = $url !== '' ? $parentName : '';
            }
            $key = $get('api_'.$p.'apikey');
            $keyFrom = '';
            if ($key === '' && !str_starts_with($urlFrom, 'пресет') && $url !== '' && $norm($url) === $norm($parent['url']) && $parent['key'] !== '') {
                $key = $parent['key'];
                $keyFrom = $parentName;
            }

            return ['url' => $url, 'key' => $key, 'urlFrom' => $urlFrom, 'keyFrom' => $keyFrom];
        };
        if ($prefix === 'web') {
            // Config::getWebSearch(): свой ключ, иначе ключ «Текст», если «Текст» — DeepSeek
            $text = $resolve('llm');
            $own = $get('api_webapikey');
            $viaText = $own === '' && str_contains(strtolower($text['url']), 'deepseek.com') && $text['key'] !== '';
            $r = ['url' => '', 'key' => $viaText ? $text['key'] : $own, 'urlFrom' => '', 'keyFrom' => $viaText ? 'как у «Текст»' : ''];
        } else {
            $r = $resolve($prefix);
        }
        $model = $get('api_'.$prefix.'model') ?: $get('api_'.$prefix.'presetmodel');
        if ($model === '' && $prefix === 'embed') {
            $model = 'bge-m3 (по умолчанию)';
        } elseif ($model === '' && in_array($prefix, ['deal', 'assistant', 'light'], true)) {
            $text = $get('api_llmmodel') ?: $get('api_llmpresetmodel');
            $model = $text !== '' ? $text.' (как у «Текст»)' : '';
        }

        return [
            'provider' => $prefix === 'asr' ? $get('def_provideraudio') : $get('def_providertext'),
            'model' => $model,
            'url' => self::cleanUrl($r['url']),
            'urlShared' => $r['urlFrom'] !== '',
            'key' => $r['key'] !== '',
            'keyShared' => $r['keyFrom'] !== '',
            'urlFrom' => $r['urlFrom'],
            'keyFrom' => $r['keyFrom'],
            'local' => self::isLocal($r['url']),
        ];
    }

    /** Версия и дата из install/version.php без подключения модуля. @return array{0: string, 1: string} */
    public static function fileVersion(string $moduleDir): array
    {
        $file = $moduleDir.'/install/version.php';
        if (!is_file($file)) {
            return ['', ''];
        }
        $arModuleVersion = [];
        try {
            (static function () use ($file, &$arModuleVersion): void {
                include $file;
            })();
        } catch (\Throwable) {
            return ['?', ''];
        }

        return [(string)($arModuleVersion['VERSION'] ?? ''), (string)($arModuleVersion['VERSION_DATE'] ?? '')];
    }

    /**
     * Сверка install/js модуля с /bitrix/js (toolsai-assistant#336): одинаковые, разные, нет в /bitrix/js.
     *
     * @return array{same: int, differ: list<string>, missing: list<string>}
     */
    public static function jsMatch(string $moduleDir, string $root): array
    {
        $out = ['same' => 0, 'differ' => [], 'missing' => []];
        $base = $moduleDir.'/install/js';
        if (!is_dir($base)) {
            return $out;
        }
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            $files = iterator_to_array($it, false);
        } catch (\UnexpectedValueException) {
            $out['missing'][] = '(install/js не читается)';
            return $out;
        }
        foreach ($files as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            $rel = substr($file->getPathname(), strlen($base) + 1);
            $rel = str_replace('\\', '/', $rel);
            $target = $root.'/bitrix/js/'.$rel;
            if (!is_file($target)) {
                $out['missing'][] = $rel;
            } elseif (($a = md5_file($target)) === false || $a !== md5_file($file->getPathname())) {
                $out['differ'][] = $rel;
            } else {
                $out['same']++;
            }
        }
        sort($out['differ']);
        sort($out['missing']);

        return $out;
    }

    /**
     * Путь обновления по версиям портала и целям выкатки.
     *
     * @param array<string, ?string> $have модуль => версия на портале ('' — не установлен, null — не проверена)
     * @param array<string, string> $targets
     * @return array{path: string, route: string, method: string, behind: list<string>, done: list<string>, unknown: list<string>}
     */
    public static function rollout(array $have, array $targets, bool $syncCommand, bool $git): array
    {
        $behind = [];
        $done = [];
        $unknown = [];
        foreach ($targets as $id => $target) {
            if (array_key_exists($id, $have) && $have[$id] === null) {
                $unknown[] = $id.' — версия не проверена, цель '.$target;
                continue;
            }
            $v = $have[$id] ?? '';
            if ($v !== '' && version_compare($v, $target, '>=')) {
                $done[] = $id.' '.$v;
            } else {
                $behind[] = $id.' '.($v === '' ? '—' : $v).' → '.$target;
            }
        }
        $method = $git ? 'git' : ($syncCommand ? 'sync' : 'cp');
        $route = '';
        if (($have['shef.toolsai'] ?? '') === '') {
            // shef.options и shef.problems могут стоять: без shef.toolsai линейки нет — ставится вся
            $path = 'новая установка: shef.toolsai на портале нет, ставить всё по порядку выкатки';
            $route = 'fresh';
        } elseif ($behind === [] && $unknown !== []) {
            $path = 'не определён: модули на целевых, не проверено — '.implode(', ', array_map(static fn (string $l): string => strstr($l, ' ', true), $unknown));
            $route = 'unknown';
        } elseif ($behind === []) {
            $path = 'обновление не нужно: всё на целевых версиях';
            $route = 'none';
        } else {
            $mcp = (bool)array_filter($behind, static fn (string $l): bool => str_starts_with($l, 'shef.cowork '));
            $modules = count($behind) - ($mcp ? 1 : 0) > 0;
            $how = $git ? 'портал в git — модули через репозиторий и деплой, раскладка tools/layout.php'
                : ($syncCommand ? 'файлы модулей поверх, затем php bitrix.php shef:modules:sync' : 'файлы модулей поверх (cp), затем «Обновить»; shef.options — первым');
            $path = ($modules && $mcp ? 'модули и MCP' : ($mcp ? 'только MCP (make в /home/bitrix/mcp)' : 'только модули')).($modules ? ': '.$how : '')
                .($unknown !== [] ? '; не проверено — '.implode(', ', array_map(static fn (string $l): string => strstr($l, ' ', true), $unknown)) : '');
            $route = $modules && $mcp ? 'modules+mcp' : ($mcp ? 'mcp' : 'modules');
        }

        return ['path' => $path, 'route' => $route, 'method' => $method, 'behind' => $behind, 'done' => $done, 'unknown' => $unknown];
    }

    /** Убрать из строки известные значения секретов. @param string[] $secrets */
    public static function redact(string $text, array $secrets): string
    {
        foreach ($secrets as $s) {
            // короткие и служебные значения (true, 3000, Y) секретами не бывают — их замена ломала бы
            // отчёт и JSON
            if (strlen($s) >= 8 && !preg_match('/^(true|false|yes|no|on|off|null)$/i', $s)) {
                $text = str_replace($s, '***', $text);
            }
        }

        return $text;
    }

    // ------------------------------------------------------------------ сбор

    /** @return array<string, string> */
    private function opts(string $module): array
    {
        $o = array_change_key_case(($this->options)($module)); // имена в ядре — строчными
        foreach ($o as $name => $value) {
            if (self::isSecretName((string)$name) && is_string($value) && $value !== '') {
                $this->secrets[] = $value;
            }
        }

        return $o;
    }

    private function row(string $section, string $level, string $what, string $value): void
    {
        $this->sections[$section][] = [$level, $what, $value];
    }

    /** @var array<string, array{0: int, 1: string}> */
    private array $got = [];

    /** Один запрос на адрес за прогон. @return array{0: int, 1: string} */
    private function get(string $url): array
    {
        return $this->http === null ? [0, 'не проверялось (--no-network)'] : ($this->got[$url] ??= ($this->http)($url));
    }

    public function collect(): self
    {
        $this->environment();
        $versions = $this->modules();
        $cowork = $this->cowork();
        $this->directions();
        $this->license();
        $jsOk = $this->js();
        $this->writable();
        $cron = $this->agents();
        $this->core();
        $versions['shef.cowork'] = $cowork === '' ? null : $cowork; // не проверено — не «отстаёт»
        $this->rolloutSection($versions, $jsOk, $cron);

        return $this;
    }

    private function environment(): void
    {
        $s = 'Окружение';
        $this->row($s, PHP_VERSION_ID >= 80200 ? 'OK' : 'FAIL', 'PHP', PHP_VERSION.' ('.PHP_SAPI.')');
        $main = defined('SM_VERSION') ? (string)SM_VERSION : self::fileVersion($this->root.'/bitrix/modules/main')[0];
        $this->row($s, 'INFO', 'Битрикс (main)', $main !== '' ? $main : 'не прочитана');
        $this->data['bitrix'] = $main;
        $this->data['php'] = PHP_VERSION;
        $this->row($s, 'INFO', 'редакция', is_dir($this->root.'/bitrix/modules/bitrix24') ? 'есть модуль bitrix24 (облако?)' : 'коробка');
        $user = function_exists('posix_geteuid') ? ((posix_getpwuid(posix_geteuid())['name'] ?? '') ?: (string)posix_geteuid()) : get_current_user();
        $this->row($s, $user === 'root' ? 'WARN' : 'INFO', 'пользователь процесса', $user.($user === 'root' ? ' — права и владельцы ниже показаны для root, запускайте от bitrix' : ''));
        $this->row($s, 'INFO', 'скрипт', 'diag '.self::VERSION.', цели выкатки '.$this->release);
        $this->row($s, 'INFO', 'корень сайта', $this->root);
        $this->row($s, 'INFO', 'портал в git', is_dir($this->root.'/.git') || is_file($this->root.'/.git') ? 'да' : 'нет');
        $this->data['git'] = is_dir($this->root.'/.git') || is_file($this->root.'/.git');
        $this->data['user'] = $user;
    }

    /** @return array<string, string> модуль => версия файлов установленного модуля ('' — нет) */
    private function modules(): array
    {
        $s = 'Модули shef.*';
        $dirs = $this->moduleDirs();
        foreach (self::MODULES as $id) {
            $dirs[$id] ??= '';
        }
        ksort($dirs);
        $versions = [];
        foreach ($dirs as $id => $dir) {
            [$v, $date] = $dir !== '' ? self::fileVersion($dir) : ['', ''];
            $installed = ($this->installed)($id);
            $versions[$id] = $installed ? $v : '';
            $this->data['modules'][$id] = ['installed' => $installed, 'files' => $dir !== '', 'version' => $v, 'date' => $date];
            $this->row($s, $installed ? 'OK' : ($dir !== '' ? 'WARN' : 'INFO'), $id, match (true) {
                $installed => $v.($date !== '' ? ' от '.substr($date, 0, 10) : ''),
                $dir !== '' => 'файлы '.$v.' есть, не установлен',
                default => 'нет',
            });
        }

        return $versions;
    }

    /** @return string версия shef.cowork по /health ('' — неизвестна) */
    private function cowork(): string
    {
        $s = 'shef.cowork и file-extract';
        $o = ($this->installed)('shef.toolsaiassistant') ? $this->opts('shef.toolsaiassistant') : [];
        // agent_coworkurl — страница установки и покупки, не сервер; адрес сервера — agent_mcpurl
        $raw = trim((string)($o['agent_mcpurl'] ?? ''));
        $version = '';
        if ($raw === '') {
            $this->row($s, 'WARN', 'адрес MCP', 'не задан в помощнике («Адрес MCP-сервера», AGENT_mcpurl)');
        } else {
            $this->row($s, 'INFO', 'адрес MCP', self::cleanUrl($raw));
            [$code, $body] = $this->get(self::healthUrl($raw));
            $health = json_decode($body, true);
            $version = is_array($health) && is_string($health['version'] ?? null) ? substr((string)preg_replace('/[^0-9A-Za-z.+-]/', '', $health['version']), 0, 32) : '';
            $this->row($s, $code === 200 ? 'OK' : 'WARN', '/health', $code === 200 ? 'версия '.($version ?: '?').(is_bool($health['ready'] ?? null) ? ', ready '.($health['ready'] ? 'true' : 'false') : '') : ($code === 0 ? $body : 'HTTP '.$code));
        }
        $this->data['cowork'] = ['url' => self::cleanUrl($raw), 'version' => $version];

        $env = $this->mcpDir !== '' ? $this->mcpDir.'/.env' : '';
        if ($env === '' || !is_readable($env)) {
            $this->row($s, 'INFO', '.env MCP', $env === '' ? 'каталог не задан' : $env.' не читается (не на этом сервере?)');
            return $version;
        }
        $vars = [];
        foreach (file($env, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
                $value = trim($m[2], " \t\"'\r");
                $vars[$m[1]] = $value;
                if (self::isSecretName($m[1]) && $value !== '') {
                    $this->secrets[] = $value;
                }
            }
        }
        foreach (self::MCP_ENV_SHOW as $name) {
            $value = $name === 'FILE_EXTRACT_URL' ? self::cleanUrl($vars[$name] ?? '') : (string)preg_replace('~^[^/@]+@~', '', (string)($vars[$name] ?? '')); // user:token@реестр/…
            $this->row($s, 'INFO', $name, $value !== '' ? $value : 'не задан');
            $this->data['mcpEnv'][$name] = $value;
        }
        $this->row($s, 'INFO', 'FILE_EXTRACT_KEY', ($vars['FILE_EXTRACT_KEY'] ?? '') !== '' ? 'задан' : 'не задан');
        $extract = (string)($vars['FILE_EXTRACT_URL'] ?? '');
        $host = (string)parse_url($extract, PHP_URL_HOST);
        if ($extract !== '' && $host !== '' && (str_contains($host, '.') || $host === 'localhost')) {
            [$code] = $this->get(self::healthUrl($extract));
            $this->row($s, $code === 200 ? 'OK' : 'WARN', 'file-extract /health', $code === 200 ? 'отвечает' : 'HTTP '.$code);
        } elseif ($extract !== '') {
            $this->row($s, 'INFO', 'file-extract /health', 'адрес внутри сети docker — проверка: make status / make extract-logs в каталоге MCP');
        }

        return $version;
    }

    private function directions(): void
    {
        $s = 'Направления shef.toolsai';
        if (!($this->installed)('shef.toolsai')) {
            $this->row($s, 'INFO', 'shef.toolsai', 'не установлен');
            return;
        }
        $o = $this->opts('shef.toolsai');
        $this->row($s, 'INFO', 'провайдер текст / аудио', ($o['def_providertext'] ?? '?').' / '.($o['def_provideraudio'] ?? '?'));
        foreach (self::DIRECTIONS as $title => $prefix) {
            $d = self::direction($o, $prefix);
            $ep = $this->endpoint !== null ? ($this->endpoint)($prefix) : null;
            if (is_array($ep)) {
                // Config shef.toolsai знает пресеты и синонимы адресов: адрес, ключ и модель — от него
                if ($ep['key'] !== '') {
                    $this->secrets[] = $ep['key'];
                }
                $d['url'] = self::cleanUrl($ep['url']);
                $d['local'] = self::isLocal($ep['url']);
                $d['key'] = $ep['key'] !== '';
                $d['keyFrom'] = $d['key'] ? ($ep['keyFrom'] ?? $d['keyFrom']) : '';
                $d['model'] = $ep['model'] !== '' ? $ep['model'] : $d['model'];
            }
            $this->data['directions'][$prefix] = $d;
            $value = sprintf(
                'модель %s, адрес %s%s, ключ %s%s',
                $d['model'] !== '' ? $d['model'] : '—',
                $d['url'] !== '' ? $d['url'] : '—',
                $d['urlFrom'] !== '' ? ' ('.$d['urlFrom'].')' : '',
                $d['key'] ? 'задан' : 'не задан',
                $d['keyFrom'] !== '' ? ' ('.$d['keyFrom'].')' : ''
            );
            // лёгкая и эмбеддинги без своего адреса идут на движок «Текст» (bge-m3 он может не знать)
            $level = in_array($prefix, ['light', 'embed'], true) && $d['urlFrom'] !== '' ? 'WARN' : 'INFO';
            $this->row($s, $level, $title, $value);
            // Свои сервисы лёгкой модели и эмбеддингов (toolsai-light, toolsai-embed — по образцу
            // file-extract): /health и версия. Общий адрес — это движок «Текст», его не опрашиваем.
            if (in_array($prefix, ['light', 'embed'], true) && $d['url'] !== '' && !$d['urlShared']) {
                [$code, $body] = $this->get(self::healthUrl($d['url']));
                $health = $code === 200 ? json_decode($body, true) : null;
                $version = is_array($health) && is_string($health['version'] ?? null) ? substr((string)preg_replace('/[^0-9A-Za-z.+-]/', '', $health['version']), 0, 32) : '';
                $this->row($s, $code === 200 ? 'OK' : 'WARN', '  /health', $code === 200 ? 'отвечает'.($version !== '' ? ', версия '.$version : '') : ($code === 0 ? $body : 'HTTP '.$code));
                $this->data['directions'][$prefix]['service'] = ['health' => $code, 'version' => $version];
            }
            if ($d['local'] && $d['url'] !== '') {
                [$code, $body] = $this->get(rtrim($d['url'], '/').'/models');
                $this->row($s, in_array($code, [200, 401, 403], true) ? 'OK' : 'WARN', '  локальная модель', $code === 0 ? $body : 'GET /models → HTTP '.$code);
                $this->data['directions'][$prefix]['health'] = $code;
            }
        }
    }

    private function license(): void
    {
        $s = 'Лицензия';
        if (!class_exists('\Bitrix\Main\Loader') || !($this->installed)('shef.toolsai') || !\Bitrix\Main\Loader::includeModule('shef.toolsai')
            || !method_exists('\Shef\ToolsAi\Container', 'getLicense')) {
            $this->row($s, 'INFO', 'shef.toolsai', 'лицензия не читается (модуль не установлен или старый)');
            return;
        }
        try {
            $st = \Shef\ToolsAi\Container::getLicense()->status(false);
            $type = is_array($st->payload) ? (string)($st->payload['type'] ?? '') : '';
            $this->row($s, $st->mode === 'active' ? 'OK' : ($st->isWorking() ? 'INFO' : 'FAIL'), 'режим', $st->mode.($type !== '' ? ', '.$type : '').($st->daysLeft !== null ? ', осталось дней: '.$st->daysLeft : '').($st->noLink ? ', давно без связи с сервером' : ''));
            $this->data['license'] = ['mode' => $st->mode, 'type' => $type, 'daysLeft' => $st->daysLeft, 'working' => $st->isWorking()];
        } catch (\Throwable $e) {
            $this->row($s, 'WARN', 'режим', 'ошибка чтения: '.get_class($e));
        }
    }

    /** Каталоги модулей shef.*: local главнее bitrix, как у ядра. @return array<string, string> */
    private function moduleDirs(): array
    {
        $dirs = [];
        foreach (['/bitrix/modules', '/local/modules'] as $base) {
            foreach (glob($this->root.$base.'/shef.*', GLOB_ONLYDIR) ?: [] as $dir) {
                $dirs[basename($dir)] = $dir;
            }
        }
        ksort($dirs);

        return $dirs;
    }

    private function js(): bool
    {
        $s = '/bitrix/js и install/js (#336)';
        $ok = true;
        foreach ($this->moduleDirs() as $dir) {
            if (!is_dir($dir.'/install/js')) {
                continue;
            }
            $m = self::jsMatch($dir, $this->root);
            $bad = count($m['differ']) + count($m['missing']);
            $ok = $ok && $bad === 0;
            $this->data['js'][basename($dir)] = $m;
            $this->row($s, $bad === 0 ? 'OK' : 'FAIL', basename($dir), $bad === 0 ? 'совпадает, файлов '.$m['same']
                : sprintf('совпадает %d, отличается %d, нет %d: %s', $m['same'], count($m['differ']), count($m['missing']), implode(', ', array_slice(array_merge($m['differ'], $m['missing']), 0, 5))));
        }

        return $ok;
    }

    private function writable(): void
    {
        $s = 'Права на запись';
        $me = function_exists('posix_geteuid') ? posix_geteuid() : null;
        $memory = ($this->installed)('shef.toolsaimemory') ? (string)($this->opts('shef.toolsaimemory')['storage_path'] ?? '') : '';
        $paths = ['/bitrix/js/shef', '/bitrix/css', '/upload'];
        foreach ($this->moduleDirs() as $dir) {
            $paths[] = substr($dir, strlen($this->root));
        }
        foreach ($paths as $rel) {
            $path = $this->root.$rel;
            if (!file_exists($path)) {
                $this->row($s, 'INFO', $rel, 'нет');
                continue;
            }
            $foreign = $me === null ? [] : self::foreign($path, $me, $rel === '/upload' ? 0 : 6);
            $w = is_writable($path);
            $this->row($s, $w && $foreign === [] ? 'OK' : 'FAIL', $rel, ($w ? 'запись есть' : 'НЕТ записи').($foreign !== [] ? ', чужие файлы: '.implode(', ', $foreign) : ''));
        }
        $memDir = $memory !== '' ? $memory : dirname($this->root);
        $this->row($s, is_writable($memDir) ? 'OK' : 'WARN', 'каталог памяти', $memDir.(is_writable($memDir) ? ' — запись есть' : ' — нет записи'));
    }

    /** Первые 5 файлов не текущего владельца (глубина ограничена). @return list<string> */
    private static function foreign(string $path, int $uid, int $depth): array
    {
        $out = [];
        if (@fileowner($path) !== $uid) {
            $out[] = basename($path);
        }
        if ($depth === 0 || !is_dir($path)) {
            return $out;
        }
        try {
            // CATCH_GET_CHILD: нечитаемый подкаталог (чужой, 700) пропускается, обход идёт дальше
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST, \RecursiveIteratorIterator::CATCH_GET_CHILD);
            $it->setMaxDepth($depth);
            foreach ($it as $file) {
                if (count($out) >= 5) {
                    break;
                }
                if (!$file->isLink() && @fileowner($file->getPathname()) !== $uid) {
                    $out[] = substr($file->getPathname(), strlen($path) + 1);
                }
            }
        } catch (\UnexpectedValueException) {
            $out[] = '(не читается)';
        }

        return $out;
    }

    private function agents(): bool
    {
        $s = 'Агенты и cron';
        $cronConst = defined('BX_CRONTAB_SUPPORT') && BX_CRONTAB_SUPPORT === true;
        $this->row($s, $cronConst ? 'OK' : 'WARN', 'BX_CRONTAB_SUPPORT', $cronConst ? 'true' : 'нет — агенты на хитах');
        $canShell = function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true);
        $tab = $canShell ? (string)@shell_exec('crontab -l 2>/dev/null') : '';
        $cron = $canShell ? str_contains($tab, 'cron_events.php') : null;
        $this->row($s, $cron ? 'OK' : 'WARN', 'cron_events.php в crontab', $cron === null ? 'не проверено (shell_exec выключен)' : ($cron ? 'есть' : 'нет (или crontab другого пользователя)'));
        $this->data['cron'] = ['constant' => $cronConst, 'crontab' => $cron];
        $cron = (bool)$cron;
        if (!class_exists('\Bitrix\Main\Application')) {
            return $cronConst && $cron;
        }
        try {
            $rows = \Bitrix\Main\Application::getConnection()->query("SELECT MODULE_ID, NAME, ACTIVE, NEXT_EXEC, LAST_EXEC FROM b_agent WHERE MODULE_ID LIKE 'shef.%' ORDER BY MODULE_ID, ID");
            $n = 0;
            while ($r = $rows->fetch()) {
                $n++;
                $name = (string)preg_replace('/\(.*$/s', '()', (string)$r['NAME']);
                $next = $r['NEXT_EXEC'] instanceof \DateTimeInterface || is_object($r['NEXT_EXEC']) ? (string)$r['NEXT_EXEC'] : (string)$r['NEXT_EXEC'];
                $this->row($s, $r['ACTIVE'] === 'Y' ? 'INFO' : 'WARN', (string)$r['MODULE_ID'], $name.', '.($r['ACTIVE'] === 'Y' ? 'активен' : 'выключен').', следующий '.$next);
                $this->data['agents'][] = ['module' => (string)$r['MODULE_ID'], 'name' => $name, 'active' => $r['ACTIVE'] === 'Y', 'next' => $next];
            }
            if ($n === 0) {
                $this->row($s, 'INFO', 'агенты shef.*', 'нет');
            }
        } catch (\Throwable $e) {
            $this->row($s, 'WARN', 'агенты', 'не прочитаны: '.get_class($e));
        }

        return $cronConst && $cron;
    }

    private function core(): void
    {
        $s = 'Правки ядра (shef.haschangefiles)';
        $cli = $this->root.'/bitrix/modules/shef.haschangefiles/cli/check-core-changes.php';
        if (!is_file($cli)) {
            $this->row($s, 'INFO', 'shef.haschangefiles', 'не установлен — правок ядра по учёту нет');
            $this->data['core'] = null;
            return;
        }
        // stderr — в никуда (полный канал не повесит процесс), окружение — своё плюс DOCUMENT_ROOT
        $proc = function_exists('proc_open') ? proc_open([PHP_BINARY, $cli], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['DOCUMENT_ROOT' => $this->root, 'PATH' => (string)getenv('PATH')]) : false;
        if (!is_resource($proc)) {
            $this->row($s, 'WARN', 'check-core-changes', 'не запустился');
            return;
        }
        $out = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $rc = proc_close($proc);
        // только «СТАТУС путь»: остаток строки (diff, текст файла) в отчёт не идёт
        $lines = [];
        foreach (explode("\n", $out) as $l) {
            if (preg_match('~^(OK|DRIFT|NO-MARKERS|NO-ORIGINAL)\s+(\S+)~', trim($l), $m)) {
                $lines[] = $m[1].' '.$m[2];
            }
        }
        $this->row($s, $rc === 0 ? 'OK' : 'WARN', 'check-core-changes', 'код '.$rc.', файлов '.count($lines));
        foreach ($lines as $l) {
            $this->row($s, str_starts_with($l, 'OK') ? 'OK' : 'WARN', '', substr(preg_replace('/[\x00-\x1f\x7f]/', '?', $l), 0, 200));
        }
        $this->data['core'] = ['rc' => $rc, 'files' => $lines];
    }

    /** @param array<string, ?string> $versions */
    private function rolloutSection(array $versions, bool $jsOk, bool $cron): void
    {
        $s = 'Выкатка '.$this->release;
        $sync = version_compare($versions['shef.options'] ?? '', '3.2.0', '>=');
        $r = self::rollout($versions, $this->targets, $sync, (bool)($this->data['git'] ?? false));
        foreach ($r['done'] as $l) {
            $this->row($s, 'OK', 'на целевой', $l);
        }
        foreach ($r['behind'] as $l) {
            $this->row($s, 'WARN', 'обновить', $l);
        }
        foreach ($r['unknown'] as $l) {
            $this->row($s, 'WARN', 'не проверено', $l);
        }
        $backup = glob(dirname($this->root).'/backup/toolsai-modules-*.tgz') ?: [];
        $this->row($s, $backup !== [] ? 'OK' : 'WARN', 'резервная копия модулей', $backup !== [] ? basename((string)end($backup)) : 'нет в '.dirname($this->root).'/backup');
        $this->row($s, $cron ? 'OK' : 'WARN', 'cron агентов', $cron ? 'есть' : 'проверить');
        $core = $this->data['core']['files'] ?? [];
        if ($core !== []) {
            $this->row($s, 'WARN', 'правки ядра', count($core).' — сверить после обновления Битрикса (check-core-changes)');
        }
        $this->row($s, $jsOk ? 'OK' : 'FAIL', '/bitrix/js = install/js', $jsOk ? 'совпадает' : 'расходится — см. раздел #336');
        $this->row($s, 'INFO', 'ПУТЬ ОБНОВЛЕНИЯ', $r['path']);
        $this->data['rollout'] = $r + ['release' => $this->release, 'targets' => $this->targets, 'jsMatch' => $jsOk, 'cron' => $cron, 'backup' => $backup !== [], 'coreChanges' => count($core)];
    }

    // ------------------------------------------------------------------ вывод

    public function text(): string
    {
        $out = 'Диагностика Tools AI — '.date('Y-m-d H:i:s').PHP_EOL;
        foreach ($this->sections as $title => $rows) {
            $out .= PHP_EOL.$title.PHP_EOL;
            foreach ($rows as [$level, $what, $value]) {
                $out .= '  '.str_pad($level, 6).$what.str_repeat(' ', max(1, 35 - (function_exists('mb_strlen') ? mb_strlen($what) : (int)preg_match_all('/./us', $what)))).$value.PHP_EOL;
            }
        }

        return self::redact($out, $this->secrets);
    }

    public function json(): string
    {
        // Машинная опора — data (ключи меняются только с format); sections — текст для человека
        $doc = ['format' => self::FORMAT, 'tool' => 'shef-diag', 'version' => self::VERSION, 'generated' => date('c'), 'seconds' => round(microtime(true) - $this->started, 1), 'data' => $this->data, 'sections' => $this->sections];

        return self::redact((string)json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE), array_map(
            static fn (string $s): string => substr((string)json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1),
            $this->secrets
        ));
    }

    /** Сборщик на поднятом ядре Битрикса. */
    public static function forPortal(string $root, bool $network, string $mcpDir, ?array $targets = null, string $release = self::RELEASE): self
    {
        $endpoint = static function (string $prefix): ?array {
            try {
                if (!\Bitrix\Main\Loader::includeModule('shef.toolsai') || !class_exists('\Shef\ToolsAi\Container')) {
                    return null;
                }
                $config = \Shef\ToolsAi\Container::getConfig();
                if ($prefix === 'web') {
                    $web = $config->getWebSearch();

                    return ['url' => '', 'key' => (string)$web->apiKey, 'model' => '', 'keyFrom' => $web->keySource === 'text' ? 'как у «Текст»' : ''];
                }
                $direction = ['llm' => 'text', 'asr' => 'audio'][$prefix] ?? $prefix;
                $ep = $config->getEndpoint($direction);

                return ['url' => (string)$ep->baseUrl, 'key' => (string)$ep->apiKey, 'model' => (string)$ep->model];
            } catch (\Throwable) {
                return null;
            }
        };
        $http = $network ? static function (string $url): array {
            if ($url === '') {
                return [0, 'нет адреса'];
            }
            if (!preg_match('~^https?://~i', $url)) {
                return [0, 'не http(s)'];
            }
            // без переходов: адрес из настроек не уводит запрос на чужие внутренние адреса
            $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true, 'method' => 'GET', 'follow_location' => 0], 'ssl' => ['verify_peer' => true]]);
            error_clear_last();
            $body = @file_get_contents($url, false, $ctx, 0, 65536);
            $code = 0;
            foreach ($http_response_header ?? [] as $h) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
                    $code = (int)$m[1];
                }
            }

            $err = (string)(error_get_last()['message'] ?? '');

            return [$code, $code === 0 ? 'нет ответа'.(str_contains($err, 'SSL') || str_contains($err, 'certificate') ? ' (ошибка TLS-сертификата)' : (str_contains($err, 'timed out') ? ' (таймаут)' : '')) : (string)$body];
        } : null;

        return new self(
            $root,
            static fn (string $id): array => array_map('strval', \Bitrix\Main\Config\Option::getForModule($id)),
            static fn (string $id): bool => \Bitrix\Main\ModuleManager::isModuleInstalled($id),
            $http,
            $mcpDir,
            $endpoint,
            $targets ?? self::TARGETS,
            $release,
        );
    }

    /**
     * Цели выкатки из --targets=файл.json: {"release": "…", "targets": {"модуль": "версия"}}.
     *
     * @return array{0: array<string, string>, 1: string}|null null — файл не читается или не тот
     */
    public static function loadTargets(string $file): ?array
    {
        $doc = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        if (!is_array($doc) || !is_array($doc['targets'] ?? null) || $doc['targets'] === []) {
            return null;
        }
        $targets = [];
        foreach ($doc['targets'] as $id => $v) {
            if (!is_string($id) || !is_string($v) || !preg_match('/^[a-z0-9.]+$/', $id) || !preg_match('/^\d+(\.\d+)*$/', $v)) {
                return null;
            }
            $targets[$id] = $v;
        }

        return [$targets, is_string($doc['release'] ?? null) ? $doc['release'] : basename($file)];
    }

    /** Значение --имя=… из argv. */
    public static function arg(array $argv, string $name): ?string
    {
        foreach ($argv as $a) {
            if (str_starts_with((string)$a, '--'.$name.'=')) {
                return substr((string)$a, strlen($name) + 3);
            }
        }

        return null;
    }

    /** Корень сайта одиночного файла: --root, DOCUMENT_ROOT, каталог модуля, /home/bitrix/www; '' — не найден. */
    public static function findRoot(array $argv): string
    {
        $root = rtrim((string)(self::arg($argv, 'root') ?? (getenv('DOCUMENT_ROOT') ?: '')), '/');
        if ($root === '') {
            // tools/diag.php внутри модуля: <корень>/bitrix/modules/shef.options/tools
            $guess = dirname(__DIR__, 4);
            $root = is_file($guess.'/bitrix/modules/main/include/prolog_before.php') ? $guess : '/home/bitrix/www';
        }

        return is_file($root.'/bitrix/modules/main/include/prolog_before.php') ? $root : '';
    }

    /** Отчёт одиночного файла после подъёма ядра. */
    public static function main(array $argv, string $root): int
    {
        $t = self::arg($argv, 'targets');
        $loaded = $t !== null ? self::loadTargets($t) : null;
        if ($t !== null && $loaded === null) {
            fwrite(STDERR, "--targets: файл не читается или не в формате {\"release\": \"…\", \"targets\": {\"shef.toolsai\": \"1.91.0\"}}\n");
            return 2;
        }
        $diag = self::forPortal($root, !in_array('--no-network', $argv, true), self::arg($argv, 'mcp-dir') ?? dirname($root).'/mcp', $loaded[0] ?? null, $loaded[1] ?? self::RELEASE)->collect();
        echo in_array('--json', $argv, true) ? $diag->json().PHP_EOL : $diag->text();

        return 0;
    }
}

// Одиночный запуск: ядро поднимается на уровне файла (глобальная область, как у bitrix.php и
// cron_events.php) — dbconn.php и init.php видят свои глобальные переменные.
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath((string)$argv[0]) === __FILE__) {
    $shefDiagRoot = Diag::findRoot($argv);
    if ($shefDiagRoot === '') {
        fwrite(STDERR, "Не найден корень сайта: укажите --root=/home/bitrix/www\n");
        exit(2);
    }
    if (function_exists('posix_geteuid') && posix_geteuid() === 0 && !in_array('--allow-root', $argv, true)) {
        fwrite(STDERR, "Запуск от root: права и владельцы файлов покажутся неверно, а код модулей исполнится от root. Запустите от пользователя веб-сервера (на BitrixVM — bitrix) или добавьте --allow-root.\n");
        exit(2);
    }
    $_SERVER['DOCUMENT_ROOT'] = $shefDiagRoot;
    // Ядро упало при подъёме (нет базы, сломан init.php) — хотя бы версии модулей с диска
    register_shutdown_function(static function () use ($shefDiagRoot): void {
        $e = error_get_last();
        if (!defined('SHEF_DIAG_DONE') && $e !== null && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            echo "Ядро Битрикса не поднялось — версии модулей с диска:\n";
            foreach (glob($shefDiagRoot.'/{bitrix,local}/modules/shef.*', GLOB_ONLYDIR | GLOB_BRACE) ?: [] as $dir) {
                echo '  '.basename($dir).' '.implode(' от ', array_filter(Diag::fileVersion($dir)))."\n";
            }
        }
    });
    foreach (['STOP_STATISTICS' => true, 'NO_KEEP_STATISTIC' => 'Y', 'NO_AGENT_STATISTIC' => 'Y', 'NOT_CHECK_PERMISSIONS' => true, 'NO_AGENT_CHECK' => true] as $shefDiagConst => $shefDiagValue) {
        if (!defined($shefDiagConst)) {
            define($shefDiagConst, $shefDiagValue);
        }
    }
    require_once $shefDiagRoot.'/bitrix/modules/main/include/prolog_before.php';
    $shefDiagCode = Diag::main($argv, $shefDiagRoot);
    define('SHEF_DIAG_DONE', true);
    exit($shefDiagCode);
}
