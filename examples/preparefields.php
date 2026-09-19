<?php declare(strict_types=1);

/**
 * Приведение входных полей трейтом PrepareFields.
 *
 * ЦЕЛЬ
 *   Показать \Shef\Options\TraitList\Tools\PrepareFields: как подключить
 *   трейт к своему классу и чем его разбор отличается от (int)/(float)/trim.
 *   Разница не косметическая — на ней молча разъезжаются данные.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Любой вход, пришедший от человека или из чужой системы: форма, CSV,
 *   выгрузка 1С, параметры компонента, тело ajax-запроса. Там, где «1 234,50»
 *   и «  Наименование  » приходят строками, а дальше нужны число и чистая
 *   строка — и нужно, чтобы отсутствие обязательного поля было ошибкой, а не
 *   нулём.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: preparefields», код возврата 0.
 *   По сути: пробелы внутри числа и запятая как разделитель разбираются
 *   правильно; отсутствующее необязательное поле даёт умолчание, а
 *   обязательное — исключение; prepareRowList() всегда отдаёт массив.
 *
 * ЗАПУСК
 *   php examples/preparefields.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/preparefields.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   translit() пойдёт в настоящий CUtil::translit ядра, поэтому строка
 *   действительно станет латиницей. Здесь ядро — заглушка, и пример смотрит
 *   не на результат перевода, а на то, с какими параметрами его позвали.
 */

require_once __DIR__.'/_bootstrap.php';

title('Приведение входных полей');

$load('lib/traitlist/tools/preparefields.php');

use Bitrix\Main\ArgumentNullException;
use Shef\Options\TraitList\Tools\PrepareFields;

// region Свой класс с трейтом ////
/**
 * Разбор строки прайса: ровно то место, где нужен этот трейт.
 *
 * Часть методов трейта protected — они рассчитаны на использование изнутри.
 * Наружу открываем только то, что действительно нужно снаружи.
 */
final class PriceRow
{
	use PrepareFields;

	public function __construct(
		public readonly string $name,
		public readonly int $quantity,
		public readonly float $price,
		public readonly array $tags
	) {}

	/**
	 * Собирает строку из сырого массива.
	 *
	 * @throws ArgumentNullException если нет обязательного поля
	 */
	public static function fromArray(array $row): static
	{
		return new static(
			// Обязательное: нет значения — исключение с понятным текстом.
			name: static::checkFieldString('Наименование', $row['NAME'] ?? null, true),
			// Необязательные: нет значения — умолчание.
			quantity: static::checkFieldInt('Количество', $row['QUANTITY'] ?? null, false, 1),
			price: static::checkFieldFloat('Цена', $row['PRICE'] ?? null, false, 0.0),
			tags: static::checkFieldArray('Метки', $row['TAGS'] ?? null, false, []),
		);
	}
}
// endregion ////

step('Разбор строки как есть');

$row = PriceRow::fromArray([
	'NAME' => '  Болт М8  ',
	'QUANTITY' => '1 200',
	'PRICE' => '1 234,50',
	'TAGS' => ['крепёж'],
]);

check('строка очищена', $row->name, 'Болт М8');
// (int)'1 200' дало бы 1 — разбор обрывается на пробеле.
check('пробел внутри числа не мешает', $row->quantity, 1200);
// (float)'1 234,50' дало бы 1.0 — и запятая, и пробел.
check('запятая как разделитель', $row->price, 1234.5);
check('массив остался массивом', $row->tags, ['крепёж']);

step('Неразрывный пробел — тот самый, что приходит из 1С и Excel');

check('разбирается наравне с обычным',
	PriceRow::parseInt('1'.chr(194).chr(160).'200'), 1200);

step('Чего НЕ делает приведение типом');

check('parseString обрезает края', PriceRow::parseString('  текст  '), 'текст');
check('и схлопывает двойные пробелы', PriceRow::parseString('а  б   в'), 'а б в');
check('и снимает ведущие дефисы', PriceRow::parseString('--- текст'), 'текст');
check('мусор даёт 0, а не половину', PriceRow::parseInt('не число'), 0);
check('дробное в parseInt обрезается', PriceRow::parseInt('1,9'), 1);

// Край, на который стоит посмотреть заранее: ноль для empty() пустой, и
// parseString вернёт '' там, где ждали '0'.
check('ноль считается пустым', PriceRow::parseString(0), '');
check('строка «0» тоже', PriceRow::parseString('0'), '');

step('Обязательное поле: отсутствие — это ошибка');

$row = PriceRow::fromArray(['NAME' => 'Гайка М8']);

check('умолчание вместо количества', $row->quantity, 1);
check('умолчание вместо цены', $row->price, 0.0);
check('умолчание вместо меток', $row->tags, []);

try
{
	PriceRow::fromArray(['QUANTITY' => 5]);
	check('без наименования должно падать', false, true);
}
catch(ArgumentNullException $exception)
{
	check('исключение с именем поля', str_contains($exception->getMessage(), 'Наименование'), true);
}

step('Негодный тип — это не то же, что отсутствие');

// Строка вместо массива: значение ЕСТЬ, но оно не того типа. Здесь тоже
// вернётся умолчание — и это единственное место, где разницу видно.
check('строка вместо массива', PriceRow::checkFieldArray('Метки', 'не массив', false, ['по умолчанию']), ['по умолчанию']);

step('prepareRowList: на выходе всегда массив');

// Поле, которое иногда приходит скаляром, иногда списком, иногда вовсе
// отсутствует. Дальше по коду хочется просто foreach.
final class Importer
{
	use PrepareFields { prepareRowList as public; }
}

$importer = new Importer();

$empty = [];
check('ключа нет', $importer->prepareRowList('COLOR', $empty), []);

$scalar = ['COLOR' => 'красный'];
check('скаляр завернули', $importer->prepareRowList('COLOR', $scalar), [0 => 'красный']);

$many = ['COLOR' => ['красный', 'синий']];
check('список оставили', $importer->prepareRowList('COLOR', $many), ['красный', 'синий']);

$assoc = ['COLOR' => ['NAME' => 'красный']];
check('ассоциативный массив завернули', $importer->prepareRowList('COLOR', $assoc), [0 => ['NAME' => 'красный']]);

step('О чём помнить');

note('checkField* с $isRequired = true бросает ArgumentNullException — ловите.');
note('parseString(0) вернёт пустую строку: ноль для empty() пустой.');
note('prepareRowList() принимает массив строки ПО ССЫЛКЕ и возвращает ссылку.');

done('preparefields');
