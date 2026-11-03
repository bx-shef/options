<?php

declare(strict_types=1);

/**
 * tools/diag.php и shef:modules:diag (issue #70): диагностика портала без ядра.
 *
 * Что держит: секреты (ключи направлений, .env MCP) не попадают ни в текст, ни в JSON; адрес — без
 * логина и параметров; локальный адрес отличается от внешнего; направление берёт общий адрес и ключ,
 * если своего нет; версия модуля из install/version.php; /bitrix/js против install/js (#336);
 * версия shef.cowork по /health; путь обновления по целям выкатки.
 */

$root = dirname(__DIR__);
require_once $root.'/tests/assert.php';
require_once $root.'/tools/diag.php';

use Shef\Options\Tools\Diag;

Check::group('чистые функции');
Check::same('секретное имя: ключ', Diag::isSecretName('API_llmapikey'), true);
Check::same('секретное имя: токен', Diag::isSecretName('SYS_token'), true);
Check::same('не секрет: модель', Diag::isSecretName('API_llmmodel'), false);
Check::same('адрес без логина и параметров', Diag::cleanUrl('https://u:p@api.x.ru:8443/v1?key=S#a'), 'https://api.x.ru:8443/v1');
Check::same('не адрес — пусто', Diag::cleanUrl('sk-123'), '');
Check::same('localhost — локальный', Diag::isLocal('http://localhost:11434/v1'), true);
Check::same('частная сеть — локальный', Diag::isLocal('http://192.168.1.5/v1'), true);
Check::same('имя docker-сервиса — локальный', Diag::isLocal('http://file-extract:3000'), true);
Check::same('внешний — не локальный', Diag::isLocal('https://api.deepseek.com/v1'), false);
Check::same('health MCP', Diag::healthUrl('https://mcp.x.ru/mcp?t=1'), 'https://mcp.x.ru/health');
Check::same('health MCP за префиксом прокси', Diag::healthUrl('https://portal.ru/mcp-ai'), 'https://portal.ru/mcp-ai/health');
Check::same('health сервиса /v1', Diag::healthUrl('http://toolsai-embed:8000/v1'), 'http://toolsai-embed:8000/health');
$d = Diag::direction(['api_baseurl' => 'http://asr:8000/v1', 'api_apikey' => 'A', 'api_llmbaseurl' => 'https://api.deepseek.com/v1', 'api_llmapikey' => 'T'], 'light');
Check::same('лёгкая без своего — как у текста, не общий', [$d['url'], $d['urlShared'], $d['urlFrom'], $d['keyFrom']], ['https://api.deepseek.com/v1', true, 'как у «Текст»', 'как у «Текст»']);
$d = Diag::direction(['api_baseurl' => 'http://asr:8000/v1', 'api_llmbaseurl' => 'https://api.deepseek.com/v1'], 'asr');
Check::same('распознавание без своего — общий', $d['url'], 'http://asr:8000/v1');

$d = Diag::direction(['API_baseurl' => 'https://api.a.ru/v1', 'API_apikey' => 'S', 'API_lightmodel' => 'mini', 'DEF_providertext' => 'openai'], 'light');
Check::same('направление без своего адреса — общий', [$d['url'], $d['urlShared'], $d['key'], $d['keyShared'], $d['model']], ['https://api.a.ru/v1', true, true, true, 'mini']);
$d = Diag::direction(['API_embedbaseurl' => 'http://127.0.0.1:8080/v1', 'API_embedpresetmodel' => 'bge-m3'], 'embed');
Check::same('своё направление, без ключа, локальное', [$d['urlShared'], $d['key'], $d['local'], $d['model']], [false, false, true, 'bge-m3']);

$d = Diag::direction(['api_lightbaseurl' => 'http://toolsai-light:8000/v1', 'api_lightmodel' => 'qwen'], 'light');
Check::same('имена настроек строчными, как хранит ядро', [$d['url'], $d['model'], $d['urlShared']], ['http://toolsai-light:8000/v1', 'qwen', false]);
$d = Diag::direction(['api_llmbaseurl' => 'https://api.deepseek.com/v1', 'api_llmapikey' => 'T', 'api_lightbaseurl' => 'http://toolsai-light:8000/v1'], 'light');
Check::same('свой адрес без ключа — ключ «Текст» не наследуется', [$d['key'], $d['keyFrom']], [false, '']);
$d = Diag::direction(['api_baseurl' => 'http://asr:8000/v1', 'api_apikey' => 'A', 'api_dealpreset' => 'deepseek'], 'deal');
Check::same('пресет без адреса — имя пресета, ключ не наследуется', [$d['urlFrom'], $d['key']], ['пресет deepseek', false]);
$d = Diag::direction(['api_baseurl' => 'http://asr:8000/v1', 'api_apikey' => 'A', 'api_webapikey' => 'W'], 'web');
Check::same('поиск в интернете — без адреса, свой ключ', [$d['url'], $d['key'], $d['local']], ['', true, false]);
Check::same('короткие и служебные значения не вычищаются', Diag::redact('installed: true, port 3000', ['true', '3000', 'Y']), 'installed: true, port 3000');
Check::same('неизвестная версия — «не проверено», не «отстаёт»', Diag::rollout(['shef.options' => '3.9.0', 'shef.toolsai' => '1.91.0', 'shef.cowork' => null], ['shef.options' => '3.9.0', 'shef.toolsai' => '1.91.0', 'shef.cowork' => '1.11.0'], true, false), ['path' => 'не определён: модули на целевых, не проверено — shef.cowork', 'route' => 'unknown', 'method' => 'sync', 'behind' => [], 'done' => ['shef.options 3.9.0', 'shef.toolsai 1.91.0'], 'unknown' => ['shef.cowork — версия не проверена, цель 1.11.0']]);
Check::same('коды пути: none / fresh / modules+mcp, способ git/cp', [
    Diag::rollout(['shef.toolsai' => '2.0.0'], ['shef.toolsai' => '1.91.0'], false, false)['route'],
    Diag::rollout(['shef.options' => '3.9.0'], ['shef.toolsai' => '1.91.0'], false, false)['route'],
    Diag::rollout(['shef.toolsai' => '1.0.0', 'shef.cowork' => '1.0.0'], ['shef.toolsai' => '1.91.0', 'shef.cowork' => '1.11.0'], false, true)['route'],
    Diag::rollout(['shef.toolsai' => '1.0.0'], ['shef.toolsai' => '1.91.0'], false, true)['method'],
    Diag::rollout(['shef.toolsai' => '1.0.0'], ['shef.toolsai' => '1.91.0'], false, false)['method'],
], ['none', 'fresh', 'modules+mcp', 'git', 'cp']);
Check::same('секретные имена: pass, webhook, auth', [Diag::isSecretName('DB_PASSWORD'), Diag::isSecretName('B24_WEBHOOK'), Diag::isSecretName('X_AUTH')], [true, true, true]);
Check::same('токен в пути адреса — ***', Diag::cleanUrl('https://portal.ru/rest/1/abc123def456ghi789/'), 'https://portal.ru/rest/1/***/');
Check::same('обычный путь не трогается', Diag::cleanUrl('https://api.deepseek.com/v1/chat/completions'), 'https://api.deepseek.com/v1/chat/completions');
Check::same('IPv6 loopback — локальный', Diag::isLocal('http://[::1]:8080/v1'), true);
$d = Diag::direction(['api_llmbaseurl' => 'https://api.deepseek.com/v1', 'api_llmapikey' => 'T'], 'web');
Check::same('поиск без своего ключа на DeepSeek — ключ «Текст»', [$d['key'], $d['keyFrom']], [true, 'как у «Текст»']);
$d = Diag::direction(['api_llmbaseurl' => 'https://api.openai.com/v1', 'api_llmapikey' => 'T'], 'web');
Check::same('поиск без своего ключа не на DeepSeek — ключа нет', $d['key'], false);
$tf = sys_get_temp_dir().'/shef-diag-targets-'.getmypid().'.json';
file_put_contents($tf, '{"release":"2026-12-01","targets":{"shef.toolsai":"1.95.0"}}');
Check::same('--targets: свои цели и метка выкатки', Diag::loadTargets($tf), [['shef.toolsai' => '1.95.0'], '2026-12-01']);
file_put_contents($tf, '{"targets":{"shef.toolsai":"1.95; rm"}}');
Check::same('--targets: мусор в версии — отказ', Diag::loadTargets($tf), null);
unlink($tf);
$t = ['shef.options' => '3.9.0', 'shef.toolsai' => '1.91.0', 'shef.cowork' => '1.11.0'];
Check::same('всё на цели', Diag::rollout(['shef.options' => '3.9.0', 'shef.toolsai' => '1.91.1', 'shef.cowork' => '1.11.0'], $t, true, false)['path'], 'обновление не нужно: всё на целевых версиях');
Check::same('нет toolsai — новая установка', str_starts_with(Diag::rollout([], $t, false, false)['path'], 'новая установка'), true);
$r = Diag::rollout(['shef.options' => '3.8.0', 'shef.toolsai' => '1.91.0', 'shef.cowork' => '1.7.3'], $t, true, false);
Check::same('отстают модули и MCP', [str_starts_with($r['path'], 'модули и MCP: файлы модулей поверх, затем php bitrix.php shef:modules:sync'), $r['behind']], [true, ['shef.options 3.8.0 → 3.9.0', 'shef.cowork 1.7.3 → 1.11.0']]);
Check::same('только MCP', Diag::rollout(['shef.options' => '3.9.0', 'shef.toolsai' => '1.91.0', 'shef.cowork' => ''], $t, true, true)['path'], 'только MCP (make в /home/bitrix/mcp)');
Check::same('портал в git', str_contains(Diag::rollout(['shef.options' => '3.8.0', 'shef.toolsai' => '1.9.0'], $t, true, true)['path'], 'tools/layout.php'), true);

$now = 1_800_000_000;
$mu = 'https://p.ru/mcp-ai/mcp';
$lic = static fn (string $status, ?int $exp, string $server = 'https://p.ru/mcp-ai/mcp'): string => (string)json_encode(['status' => $status, 'expiresAt' => $exp, 'server' => $server]);
Check::same('лицензия shef.cowork: действует, срок и дни', Diag::coworkLicense($lic('active', $now + 10 * 86400), $now, $mu), ['status' => 'active', 'expiresAt' => $now + 10 * 86400, 'daysLeft' => 10, 'otherServer' => false]);
Check::same('лицензия shef.cowork: нет записи или пустой статус — null', [Diag::coworkLicense('', $now, $mu), Diag::coworkLicense('{"status":"активна"}', $now, $mu)], [null, null]);
Check::same('лицензия shef.cowork: статус без мусора, истёкшая — 0 дней', [Diag::coworkLicense($lic('expired<x>', $now - 86400 * 5), $now, $mu)['status'], Diag::coworkLicense($lic('expired', $now - 86400 * 5), $now, $mu)['daysLeft']], ['expiredx', 0]);
Check::same('лицензия shef.cowork: запись другого сервера', Diag::coworkLicense($lic('active', null, 'https://old.ru/mcp'), $now, $mu)['otherServer'], true);
Check::same('лицензия shef.cowork: тот же сервер без /mcp', Diag::coworkLicense($lic('active', null, 'https://p.ru/mcp-ai'), $now, $mu)['otherServer'], false);
$lv = static fn (string $status, ?int $exp, string $server = 'https://p.ru/mcp-ai/mcp'): string => Diag::coworkLevel(Diag::coworkLicense($lic($status, $exp, $server), $now, $mu), $now);
Check::same('уровни лицензии shef.cowork', [
    $lv('active', null), $lv('active', $now + 90 * 86400), $lv('active', $now + 10 * 86400), $lv('active', $now - 60),
    $lv('expired', null), $lv('unconfigured', null), $lv('active', null, 'https://old.ru/mcp'), Diag::coworkLevel(null, $now),
], ['OK', 'OK', 'WARN', 'FAIL', 'FAIL', 'INFO', 'WARN', 'WARN']);
Check::group('отчёт по порталу-заглушке');
$p = sys_get_temp_dir().'/shef-diag-'.getmypid();
$www = $p.'/www';
$mod = $www.'/bitrix/modules/shef.toolsaiassistant';
mkdir($mod.'/install/js/shef/toolsaiassistant', 0755, true);
mkdir($www.'/bitrix/js/shef/toolsaiassistant', 0755, true);
mkdir($www.'/bitrix/modules/shef.toolsai/install', 0755, true);
mkdir($p.'/mcp', 0755, true);
mkdir($www.'/local/modules/shef.toolsaikb/install/js/shef/kb', 0755, true);
file_put_contents($www.'/local/modules/shef.toolsaikb/install/js/shef/kb/k.js', 'kb');
file_put_contents($mod.'/install/version.php', '<?php $arModuleVersion = [\'VERSION\' => \'1.95.1\', \'VERSION_DATE\' => \'2026-11-01 10:00:00\'];');
file_put_contents($www.'/bitrix/modules/shef.toolsai/install/version.php', '<?php $arModuleVersion = [\'VERSION\' => \'1.87.1\', \'VERSION_DATE\' => \'2026-10-20 10:00:00\'];');
file_put_contents($mod.'/install/js/shef/toolsaiassistant/a.js', 'new');
file_put_contents($mod.'/install/js/shef/toolsaiassistant/b.js', 'same');
file_put_contents($www.'/bitrix/js/shef/toolsaiassistant/a.js', 'old');
file_put_contents($www.'/bitrix/js/shef/toolsaiassistant/b.js', 'same');
file_put_contents($p.'/mcp/.env', "TRUST_PROXY=true\nPORT=3000\nMCP_IMAGE=robot:regtoken-SECRET@ghcr.io/bx-shef/toolsai-mcp:1.7.3\nREGISTRY_TOKEN=regtoken-SECRET\nB24_CLIENT_SECRET=\"mcp-secret-value\"\nFILE_EXTRACT_KEY=extract-secret-value\nFILE_EXTRACT_URL=http://file-extract:3000\n");

mkdir($www.'/bitrix/modules/shef.toolsaieval/install', 0755, true);
file_put_contents($www.'/bitrix/modules/shef.toolsaieval/install/version.php', '<?php $arModuleVersion = [');
Check::same('битый version.php — «?», отчёт не падает', Diag::fileVersion($www.'/bitrix/modules/shef.toolsaieval'), ['?', '']);
$m = Diag::jsMatch($mod, $www);
Check::same('js: одинаковый и отличающийся', [$m['same'], $m['differ'], $m['missing']], [1, ['shef/toolsaiassistant/a.js'], []]);
Check::same('версия из version.php', Diag::fileVersion($mod), ['1.95.1', '2026-11-01 10:00:00']);

$secret = 'sk-live-SECRET/1234"567890';
$opts = [
    'shef.toolsai' => [
        'DEF_providertext' => 'openai', 'DEF_provideraudio' => 'openai',
        'API_baseurl' => 'https://user:pw@api.deepseek.com/v1?token=abc', 'API_apikey' => $secret,
        'API_llmmodel' => 'deepseek-chat', 'API_asrbaseurl' => 'https://asr.example.ru/v1', 'SYS_token' => 'endpoint-token-xyz',
        'API_embedbaseurl' => 'http://127.0.0.1:8080/v1', 'API_embedmodel' => 'bge-m3',
        'API_lightmodel' => $secret,
    ],
    'shef.toolsaiassistant' => ['agent_coworkurl' => 'https://landing.example/agent.html', 'agent_mcpurl' => 'https://mcp.portal.ru/mcp?key=zzz'],
];
$calls = [];
$diag = new Diag(
    $www,
    static fn (string $id): array => $opts[$id] ?? [],
    static fn (string $id): bool => in_array($id, ['shef.toolsai', 'shef.toolsaiassistant'], true),
    static function (string $url) use (&$calls): array {
        $calls[] = $url;
        return match (true) {
            $url === 'http://127.0.0.1:8080/health' => [200, '{"version":"0.1.0"}'],
            str_ends_with($url, '/health') => [200, '{"version":"1.11.0","ready":true}'],
            default => [401, ''],
        };
    },
    $p.'/mcp',
);
$diag->collect();
$text = $diag->text();
$json = $diag->json();
// $secret стоит и в поле модели (не секретное имя): чистка по значению обязана его поймать.
foreach ([$secret, json_encode($secret), 'regtoken-SECRET', 'endpoint-token-xyz', 'mcp-secret-value', 'extract-secret-value', 'pw@', 'token=abc', 'key=zzz'] as $s) {
    $s = trim((string)$s, '"');
    Check::same('секрета нет в тексте: '.$s, str_contains($text, $s), false);
    Check::same('секрета нет в JSON: '.$s, str_contains($json, $s), false);
}
$doc = json_decode($json, true);
Check::same('JSON разбирается, формат 1', $doc['format'] ?? null, 1);
Check::same('cowork: версия по /health', $doc['data']['cowork']['version'] ?? null, '1.11.0');
Check::same('cowork: адрес без ключа', $doc['data']['cowork']['url'] ?? null, 'https://mcp.portal.ru/mcp');
Check::same('ключ текста — задан', $doc['data']['directions']['llm']['key'] ?? null, true);
Check::same('образ MCP из .env', $doc['data']['mcpEnv']['MCP_IMAGE'] ?? null, 'ghcr.io/bx-shef/toolsai-mcp:1.7.3');
Check::same('локальные эмбеддинги проверены', [in_array('http://127.0.0.1:8080/v1/models', $calls, true), $doc['data']['directions']['embed']['health'] ?? null], [true, 401]);
Check::same('внешний движок не опрашивается', (bool)array_filter($calls, static fn (string $u): bool => str_contains($u, 'deepseek')), false);
Check::same('модуль из local/modules сверяется', $doc['data']['js']['shef.toolsaikb']['missing'] ?? null, ['shef/kb/k.js']);
Check::same('JSON цел при TRUST_PROXY=true в .env', ($doc['data']['modules']['shef.toolsai']['installed'] ?? null), true);
Check::same('js расходится — шаг #336 FAIL', $doc['data']['rollout']['jsMatch'] ?? null, false);
Check::same('toolsai 1.87.1 отстаёт', in_array('shef.toolsai 1.87.1 → 1.91.0', $doc['data']['rollout']['behind'] ?? [], true), true);
Check::same('в тексте есть путь обновления', str_contains($text, 'ПУТЬ ОБНОВЛЕНИЯ'), true);
Check::same('модели видны (не секрет)', [str_contains($text, 'модель deepseek-chat'), str_contains($text, 'модель bge-m3')], [true, true]);
Check::same('сервис эмбеддингов: /health и версия', $doc['data']['directions']['embed']['service'] ?? null, ['health' => 200, 'version' => '0.1.0']);
Check::same('распознавание со своим адресом — /health не опрашивается', isset($doc['data']['directions']['asr']['service']), false);
Check::same('лёгкая на общем адресе — сервис не опрашивается', isset($doc['data']['directions']['light']['service']), false);
Check::same('уровни: лёгкая на «Текст» — WARN, свой сервис эмбеддингов — INFO', [str_contains($text, "WARN  Лёгкая модель"), str_contains($text, "INFO  Эмбеддинги")], [true, true]);
Check::same('модуль только файлами — не в выкатке как установленный', in_array('shef.toolsaieval — → 1.16.0', $doc['data']['rollout']['behind'] ?? [], true), true);
Check::same('JSON: версия скрипта и цели выкатки', [$doc['version'] ?? null, $doc['data']['rollout']['release'] ?? null, $doc['data']['rollout']['route'] ?? null], [Diag::VERSION, Diag::RELEASE, 'modules']);
Check::same('лицензия shef.cowork без записи — строка «срок неизвестен»', str_contains($text, 'shef.cowork (MCP)') && str_contains($text, 'срок неизвестен'), true);
Check::same('в тексте «ключ задан»', str_contains($text, 'ключ задан'), true);

$noNet = new Diag($www, static fn (string $id): array => $opts[$id] ?? [], static fn (string $id): bool => $id === 'shef.toolsaiassistant');
Check::same('--no-network: /health не вызывается', str_contains($noNet->collect()->text(), 'не проверялось (--no-network)'), true);
$noNetDoc = json_decode($noNet->json(), true);
Check::same('--no-network: shef.cowork «не проверено», не в «обновить»', [$noNetDoc['data']['rollout']['unknown'] ?? null, (bool)array_filter($noNetDoc['data']['rollout']['behind'] ?? [], static fn (string $l): bool => str_starts_with($l, 'shef.cowork'))], [['shef.cowork — версия не проверена, цель 1.11.0'], false]);
$d = Diag::direction(['api_llmbaseurl' => 'https://api.deepseek.com/v1/', 'api_llmapikey' => 'T', 'api_assistantbaseurl' => 'https://API.deepseek.com/v1'], 'assistant');
Check::same('свой адрес, совпадающий с «Текст», — ключ наследуется', [$d['key'], $d['keyFrom']], [true, 'как у «Текст»']);

Check::group('лимит сети и --fast');
$slow = [];
$budget = new Diag(
    $www,
    static fn (string $id): array => $opts[$id] ?? [],
    static fn (string $id): bool => in_array($id, ['shef.toolsai', 'shef.toolsaiassistant'], true),
    static function (string $url) use (&$slow): array {
        $slow[] = $url;
        usleep(60000);
        return [200, '{}'];
    },
    '',
    null,
    Diag::TARGETS,
    Diag::RELEASE,
    0.1,
);
$budgetText = $budget->collect()->text();
Check::same('лимит 0.1 с при 0.06 с на запрос — запросов 2', count($slow), 2);
Check::same('остальное помечено «лимит сети исчерпан»', str_contains($budgetText, 'лимит сети 0.1 с исчерпан'), true);
mkdir($www.'/bitrix/js/shef/deep/a/b', 0755, true);
$fast = new Diag($www, static fn (string $id): array => [], static fn (string $id): bool => false, null, '', null, Diag::TARGETS, Diag::RELEASE, Diag::NET_BUDGET, true);
Check::same('--fast: check-core-changes не запускается', str_contains($fast->collect()->text(), 'не проверялось (--fast)'), true);
Check::same('сеть по умолчанию — 20 с', Diag::NET_BUDGET, 20.0);

exec('rm -rf '.escapeshellarg($p));
Check::group('пределы записи звонка (toolsai-mp3-txt#33)');
Check::same('свой сервер, умолчания ядра — WARN', Diag::crmAudioLimits('', '', true)[0], 'WARN');
Check::same('свой сервер, подняты — OK', Diag::crmAudioLimits('7200', (string)(64 * 1024 * 1024), true), ['OK', 'ядро отдаёт звонки до 7200 с и 64 МБ']);
Check::same('не свой сервер — INFO', Diag::crmAudioLimits('', '', false)[0], 'INFO');
Check::same('memory_limit', [Diag::memoryLevel('256M')[0], Diag::memoryLevel('512M')[0], Diag::memoryLevel('-1')[0], Diag::memoryLevel('1G')[0]], ['WARN', 'OK', 'OK', 'OK']);
Check::same('memoryBytes', [Diag::memoryBytes('131072k'), Diag::memoryBytes('536870912'), Diag::memoryBytes('')], [128 * 1024 ** 2, 536870912, null]);

Check::finish();
