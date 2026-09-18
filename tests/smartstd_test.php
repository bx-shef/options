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

Check::finish();
