<?php declare(strict_types=1);

/**
 * DocumentPath: путь к документу ajax-загрузчика документации.
 *
 * Оба слагаемых пути — идентификатор модуля и адрес документа — приходят из
 * браузера. Проверяется здесь не то, как выглядит строка, а то, какой файл
 * в итоге откроется: «..» и символическая ссылка дают один и тот же выход за
 * каталог модуля, а поймать их по тексту можно только первый.
 *
 * Класс ядра не требует, поэтому заглушки не подключаются: тест гоняет
 * ровно код модуля.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';
require_once $root.'/lib/main/options/markdown/documentpath.php';

use Shef\Options\Main\Options\Markdown\DocumentPath;

/**
 * Рекурсивное удаление: фикстура живёт во временном каталоге и убирается за
 * собой, иначе следующий прогон получил бы чужие файлы.
 */
$removeTree = static function(string $dir) use (&$removeTree): void
{
	if(!is_dir($dir))
	{
		return;
	}

	foreach(scandir($dir) ?: [] as $entry)
	{
		if('.' === $entry || '..' === $entry)
		{
			continue;
		}

		$path = $dir.'/'.$entry;

		if(is_link($path) || is_file($path))
		{
			unlink($path);
			continue;
		}

		$removeTree($path);
	}

	rmdir($dir);
};

$base = sys_get_temp_dir().'/shef-options-documentpath-'.getmypid();
$removeTree($base);

$write = static function(string $path, string $content) use ($base): void
{
	$full = $base.'/'.$path;
	$dir = dirname($full);

	if(!is_dir($dir))
	{
		mkdir($dir, 0777, true);
	}

	file_put_contents($full, $content);
};

$write('modules/shef.options/README.md', "# README\n");
$write('modules/shef.options/UPPER.MD', "# UPPER\n");
$write('modules/shef.options/docs/inner.md', "# inner\n");
$write('modules/shef.options/install/index.php', "<?php\n");
$write('modules/other.module/README.md', "# другой модуль\n");
$write('secret/credentials.md', "# не для браузера\n");

// Каталог-сосед с общим началом имени: проверка «путь начинается с каталога
// модуля» без разделителя в конце пустила бы его внутрь.
$write('modules/shef.options-backup/README.md', "# копия\n");

$base = realpath($base);
$modules = $base.'/modules';
$module = $modules.'/shef.options';

// Символическая ссылка изнутри модуля наружу. Поддержка есть не везде,
// поэтому неудача — не провал теста, а пропуск одной проверки.
$isLinked = false;
try
{
	$isLinked = symlink($base.'/secret/credentials.md', $module.'/link.md');
}
catch(Throwable $throwable)
{
	$isLinked = false;
}

Check::group('документ внутри каталога модуля');

Check::same('файл в корне модуля',
	DocumentPath::resolve($modules, 'shef.options', 'README.md'), $module.'/README.md');

// Так склеивает адрес фронт: rootPath пустой, дальше «/» и имя документа.
Check::same('ведущий слэш',
	DocumentPath::resolve($modules, 'shef.options', '/README.md'), $module.'/README.md');

Check::same('вложенный каталог',
	DocumentPath::resolve($modules, 'shef.options', 'docs/inner.md'), $module.'/docs/inner.md');

Check::same('«..» внутри модуля разрешается и остаётся внутри',
	DocumentPath::resolve($modules, 'shef.options', 'docs/../README.md'), $module.'/README.md');

Check::same('регистр расширения не важен',
	DocumentPath::resolve($modules, 'shef.options', 'UPPER.MD'), $module.'/UPPER.MD');

Check::group('за пределы каталога модуля не пускает');

Check::same('«..» в адресе документа',
	DocumentPath::resolve($modules, 'shef.options', '../../secret/credentials.md'), null);

Check::same('«..» после ведущего слэша',
	DocumentPath::resolve($modules, 'shef.options', '/../secret/credentials.md'), null);

Check::same('соседний модуль',
	DocumentPath::resolve($modules, 'shef.options', '../other.module/README.md'), null);

Check::same('«..» в идентификаторе модуля',
	DocumentPath::resolve($modules, '../secret', 'credentials.md'), null);

Check::same('слэш в идентификаторе модуля',
	DocumentPath::resolve($modules, 'shef.options/../../secret', 'credentials.md'), null);

Check::same('идентификатор модуля из одних точек',
	DocumentPath::resolve($modules, '..', 'secret/credentials.md'), null);

Check::same('каталог-сосед с общим началом имени',
	DocumentPath::resolve($modules, 'shef.options', '../shef.options-backup/README.md'), null);

if($isLinked)
{
	Check::same('символическая ссылка наружу',
		DocumentPath::resolve($modules, 'shef.options', 'link.md'), null);
}

Check::group('отдаётся только существующая разметка');

Check::same('не markdown',
	DocumentPath::resolve($modules, 'shef.options', 'install/index.php'), null);

Check::same('файла нет',
	DocumentPath::resolve($modules, 'shef.options', 'missing.md'), null);

Check::same('каталог вместо файла',
	DocumentPath::resolve($modules, 'shef.options', 'docs'), null);

Check::same('пустой адрес',
	DocumentPath::resolve($modules, 'shef.options', ''), null);

Check::same('модуля нет',
	DocumentPath::resolve($modules, 'no.such.module', 'README.md'), null);

// Нулевой байт обрывает строку внутри файловых функций: проверка расширения
// смотрела бы на «.md», а открылся бы файл за каталогом модуля.
Check::same('нулевой байт',
	DocumentPath::resolve($modules, 'shef.options', "README.md\0/../../secret/credentials.md"), null);

Check::group('isModuleId');

Check::same('идентификатор с точкой', DocumentPath::isModuleId('shef.options'), true);
Check::same('идентификатор ядра', DocumentPath::isModuleId('main'), true);
Check::same('подчёркивания', DocumentPath::isModuleId('a_b.c_d'), true);
Check::same('две точки', DocumentPath::isModuleId('..'), false);
Check::same('точка', DocumentPath::isModuleId('.'), false);
Check::same('пусто', DocumentPath::isModuleId(''), false);
Check::same('пустой сегмент', DocumentPath::isModuleId('shef..options'), false);
Check::same('точка в начале', DocumentPath::isModuleId('.shef'), false);
Check::same('точка в конце', DocumentPath::isModuleId('shef.'), false);
Check::same('слэш', DocumentPath::isModuleId('shef/options'), false);
// Дефис ядро тоже не принимает: \Bitrix\Main\Loader::includeModule()
// разрешает только [a-zA-Z0-9._].
Check::same('дефис', DocumentPath::isModuleId('shef-options'), false);

$removeTree($base);

Check::finish();
