<?php

declare(strict_types=1);

/**
 * Общий журнал модулей (bx-shef/toolsai#612): маска секретов при записи и при выдаче в архив,
 * формат строки, срок 5 дней и потолок 20 МБ, сбой записи не роняет модуль.
 *
 * Ключи ниже — выдуманные тестовые значения. Настоящие логи порталов в тестах не используются.
 *
 * Мутация (проверено): `Mask::string()` возвращает текст без изменений — краснеют проверки
 * «ключ не попал в файл» и «ключ не попал в архив»; `tail()` без повторной маски — краснеет
 * «строка, записанная мимо журнала, в архиве под маской».
 */

require __DIR__.'/assert.php';
require dirname(__DIR__).'/lib/log/mask.php';
require dirname(__DIR__).'/lib/log/journal.php';

use Shef\Options\Log\Journal;
use Shef\Options\Log\Mask;

$base = sys_get_temp_dir().'/shef-journal-'.getmypid();
$now = gmmktime(10, 15, 2, 11, 4, 2026);
$clock = static function () use (&$now): int {
    return $now;
};

$key = 'sk-test-ABCDEFGHIJKLMNOPQRSTUV0123';
$bearer = 'Zm9vYmFyYmF6cXV4MTIzNDU2Nzg5MA';
$hook = 'https://example.test/rest/1/abcdef1234567890/';
$cert = 'BXL1.eyJ2IjoxLCJpZCI6IkwtVEVTVCJ9.c2lnbmF0dXJlLXRlc3Q';

Check::group('Mask');
Check::same('ключ провайдера', str_contains(Mask::string('key '.$key), $key), false);
Check::same('последние 4 знака видны', str_contains(Mask::string('x '.$key), '••••0123'), true);
Check::same('Bearer', str_contains(Mask::string('Authorization: Bearer '.$bearer), $bearer), false);
Check::same('вебхук Б24', Mask::string($hook), 'https://example.test/rest/1/••••/');
Check::same('сертификат лицензии', str_contains(Mask::string('cert='.$cert), 'c2lnbmF0dXJl'), false);
Check::same('query ?token=', Mask::string('https://a.test/x?token=secretvalue123&page=2'), 'https://a.test/x?token=••••e123&page=2');
Check::same('JSON "password"', str_contains(Mask::string('{"password":"hunter2hunter2"}'), 'hunter2'), false);
Check::same('логин:пароль в адресе', Mask::string('https://user:p4ssw0rd@db.test/'), 'https://user:••••@db.test/');
Check::same('обычный текст не трогаем', Mask::string('Модель ответила за 1200 мс, monkey: banana'), 'Модель ответила за 1200 мс, monkey: banana');
Check::same('JSON-строка с пробелами целиком', str_contains(Mask::string('{"password":"correct horse battery staple"}'), 'battery'), false);
Check::same('bearer в нижнем регистре', str_contains(Mask::string('hdr bearer '.$bearer), $bearer), false);
Check::same('имя через дефис', str_contains(Mask::string('license-key=LICENSE0123456789'), 'LICENSE0123'), false);
Check::same('«@» в пароле адреса', Mask::string('https://user:p@ss@db.test/'), 'https://user:••••@db.test/');
$leaks = static function (string $text, string $secret): bool {
    return str_contains(Mask::string($text), $secret);
};
Check::same('токены Б24: AUTH_ID, REFRESH_ID, auth[access_token], application_token', [
    $leaks('AUTH_ID=b24authTESTvalue01', 'b24authTEST'),
    $leaks('REFRESH_ID=b24refrTESTvalue02', 'b24refrTEST'),
    $leaks('auth[access_token]=b24accTESTvalue03&x=1', 'b24accTEST'),
    $leaks('auth[application_token]=b24appTESTvalue04', 'b24appTEST'),
], [false, false, false, false]);
Check::same('значение короче 4 знаков в начале и в кавычках', [
    $leaks('password=p&ss', 'p&ss') && $leaks('password=p&ss', 'p'),
    $leaks("password='a b c d'", 'a b c d'),
    $leaks('pass: "my secret pass"', 'secret pass'),
], [false, false, false]);
Check::same('print_r, var_export, экранированный JSON, XML, urlencode', [
    $leaks('[api_key] => printrTESTsecret01', 'printrTEST'),
    $leaks("'api_key' => 'varexpTESTsecret02'", 'varexpTEST'),
    $leaks('{\"token\":\"escTESTsecret03\"}', 'escTEST'),
    $leaks('<password>xmlTESTsecret04</password>', 'xmlTEST'),
    $leaks('client_secret%3DurlTESTsecret05', 'urlTEST'),
    $leaks('hook https://a.test/rest/7/ab_cd-ef1234/', 'ab_cd-ef1234'),
], [false, false, false, false, false, false]);
Check::same('аргументы в стеке исключения', Mask::string("#0 /w/lib/client.php(12): Client->login('admin', 'StackTESTpass1')\n#1 {main}"), "#0 /w/lib/client.php(12): Client->login(…)\n#1 {main}");
Check::same('OAuth-код в адресе под маской, member_id и код итога — нет', [
    $leaks('https://p.test/oauth?code=oauthTESTcode0123&state=1', 'oauthTESTcode'),
    Mask::string('member_id=abc123def456'),
    Mask::context(['code' => 'timeout']),
], [false, 'member_id=abc123def456', ['code' => 'timeout']]);
$t = microtime(true);
Mask::string(str_repeat('a-', 200000));
Check::same('400 КБ «a-a-a…» — без квадратичного времени (< 2 с)', microtime(true) - $t < 2.0, true);
Check::same('сбой PCRE — строка скрыта, а не открыта', str_contains(Mask::string("password=\xff\xfe".$key), $key), false);
Check::same('контекст по имени поля', Mask::context(['api_key' => $key, 'n' => 5, 'deep' => ['access_token' => 'short']]), ['api_key' => '••••0123', 'n' => 5, 'deep' => ['access_token' => '••••']]);

Check::group('запись');
$j = new Journal('shef.toolsai', $base, $clock);
$j->error('license', "Ответ\nсервера: ".$key, ['header' => 'Bearer '.$bearer, 'token' => $key, 'url' => $hook, 'len' => 42]);
$file = $base.'/shef.toolsai/2026-11-04.log';
$raw = (string)@file_get_contents($file);
Check::same('файл по дню', is_file($file), true);
Check::same('ключ не попал в файл', [str_contains($raw, $key), str_contains($raw, $bearer), str_contains($raw, 'abcdef1234567890')], [false, false, false]);
Check::same('формат строки', (bool)preg_match('/^2026-11-04T10:15:02Z \| ERROR \| shef\.toolsai \| license \| Ответ\\\\nсервера: ••••0123 \| \{.*"len":42\}\n$/u', $raw), true);
Check::same('каталог закрыт .htaccess', is_file($base.'/.htaccess'), true);
Check::throws('id модуля с «..» отвергается', InvalidArgumentException::class, static fn () => new Journal('../etc', $base));

Check::group('архив: маска повторно');
file_put_contents($file, 'запись мимо журнала: api_key='.$key."\n", FILE_APPEND);
$tail = $j->tail();
Check::same('строка, записанная мимо журнала, в архиве под маской', str_contains($tail, $key), false);
Check::same('остальное на месте', str_contains($tail, '| license |'), true);
Check::same('обрезка отмечена', str_starts_with($j->tail(60), '# журнал обрезан'), true);

Check::group('срок 5 дней и потолок');
foreach (['2026-10-29', '2026-10-30', '2026-10-31'] as $d) {
    file_put_contents($base.'/shef.toolsai/'.$d.'.log', "old\n");
}
$j->rotate();
Check::same('старше 5 дней удалены, 31.10 — пятый день — остался', array_keys($j->files()), ['2026-10-31', '2026-11-04']);
$big = str_repeat(str_repeat('x', 1023)."\n", 11 * 1024);
file_put_contents($base.'/shef.toolsai/2026-11-01.log', $big);
file_put_contents($base.'/shef.toolsai/2026-11-02.log', $big);
$j->rotate();
$total = array_sum(array_map('filesize', $j->files()));
Check::same('потолок 20 МБ соблюдён, ушли самые старые', [$total <= Journal::MAX_BYTES, array_key_first($j->files())], [true, '2026-11-02']);

Check::group('сбой записи');
$bad = $base.'/file-not-dir';
file_put_contents($bad, '');
(new Journal('shef.toolsai', $bad, $clock))->info('t', 'm');
Check::same('каталог недоступен — молча, без исключения и без файлов', is_dir($bad.'/shef.toolsai'), false);

exec((DIRECTORY_SEPARATOR === '\\' ? 'rmdir /s /q ' : 'rm -rf ').escapeshellarg($base));
Check::finish();
