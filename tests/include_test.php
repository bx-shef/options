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

Check::finish();
