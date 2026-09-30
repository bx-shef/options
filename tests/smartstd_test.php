<?php declare(strict_types=1);

/**
 * SmartStd: превращение массива в объект и обратно, глубокое клонирование.
 *
 * Класс лежит в основе передачи структур между модулями линейки, поэтому его
 * поведение на краях важнее, чем на середине: где список остаётся списком, где
 * становится объектом, что происходит с типами ядра при выгрузке в массив.
 *
 * Ядро подменяется заглушками, класс подключается настоящий.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';
require_once $root.'/lib/options/smartstd.php';

use Shef\Options\Options\SmartStd;

Check::group('toObject — массив в объект');

$object = SmartStd::toObject(['code' => 'A', 'sort' => 100]);

Check::same('тип', get_class($object), SmartStd::class);
Check::same('строковое свойство', $object->code, 'A');
Check::same('числовое свойство', $object->sort, 100);

$nested = SmartStd::toObject([
	'code' => 'A',
	'props' => ['color' => 'красный', 'size' => 'M'],
]);

Check::same('вложенный ассоциативный массив стал объектом',
	get_class($nested->props), SmartStd::class);
Check::same('значение внутри вложенного объекта', $nested->props->color, 'красный');

$withList = SmartStd::toObject([
	'code' => 'A',
	'tags' => ['новинка', 'хит'],
]);

Check::same('вложенный список остался массивом', $withList->tags, ['новинка', 'хит']);

// Два соседних вложенных массива: toObject увеличивает счётчик уровня прямо в
// цикле (++$level), то есть второму соседу достаётся уровень на единицу
// больше. На результат это не влияет — разбирается только уровень 0, — но
// закрепляем, чтобы замена счётчика на что-то другое не прошла незаметно.
$siblings = SmartStd::toObject([
	'first' => ['список', 'один'],
	'second' => ['список', 'два'],
]);

Check::same('первый сосед', $siblings->first, ['список', 'один']);
Check::same('второй сосед', $siblings->second, ['список', 'два']);

// Особенность: список на верхнем уровне всё равно становится объектом с
// числовыми свойствами, потому что уровень 0 всегда объект.
$rootList = SmartStd::toObject(['а', 'б']);

Check::same('список на верхнем уровне — объект', get_class($rootList), SmartStd::class);
Check::same('доступ по числовому свойству', $rootList->{'0'}, 'а');

Check::group('toArray — объект в массив');

Check::same('простой объект', $nested->toArray(), [
	'code' => 'A',
	'props' => ['color' => 'красный', 'size' => 'M'],
]);

Check::same('список сохраняется списком', $withList->toArray(), [
	'code' => 'A',
	'tags' => ['новинка', 'хит'],
]);

Check::group('toArray — типы ядра');

$withDate = new SmartStd();
$withDate->at = new \Bitrix\Main\Type\Date('01.02.2026');
Check::same('Date разворачивается через toString', $withDate->toArray(), ['at' => '01.02.2026']);

$inner = new SmartStd();
$inner->code = 'B';
$withArrayable = new SmartStd();
$withArrayable->child = $inner;
Check::same('вложенный Arrayable разворачивается через toArray',
	$withArrayable->toArray(), ['child' => ['code' => 'B']]);

$withJson = new SmartStd();
$withJson->payload = new class implements \JsonSerializable
{
	public function jsonSerialize(): array
	{
		return ['k' => 'v'];
	}
};
Check::same('JsonSerializable разворачивается', $withJson->toArray(), ['payload' => ['k' => 'v']]);

Check::group('toObject -> toArray — круговой рейс');

$source = [
	'code' => 'A',
	'sort' => 100,
	'props' => ['color' => 'красный'],
	'tags' => ['новинка', 'хит'],
];

Check::same('структура не изменилась', SmartStd::toObject($source)->toArray(), $source);

Check::group('__clone — глубокое клонирование');

$original = SmartStd::toObject(['props' => ['color' => 'красный']]);
$copy = clone $original;
$copy->props->color = 'синий';

Check::same('оригинал не тронут', $original->props->color, 'красный');
Check::same('копия изменилась', $copy->props->color, 'синий');
Check::same('это разные объекты', $original->props === $copy->props, false);

Check::group('__toString — объект в строку');

// CHANGELOG 2.2.12 обещает «Поддержка в SmartStd \Stringable», а в дереве
// interface и метод однажды потерялись вместе с переносом файлов из поздней
// рабочей копии: класс объявлял только Arrayable, и (string)$obj падал с
// «could not be converted to string». Тест держит обещание CHANGELOG.
$stringable = SmartStd::toObject(['code' => 'A', 'sort' => 100]);

// instanceof \Stringable здесь ничего не доказывает: PHP 8 добавляет этот
// интерфейс сам любому классу с __toString(), поэтому проверка зелёная и без
// слова implements в объявлении. Проверено запуском, и мутация «убрать
// \Stringable из implements» её не роняет. Значит проверяем поведение, а
// объявление оставлено для читателя — так же, как в сборке 2.2.16.
Check::same('объект приводится к строке', $stringable instanceof \Stringable, true);

$json = (string)$stringable;

Check::same('строка разбирается как json', json_decode($json, true), [
	'code' => 'A',
	'sort' => 100,
]);

// Флаги выбраны в самом методе, и два из них видны в результате глазами:
// кириллица не экранируется, вывод с отступами.
$cyrillic = (string)SmartStd::toObject(['name' => 'красный', 'url' => 'https://a/b']);

Check::same('кириллица не экранирована', str_contains($cyrillic, 'красный'), true);
Check::same('слэш не экранирован', str_contains($cyrillic, 'https://a/b'), true);
Check::same('вывод с переносами', str_contains($cyrillic, "\n"), true);

Check::group('чужая кодировка не роняет объект');

// toArray() внутри гоняет значение через json_encode/json_decode. Без
// терпимых флагов строка не в UTF-8 роняет json_encode() в false, а
// json_decode(false) под strict_types — это TypeError. Вылетал он из
// __toString(), то есть из подстановки объекта в строку лога: вместо записи
// в лог получался фатал в том месте, которое как раз пыталось записать сбой.
$broken = SmartStd::toObject(['name' => "\xC0\xE1\xE2", 'ok' => 1]);

Check::same('toArray() отдаёт массив', is_array($broken->toArray()), true);
Check::same('соседнее значение целое', $broken->toArray()['ok'], 1);
Check::same('приведение к строке не бросает', is_string((string)$broken), true);

Check::finish();
