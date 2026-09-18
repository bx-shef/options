<?php declare(strict_types=1);

/**
 * .settings.php: ссылки наружу обязаны никуда не висеть.
 *
 * Этот файл — единственное место, где модуль говорит ядру, что и откуда брать:
 * что копировать установщику, какие чужие классы подгружать по карте, какие
 * namespace отдавать ajax-контроллерам. Все три ссылаются на файлы и каталоги
 * ПУТЁМ, и ни одну из них не проверяет ни php -l, ни автозагрузка.
 *
 * Цена промаха разная, но диагноз одинаково неочевидный: установщик молча
 * ничего не скопирует (страница останется без стилей), автозагрузка молча не
 * найдёт класс, ajax молча ответит 404. Ни одного сообщения об ошибке
 * установки при этом не будет.
 *
 * Проверяется в первую очередь после удалений: убрали каталог — запись о нём
 * осталась.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$settings = require $root.'/.settings.php';

Check::group('структура файла');

Check::same('.settings.php вернул массив', is_array($settings), true);

$shape = [];
foreach($settings as $key => $section)
{
	if(!is_array($section) || !array_key_exists('value', $section) || !array_key_exists('readonly', $section))
	{
		$shape[] = $key;
	}
}

Check::same('у каждой секции есть value и readonly', $shape, []);

Check::group('installDir — что копирует установщик');

$installDir = $settings['installDir']['value'] ?? [];

Check::same('installDir не пуст', !empty($installDir), true);

$missing = [];
$wrongTarget = [];

foreach($installDir as $i => $map)
{
	$from = (string)($map['from'] ?? '');
	$to = (string)($map['to'] ?? '');

	if($from === '' || !is_dir($root.$from))
	{
		$missing[] = sprintf('запись %d: каталога %s в репозитории нет', $i, $from);
	}

	// Каталог модуля браузеру недоступен, поэтому «куда» — всегда под /bitrix/.
	if(!str_starts_with($to, '/bitrix/'))
	{
		$wrongTarget[] = sprintf('запись %d: to = %s', $i, $to);
	}
}

Check::same('каждый from существует', $missing, []);
Check::same('каждый to ведёт под /bitrix/', $wrongTarget, []);

Check::group('registerAutoLoadClasses — карта чужих классов');

$autoload = $settings['registerAutoLoadClasses']['value'] ?? [];

$brokenPath = [];
$brokenClass = [];

foreach($autoload as $class => $path)
{
	if(!is_file($root.'/'.$path))
	{
		$brokenPath[] = sprintf('%s => %s', $class, $path);
		continue;
	}

	// Ключ карты — FQCN строчными. Класс в файле обязан быть тот же, иначе
	// ядро подключит файл и всё равно не найдёт класса.
	$name = mb_substr((string)$class, mb_strrpos((string)$class, '\\') + 1);
	if(!preg_match('/\b(class|interface|trait|enum)\s+'.preg_quote($name, '/').'\b/i', file_get_contents($root.'/'.$path)))
	{
		$brokenClass[] = sprintf('%s не объявлен в %s', $class, $path);
	}
}

Check::same('каждый файл карты на месте', $brokenPath, []);
Check::same('в каждом файле карты объявлен свой класс', $brokenClass, []);

Check::group('controllers — namespace ajax-контроллеров');

$namespaces = $settings['controllers']['value']['namespaces'] ?? [];

$brokenNamespace = [];

foreach($namespaces as $namespace => $prefix)
{
	// То же соглашение, что у автозагрузки: первые два сегмента — модуль,
	// остальные — путь строчными. @see tests/autoload_test.php
	$relative = ltrim((string)$namespace, '\\');

	if(!str_starts_with($relative, 'Shef\\Options\\'))
	{
		$brokenNamespace[] = sprintf('%s — чужой namespace', $namespace);
		continue;
	}

	$dir = 'lib/'.mb_strtolower(str_replace('\\', '/', mb_substr($relative, mb_strlen('Shef\\Options\\'))));

	if(!is_dir($root.'/'.$dir))
	{
		$brokenNamespace[] = sprintf('%s (префикс %s) — каталога %s нет', $namespace, $prefix, $dir);
	}
}

Check::same('каждый namespace контроллеров существует', $brokenNamespace, []);

Check::finish();
