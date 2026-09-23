<?php declare(strict_types=1);

/**
 * Удаление модуля уносит его настройки.
 *
 * Раньше UnInstallDB() снимал регистрацию и пункт меню, а b_option не трогал.
 * Выглядело это безобидно, но давало две вещи сразу: на портале оставались
 * строки снятого модуля, и «поставить начисто» было нельзя — повторная
 * установка молча поднимала прежние значения. Молча и есть самое неприятное:
 * администратор видит свежеустановленный модуль с чужими настройками и
 * никакого следа, откуда они взялись.
 *
 * Проверяются обе стороны: своё стирается, чужое остаётся. Вторая половина
 * не формальность — Option::delete() без фильтра работает по модулю, и
 * ошибка в идентификаторе унесла бы настройки соседа.
 *
 * Ядро подменяется заглушками, установщик подключается настоящий.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';

// region Заглушка ядра ////
/**
 * След вызовов, которые установщик делает в ядро.
 *
 * Лежит отдельным классом, а не глобальной переменной: функции-заглушки ниже
 * объявлены в глобальном пространстве, и тянуть в каждую global было бы
 * ровно тем шумом, из-за которого потом не видно сути теста.
 */
class CoreCalls
{
	/** @var list<string> */
	public static array $unregistered = [];

	/** @var int */
	public static int $cacheCleaned = 0;

	public static function reset(): void
	{
		static::$unregistered = [];
		static::$cacheCleaned = 0;
	}
}

if(!class_exists('CModule'))
{
	class CModule
	{
	}
}

if(!function_exists('IsModuleInstalled'))
{
	/** Intranet на стенде теста нет — левое меню уходит в ранний возврат. */
	function IsModuleInstalled(string $moduleId): bool
	{
		return false;
	}
}

if(!function_exists('UnRegisterModule'))
{
	function UnRegisterModule(string $moduleId): void
	{
		CoreCalls::$unregistered[] = $moduleId;
	}
}

if(!function_exists('RegisterModule'))
{
	function RegisterModule(string $moduleId): void
	{
	}
}

$GLOBALS['APPLICATION'] = new class
{
	public function ThrowException(string $message): void {}
};

$GLOBALS['CACHE_MANAGER'] = new class
{
	public function CleanAll(): void
	{
		CoreCalls::$cacheCleaned++;
	}
};
// endregion ////

require_once $root.'/install/index.php';

use Bitrix\Main\Config\Option;

const NEIGHBOUR = 'shef.leadfinish';

/** Настройки двух модулей и чистый след вызовов перед каждой проверкой. */
$given = static function(): shef_options
{
	CoreCalls::reset();
	Option::$values = [];

	Option::set('shef.options', 'DEF_systemuserid', '7');
	Option::set('shef.options', 'DEF_other', 'Y');
	Option::set(NEIGHBOUR, 'DEF_systemuserid', '9');

	return new shef_options();
};

Check::group('удаление уносит настройки модуля');

$module = $given();
$module->UnInstallDB();

Check::same('идентификатор модуля тот самый', $module->MODULE_ID, 'shef.options');
Check::same('настройка модуля стёрта', Option::get('shef.options', 'DEF_systemuserid', 'нет'), 'нет');
Check::same('вторая настройка тоже', Option::get('shef.options', 'DEF_other', 'нет'), 'нет');
Check::same('модуль снят с регистрации', CoreCalls::$unregistered, ['shef.options']);
Check::same('кеш сброшен', CoreCalls::$cacheCleaned, 1);

Check::group('чужое не трогаем');

Check::same('настройка соседнего модуля на месте', Option::get(NEIGHBOUR, 'DEF_systemuserid', 'нет'), '9');

Check::group('savedata = Y оставляет настройки');

// Уговор ядра: установщик с формой удаления кладёт сюда ответ на «сохранить
// данные?». Формы у модуля нет, но метод её уже слушает — иначе добавить
// форму позже значило бы править и метод, и тест разом.
$module = $given();
$module->UnInstallDB(['savedata' => 'Y']);

Check::same('настройка пережила удаление', Option::get('shef.options', 'DEF_systemuserid', 'нет'), '7');
Check::same('модуль всё равно снят с регистрации', CoreCalls::$unregistered, ['shef.options']);

Check::group('умолчание — чистить');

$module = $given();
$module->UnInstallDB(['savedata' => 'N']);

Check::same('savedata = N стирает', Option::get('shef.options', 'DEF_systemuserid', 'нет'), 'нет');

Check::finish();
