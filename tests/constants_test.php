<?php declare(strict_types=1);

/**
 * Constants: разбор настройки «служебный пользователь».
 *
 * Значение приходит из формы настроек, то есть строкой и от человека. Приведение
 * (int) на таком значении ошибается молча и в обе стороны: пустая строка станет
 * нулём — работой «от имени никого», — а '5 62' станет пятёркой, то есть правами
 * пользователя, которого никто не выбирал. Ни того, ни другого не видно в логе:
 * ошибки нет, есть неверный идентификатор.
 *
 * Ядро подменяется заглушками, класс подключается настоящий.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';
require_once $root.'/lib/main/constants.php';

use Shef\Options\Main\Constants;
use Bitrix\Main\Config\Option;

/** Значение настройки для одной проверки. */
$withOption = static function(mixed $value): int
{
	Option::set(Constants::MODULE_ID, 'DEF_systemuserid', $value);
	$id = Constants::getSystemUserId();
	Option::forget(Constants::MODULE_ID, 'DEF_systemuserid');

	return $id;
};

Check::group('настройка не заполнена');

Check::same('умолчание объявлено', Constants::DEFAULT_SYSTEM_USER_ID, 1);
Check::same('опции нет вовсе', Constants::getSystemUserId(), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('пустая строка', $withOption(''), Constants::DEFAULT_SYSTEM_USER_ID);

Check::group('настройка заполнена верно');

Check::same('строка из цифр', $withOption('562'), 562);
Check::same('целое', $withOption(7), 7);
Check::same('единица', $withOption('1'), 1);

Check::group('настройка заполнена мусором — берём умолчание, а не половину значения');

// Ровно те случаи, на которых ошибается intval: каждый из них давал бы
// работающий, но не тот идентификатор.
Check::same('пробел внутри числа', $withOption('5 62'), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('пробел перед числом', $withOption(' 5'), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('ведущий ноль', $withOption('05'), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('ноль', $withOption('0'), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('отрицательное', $withOption('-3'), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('целое ноль', $withOption(0), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('целое отрицательное', $withOption(-3), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('не число', $withOption('нет'), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('массив', $withOption([562]), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('истина', $withOption(true), Constants::DEFAULT_SYSTEM_USER_ID);
Check::same('null', $withOption(null), Constants::DEFAULT_SYSTEM_USER_ID);

Check::group('публичные пути');

Check::same('css через точку', Constants::getPublicCssDir(), '/bitrix/css/shef.options');
Check::same('js через дефис', Constants::getPublicJsDir(), '/bitrix/js/shef-options');

Check::finish();
