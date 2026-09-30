<?php declare(strict_types=1);

/**
 * Подключение модуля даёт то, чем модуль предлагает пользоваться.
 *
 * Трейт \Shef\Options\TraitList\Log, а с ним Integration\AEvents и
 * Integration\IBlock\AEntity зовут глобальную _log(). Объявлена она в
 * def-functions.php, и этот файл долго ехал в поставке, не подключаясь
 * ниоткуда: include.php требовал только autoload.php и register-js.php.
 * На портале это выглядело так, что трейт из документации падает с «Call to
 * undefined function _log()» у первого же, кто им воспользовался.
 *
 * Сторож ловит именно разрыв «файл в поставке есть, а функции нет»: он
 * подключает настоящий include.php и спрашивает функции, а не ищет строку
 * require в исходнике. Уберут подключение — тест покраснеет, даже если
 * def-functions.php останется на месте.
 *
 * Ядро подменяется заглушками, файлы модуля подключаются настоящие.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';

// region Заглушка ядра ////
/**
 * От CJSCore нужен только перехват регистрации: register-js.php выполняется
 * из include.php, и без этого класса подключение модуля просто упало бы.
 */
class CJSCore
{
	/** @var array<string, array> */
	public static array $registered = [];

	public static function RegisterExt(string $name, array $params): void
	{
		static::$registered[$name] = $params;
	}
}

// Configuration отдаёт значения настоящего .settings.php — ровно то, что
// читает autoload.php модуля. Ключи у модуля пусты, так что до Loader дело не
// дойдёт, но читаются они из файла, а не из выдуманного массива.
\Bitrix\Main\Config\Configuration::$settings = require $root.'/.settings.php';

// endregion ////

Check::group('функции до подключения модуля');

// Именно «до»: иначе тест не отличил бы «include.php их объявил» от «они уже
// были объявлены кем-то другим».
Check::same('_log ещё нет', function_exists('_log'), false);
Check::same('_log1 ещё нет', function_exists('_log1'), false);
Check::same('_pr ещё нет', function_exists('_pr'), false);

require_once $root.'/include.php';

Check::group('после подключения модуля');

Check::same('_log объявлена', function_exists('_log'), true);
Check::same('_log1 объявлена', function_exists('_log1'), true);
Check::same('_pr объявлена', function_exists('_pr'), true);

Check::group('сигнатуры те, что зовёт трейт Log');

// Log::log() зовёт _log($value, static::getLogFile()) — массив и строка.
$log = new ReflectionFunction('_log');
Check::same('_log принимает два аргумента', $log->getNumberOfParameters(), 2);
Check::same('первый — массив', (string)$log->getParameters()[0]->getType(), 'array');
Check::same('второй — строка', (string)$log->getParameters()[1]->getType(), 'string');
Check::same('оба со значением по умолчанию', $log->getNumberOfRequiredParameters(), 0);

Check::group('register-js.php всё ещё отрабатывает');

// include.php подключает три файла; проверяем, что добавленный не сломал
// остальные — расширение зарегистрировано.
Check::same(
	'расширение shef-options-admin зарегистрировано',
	isset(CJSCore::$registered['shef-options-admin']),
	true
);

Check::group('_log1() пишет и не сыплет deprecation');

// Проверка не про запись как таковую, а про то, что вызов чист: обвязка
// превращает warning, notice и deprecation в провал, а _log1() передавал null
// третьим параметром file_put_contents() — с PHP 8.1 это deprecated, и в лог
// портала капало при каждом первом вызове за запрос.
//
// Файл пишется в DOCUMENT_ROOT/local/log, поэтому корень на время проверки
// подменяется временным каталогом.
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$sandbox = sys_get_temp_dir().'/shef-options-log-'.getmypid();
$logFile = $sandbox.'/local/log/probe_'.date('dmY').'.log';

// Уборка вешается на завершение процесса, а не пишется в конце группы: этот
// тест существует ради падения, а падение до конца группы не доходит и
// оставило бы каталог в /tmp следующему прогону с тем же pid.
register_shutdown_function(static function() use ($sandbox, $documentRoot): void
{
	$_SERVER['DOCUMENT_ROOT'] = $documentRoot;

	array_map('unlink', glob($sandbox.'/local/log/*') ?: []);

	foreach([$sandbox.'/local/log', $sandbox.'/local', $sandbox] as $dir)
	{
		is_dir($dir) && rmdir($dir);
	}
});

if(is_dir($sandbox.'/local/log'))
{
	array_map('unlink', glob($sandbox.'/local/log/*') ?: []);
}
else
{
	mkdir($sandbox.'/local/log', 0777, true);
}

$_SERVER['DOCUMENT_ROOT'] = $sandbox;

// Метка от «прошлого запроса»: первый вызов обязан её стереть. Без неё
// проверки ниже проходили бы и при $mode = FILE_APPEND, то есть перезапись
// никто бы не сторожил.
file_put_contents($logFile, 'метка прошлого запроса');

// Первый вызов перезаписывает, второй дописывает — ради этого в _log1() и
// заведён статический флаг.
_log1(['первый' => 1], 'probe');
_log1(['второй' => 2], 'probe');

Check::same('файл создан', is_file($logFile), true);

// Читаем только если файл есть: иначе провал проверки выше превратился бы в
// фатал на file_get_contents, и отчёт Check::finish() не напечатался бы.
$written = is_file($logFile) ? (string)file_get_contents($logFile) : '';

Check::same('метка прошлого запроса стёрта', str_contains($written, 'метка'), false);
Check::same('первая запись на месте', str_contains($written, 'первый'), true);
Check::same('вторая дописана, а не затёрла', str_contains($written, 'второй'), true);

Check::finish();
