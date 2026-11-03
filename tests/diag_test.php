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

$d = Diag::direction(['API_baseurl' => 'https://api.a.ru/v1', 'API_apikey' => 'S', 'API_lightmodel' => 'mini', 'DEF_providertext' => 'openai'], 'light');
Check::same('направление без своего адреса — общий', [$d['url'], $d['urlShared'], $d['key'], $d['keyShared'], $d['model']], ['https://api.a.ru/v1', true, true, true, 'mini']);
$d = Diag::direction(['API_embedbaseurl' => 'http://127.0.0.1:8080/v1', 'API_embedpresetmodel' => 'bge-m3'], 'embed');
Check::same('своё направление, без ключа, локальное', [$d['urlShared'], $d['key'], $d['local'], $d['model']], [false, false, true, 'bge-m3']);

$t = ['shef.options' => '3.9.0', 'shef.toolsai' => '1.91.0', 'shef.cowork' => '1.11.0'];
Check::same('всё на цели', Diag::rollout(['shef.options' => '3.9.0', 'shef.toolsai' => '1.91.1', 'shef.cowork' => '1.11.0'], $t, true, false)['path'], 'обновление не нужно: всё на целевых версиях');
Check::same('нет toolsai — новая установка', str_starts_with(Diag::rollout([], $t, false, false)['path'], 'новая установка'), true);
$r = Diag::rollout(['shef.options' => '3.8.0', 'shef.toolsai' => '1.91.0', 'shef.cowork' => '1.7.3'], $t, true, false);
Check::same('отстают модули и MCP', [str_starts_with($r['path'], 'модули и MCP: файлы модулей поверх, затем php bitrix.php shef:modules:sync'), $r['behind']], [true, ['shef.options 3.8.0 → 3.9.0', 'shef.cowork 1.7.3 → 1.11.0']]);
Check::same('только MCP', Diag::rollout(['shef.options' => '3.9.0', 'shef.toolsai' => '1.91.0', 'shef.cowork' => ''], $t, true, true)['path'], 'только MCP (make в /home/bitrix/mcp)');
Check::same('портал в git', str_contains(Diag::rollout(['shef.options' => '3.8.0', 'shef.toolsai' => '1.9.0'], $t, true, true)['path'], 'tools/layout.php'), true);

Check::group('отчёт по порталу-заглушке');
$p = sys_get_temp_dir().'/shef-diag-'.getmypid();
$www = $p.'/www';
$mod = $www.'/bitrix/modules/shef.toolsaiassistant';
mkdir($mod.'/install/js/shef/toolsaiassistant', 0755, true);
mkdir($www.'/bitrix/js/shef/toolsaiassistant', 0755, true);
mkdir($www.'/bitrix/modules/shef.toolsai/install', 0755, true);
mkdir($p.'/mcp', 0755, true);
file_put_contents($mod.'/install/version.php', '<?php $arModuleVersion = [\'VERSION\' => \'1.95.1\', \'VERSION_DATE\' => \'2026-11-01 10:00:00\'];');
file_put_contents($www.'/bitrix/modules/shef.toolsai/install/version.php', '<?php $arModuleVersion = [\'VERSION\' => \'1.87.1\', \'VERSION_DATE\' => \'2026-10-20 10:00:00\'];');
file_put_contents($mod.'/install/js/shef/toolsaiassistant/a.js', 'new');
file_put_contents($mod.'/install/js/shef/toolsaiassistant/b.js', 'same');
file_put_contents($www.'/bitrix/js/shef/toolsaiassistant/a.js', 'old');
file_put_contents($www.'/bitrix/js/shef/toolsaiassistant/b.js', 'same');
file_put_contents($p.'/mcp/.env', "MCP_IMAGE=ghcr.io/bx-shef/toolsai-mcp:1.7.3\nB24_CLIENT_SECRET=\"mcp-secret-value\"\nFILE_EXTRACT_KEY=extract-secret-value\nFILE_EXTRACT_URL=http://file-extract:3000\n");

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
    'shef.toolsaiassistant' => ['AGENT_coworkurl' => 'https://mcp.portal.ru/mcp?key=zzz'],
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
foreach ([$secret, json_encode($secret), 'endpoint-token-xyz', 'mcp-secret-value', 'extract-secret-value', 'pw@', 'token=abc', 'key=zzz'] as $s) {
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
Check::same('js расходится — шаг #336 FAIL', $doc['data']['rollout']['jsMatch'] ?? null, false);
Check::same('toolsai 1.87.1 отстаёт', in_array('shef.toolsai 1.87.1 → 1.91.0', $doc['data']['rollout']['behind'] ?? [], true), true);
Check::same('в тексте есть путь обновления', str_contains($text, 'ПУТЬ ОБНОВЛЕНИЯ'), true);
Check::same('модели видны (не секрет)', [str_contains($text, 'модель deepseek-chat'), str_contains($text, 'модель bge-m3')], [true, true]);
Check::same('сервис эмбеддингов: /health и версия', $doc['data']['directions']['embed']['service'] ?? null, ['health' => 200, 'version' => '0.1.0']);
Check::same('распознавание со своим адресом — /health не опрашивается', isset($doc['data']['directions']['asr']['service']), false);
Check::same('лёгкая на общем адресе — сервис не опрашивается', isset($doc['data']['directions']['light']['service']), false);
Check::same('в тексте «ключ задан»', str_contains($text, 'ключ задан'), true);

$noNet = new Diag($www, static fn (string $id): array => $opts[$id] ?? [], static fn (string $id): bool => $id === 'shef.toolsaiassistant');
Check::same('--no-network: /health не вызывается', str_contains($noNet->collect()->text(), 'не проверялось (--no-network)'), true);

exec('rm -rf '.escapeshellarg($p));
Check::finish();
