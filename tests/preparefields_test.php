<?php declare(strict_types=1);

/**
 * Трейт PrepareFields: нормализация строк, разбор чисел и матрица решений
 * checkField*.
 *
 * Это ровно та логика, которую видно без портала, — и ровно та, на которой
 * ошибаются молча: неверно разобранное число не падает, а едет дальше цифрой,
 * которой никто не заказывал.
 *
 * Ядро подменяется заглушками, трейт подключается настоящий: тест проверяет
 * код модуля, а не свою копию его логики.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';

require_once $root.'/lib/traitlist/tools/preparefields.php';

/**
 * Хост для трейта: часть методов protected и не статические.
 */
class Fields
{
	use \Shef\Options\TraitList\Tools\PrepareFields
	{
		prepareRowList as public;
		translit as public;
		checkField as public;
	}
}

Check::group('parseString — нормализация');

Check::same('пустая строка', Fields::parseString(''), '');
Check::same('null', Fields::parseString(null), '');
Check::same('обрамляющие пробелы', Fields::parseString('  текст  '), 'текст');
Check::same('ведущие дефисы', Fields::parseString('--- текст'), 'текст');
Check::same('ведущие табы', Fields::parseString("\t\tтекст"), 'текст');
Check::same('двойные пробелы внутри', Fields::parseString('а  б   в'), 'а б в');
Check::same('дефис в середине не трогаем', Fields::parseString('А-Б'), 'А-Б');
Check::same('число как строка', Fields::parseString(42), '42');

// Ноль — «пустой» для empty(), и это осознанная особенность: parseString
// вернёт '' там, где ждали '0'. Закрепляем, чтобы изменение заметили.
Check::same('ноль считается пустым', Fields::parseString(0), '');
Check::same('строка «0» тоже пустая', Fields::parseString('0'), '');

Check::group('parseInt и parseFloat');

Check::same('целое из строки', Fields::parseInt('42'), 42);
Check::same('пробелы внутри числа', Fields::parseInt('1 234'), 1234);
Check::same('неразрывный пробел', Fields::parseInt('1'.chr(194).chr(160).'234'), 1234);
Check::same('запятая как разделитель', Fields::parseFloat('1,5'), 1.5);
Check::same('точка как разделитель', Fields::parseFloat('1.5'), 1.5);
Check::same('дробное в parseInt обрезается', Fields::parseInt('1,9'), 1);
Check::same('пусто -> 0', Fields::parseInt(''), 0);
Check::same('пусто -> 0.0', Fields::parseFloat(''), 0.0);
Check::same('мусор -> 0', Fields::parseInt('не число'), 0);

Check::group('checkField* — матрица решений');

Check::same('значение есть — возвращается как есть',
	Fields::checkFieldString('Заголовок', ' текст '), 'текст');
Check::same('значения нет, не обязательно — умолчание',
	Fields::checkFieldString('Заголовок', null, false, 'умолчание'), 'умолчание');
Check::same('значения нет, int — умолчание',
	Fields::checkFieldInt('Количество', null, false, 7), 7);
Check::same('значения нет, float — умолчание',
	Fields::checkFieldFloat('Цена', null, false, 1.5), 1.5);

Check::throws('значения нет и оно обязательно',
	\Bitrix\Main\ArgumentNullException::class,
	static fn() => Fields::checkFieldString('Заголовок', null, true));

// Отдельно: «значения нет» и «значение негодного типа» — разные случаи.
// Первый даёт умолчание, второй тоже, но заметить разницу можно только тут.
Check::same('массив вместо массива', Fields::checkFieldArray('Список', ['а']), ['а']);
Check::same('строка вместо массива — умолчание',
	Fields::checkFieldArray('Список', 'не массив', false, ['по умолчанию']), ['по умолчанию']);
Check::same('нет значения — умолчание',
	Fields::checkFieldArray('Список', null, false, ['по умолчанию']), ['по умолчанию']);

Check::group('prepareRowList — всегда массив');

$fields = new Fields();

$row = [];
Check::same('ключа нет', $fields->prepareRowList('Цвет', $row), []);

$row = ['Цвет' => 'красный'];
Check::same('скаляр заворачивается', $fields->prepareRowList('Цвет', $row), [0 => 'красный']);

$row = ['Цвет' => ['красный', 'синий']];
Check::same('список остаётся списком',
	$fields->prepareRowList('Цвет', $row), ['красный', 'синий']);

$row = ['Цвет' => ['ИМЯ' => 'красный']];
Check::same('ассоциативный массив без ключа 0 заворачивается',
	$fields->prepareRowList('Цвет', $row), [0 => ['ИМЯ' => 'красный']]);

Check::group('translit');

// Вызов без options не должен сыпать warning: обвязка превращает их в провал.
$fields->translit('Привет мир');
Check::same('вызов без options дошёл до ядра',
	\CUtil::$lastCall['value'], 'Привет мир');
Check::same('умолчания параметров',
	\CUtil::$lastCall['params'], ['replace_space' => '-', 'replace_other' => '-']);

$fields->translit('ёжик', ['str_replace' => ['ж' => 'zh']]);
Check::same('str_replace применяется до перевода ё',
	\CUtil::$lastCall['value'], 'yozhик');

Check::finish();
