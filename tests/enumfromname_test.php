<?php declare(strict_types=1);

/**
 * EnumFromName: выбор варианта перечисления по имени.
 *
 * У backed enum есть from() и tryFrom() — но по ЗНАЧЕНИЮ. Когда значения нет
 * (чистый enum) или когда в настройке лежит имя варианта, а не значение,
 * выбирать приходится по name, и ядро для этого ничего не даёт. Трейт
 * повторяет ту же пару: tryFromName() возвращает null, fromName() бросает
 * ValueError — как и договаривается PHP для from()/tryFrom().
 *
 * Трейт подключается настоящий, ядро здесь не нужно.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';
require_once $root.'/lib/traitlist/tools/enumfromname.php';

use Shef\Options\TraitList\Tools\EnumFromName;

/** Чистый enum — именно ради таких трейт и нужен: from() у него нет. */
enum Color
{
	use EnumFromName;

	case Red;
	case Green;
}

/** Backed enum: from() есть, но он по значению, а не по имени. */
enum Status: string
{
	use EnumFromName;

	case Active = 'Y';
	case Paused = 'N';
}

Check::group('tryFromName — null вместо исключения');

Check::same('вариант найден', Color::tryFromName('Red'), Color::Red);
Check::same('второй вариант', Color::tryFromName('Green'), Color::Green);
Check::same('имени нет — null', Color::tryFromName('Blue'), null);
Check::same('пустая строка — null', Color::tryFromName(''), null);

// Регистр важен: имена вариантов сравниваются строго, иначе 'red' и 'Red'
// стали бы одним и тем же, и опечатка в настройке прошла бы незамеченной.
Check::same('регистр учитывается', Color::tryFromName('red'), null);

Check::group('fromName — исключение вместо null');

Check::same('вариант найден', Color::fromName('Green'), Color::Green);
Check::throws(
	'имени нет — ValueError',
	\ValueError::class,
	static fn() => Color::fromName('Blue')
);

Check::group('backed enum: имя и значение — разные вещи');

Check::same('по имени', Status::tryFromName('Active'), Status::Active);
// 'Y' — это значение, а не имя; трейт про имена и такого варианта не знает.
Check::same('значение именем не считается', Status::tryFromName('Y'), null);
Check::same('штатный from() по-прежнему по значению', Status::from('Y'), Status::Active);

Check::finish();
