<?php declare(strict_types=1);

/**
 * Реестр настроек с временной подменой.
 *
 * ЦЕЛЬ
 *   Показать \Shef\Options\Options\Config: реестр строковых значений на время
 *   запроса и пару push()/restore() — отработать кусок кода в других
 *   настройках, не задев остальной код.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Разобрали настройку модуля один раз — положили сюда, дальше берёте
 *   отсюда, а не разбираете заново в каждом вызове. Вторая задача — импорт
 *   или обработчик, которому нужен свой режим на время работы: push(),
 *   поменяли, отработали, restore().
 *   Это НЕ замена \Bitrix\Main\Config\Option: между запросами ничего не
 *   сохраняется.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: config», код возврата 0.
 *   По сути: после restore() значение возвращается к прежнему, а обращение к
 *   незаданному ключу даёт умолчание, а не падение.
 *
 * ЗАПУСК
 *   php examples/config.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/config.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Ничем: пример работает только с памятью процесса.
 */

require_once __DIR__.'/_bootstrap.php';

title('Реестр настроек');

$load('lib/options/singleton.php', 'lib/options/config.php');

use Shef\Options\Options\Config;

step('Значения кладут один раз, берут откуда угодно');

// Config — синглетон, поэтому «положил здесь, взял там» работает без
// передачи объекта по цепочке вызовов.
Config::getInstance()
	->setValue('mode', 'import')
	->setValue('source', 'crm')
;

check('значение на месте', Config::getInstance()->getValue('mode'), 'import');
check('это тот же реестр', Config::getInstance()->toArray(), [
	'mode' => 'import',
	'source' => 'crm',
]);

step('Ключа нет — это не падение');

// Раньше getValue() на незаданном ключе писал в лог «Undefined array key» и
// валил вызов TypeError'ом: метод обещает string, а возвращал null.
check('умолчание по умолчанию', Config::getInstance()->getValue('нет такого'), '');
check('своё умолчание', Config::getInstance()->getValue('нет такого', 'по умолчанию'), 'по умолчанию');

// «Ключа нет» и «значение пустое» — разные вещи, и различить их после
// getValue() уже нельзя. Поэтому есть отдельный вопрос.
Config::getInstance()->setValue('пусто', '');

check('ключ есть, значение пустое', Config::getInstance()->hasValue('пусто'), true);
check('а этого ключа нет', Config::getInstance()->hasValue('нет такого'), false);
check('getValue их не различает', Config::getInstance()->getValue('пусто'), '');

step('Временная подмена: push и restore');

Config::getInstance()->push();
Config::getInstance()->setValue('mode', 'test');

check('внутри подмены', Config::getInstance()->getValue('mode'), 'test');

Config::getInstance()->restore();

check('после restore вернулось как было', Config::getInstance()->getValue('mode'), 'import');

step('Вложенные подмены снимаются по одной');

Config::getInstance()->push();
Config::getInstance()->setValue('mode', 'первый');
Config::getInstance()->push();
Config::getInstance()->setValue('mode', 'второй');

check('верхний слой', Config::getInstance()->getValue('mode'), 'второй');

Config::getInstance()->restore();
check('сняли один слой', Config::getInstance()->getValue('mode'), 'первый');

Config::getInstance()->restore();
check('сняли второй', Config::getInstance()->getValue('mode'), 'import');

// restore() без пары push() не делает ничего: стопка пуста.
Config::getInstance()->restore();
check('лишний restore безвреден', Config::getInstance()->getValue('mode'), 'import');

step('О чём помнить');

note('Состояние общее на весь запрос: положили в одном месте — увидят все.');
note('Значения только строковые: setValue(string, string).');
note('push() без restore() оставит стопку расти — парность на вас.');

done('config');
