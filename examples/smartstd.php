<?php declare(strict_types=1);

/**
 * Объект из массива и обратно.
 *
 * ЦЕЛЬ
 *   Показать \Shef\Options\Options\SmartStd: массив превращается в объект с
 *   обращением через «->», а обратно разворачивается в массив, попутно
 *   раскрывая типы ядра. И показать края, на которых обычно ошибаются.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Передача структур между модулями линейки и между слоями одного модуля:
 *   параметры компонента, разобранный ответ внешней системы, настройки
 *   обработчика. Удобно там, где массив уже неудобно читать, а заводить класс
 *   на каждую структуру — дорого.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: smartstd», код возврата 0.
 *   По сути: ассоциативный массив внутри становится объектом, СПИСОК внутри
 *   остаётся массивом, круговой рейс массив → объект → массив не меняет
 *   структуру, а клонирование глубокое.
 *
 * ЗАПУСК
 *   php examples/smartstd.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/smartstd.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Ничем, кроме того, что Date и DateTime будут настоящие.
 */

require_once __DIR__.'/_bootstrap.php';

title('Объект из массива');

$load('lib/options/smartstd.php');

use Shef\Options\Options\SmartStd;
use Bitrix\Main\Type\Date;

step('Массив становится объектом');

$deal = SmartStd::toObject([
	'title' => 'Поставка',
	'sum' => 1500,
	'client' => [
		'name' => 'ООО «Ромашка»',
		'inn' => '7701234567',
	],
	'tags' => ['новый', 'срочно'],
]);

check('корень стал объектом', get_class($deal), SmartStd::class);
check('обращение через ->', $deal->title, 'Поставка');
check('вложенный ассоциативный массив тоже объект', get_class($deal->client), SmartStd::class);
check('и в него можно вглубь', $deal->client->name, 'ООО «Ромашка»');

step('Список остаётся списком — это важно');

// Ассоциативный массив внутри становится объектом, а СПИСОК — нет.
// Иначе foreach по тегам сломался бы.
check('список не стал объектом', $deal->tags, ['новый', 'срочно']);
check('по нему можно идти foreach', is_array($deal->tags), true);

step('Край: список на ВЕРХНЕМ уровне');

// Уровень 0 всегда объект — даже если это список. Обращаться придётся по
// числовому свойству. Если ждёте на входе список, не прогоняйте его через
// toObject().
$list = SmartStd::toObject(['первый', 'второй']);

check('верхний уровень всё равно объект', get_class($list), SmartStd::class);
check('доступ по числовому свойству', $list->{'0'}, 'первый');

step('Обратно в массив');

check('структура не изменилась', $deal->toArray(), [
	'title' => 'Поставка',
	'sum' => 1500,
	'client' => [
		'name' => 'ООО «Ромашка»',
		'inn' => '7701234567',
	],
	'tags' => ['новый', 'срочно'],
]);

step('Типы ядра разворачиваются сами');

$document = new SmartStd();
$document->number = 'СЧ-1';
$document->date = new Date('01.02.2026');

// Date умеет toString(), и toArray() это использует: в массиве окажется
// строка, а не объект, — такое уже можно отдать в json или в очередь.
check('Date стал строкой', $document->toArray(), [
	'number' => 'СЧ-1',
	'date' => '01.02.2026',
]);

$inner = new SmartStd();
$inner->code = 'A';

$outer = new SmartStd();
$outer->child = $inner;

check('вложенный SmartStd развернулся', $outer->toArray(), ['child' => ['code' => 'A']]);

$payload = new SmartStd();
$payload->body = new class implements JsonSerializable
{
	public function jsonSerialize(): array
	{
		return ['k' => 'v'];
	}
};

check('JsonSerializable тоже развернулся', $payload->toArray(), ['body' => ['k' => 'v']]);

step('Клонирование глубокое');

$copy = clone $deal;
$copy->client->name = 'ООО «Василёк»';

// Обычный clone в PHP поверхностный: вложенные объекты остались бы общими, и
// правка копии меняла бы оригинал. Здесь этого не происходит.
check('оригинал не тронут', $deal->client->name, 'ООО «Ромашка»');
check('копия изменилась', $copy->client->name, 'ООО «Василёк»');
check('это разные объекты', $deal->client === $copy->client, false);

step('О чём помнить');

note('Список на верхнем уровне станет объектом с числовыми свойствами.');
note('Несуществующее свойство — это обычный PHP: warning и null.');
note('toArray() разворачивает Date, Arrayable и JsonSerializable, остальное — как есть.');

done('smartstd');
