<?php declare(strict_types=1);

/**
 * Сходимость публичных путей фронта.
 *
 * Каталог модуля браузеру недоступен, поэтому install/css и install/js
 * раскладываются установщиком в /bitrix/css и /bitrix/js. Путей при этом два:
 * тот, куда установщик кладёт файл, и тот, который страница просит у браузера.
 * Разойдутся — файлы лягут в одно место, страница попросит из другого, и
 * выглядеть это будет как «стили пропали», а не как ошибка установки. На
 * портале такое ловится глазами, здесь — тестом.
 *
 * Ядро подменяется заглушкой, классы модуля подключаются настоящие: тест
 * проверяет код модуля, а не свою копию его логики.
 */

$root = dirname(__DIR__);

// region Заглушка ядра ////
/**
 * От настоящего CJSCore нужен только перехват регистрации расширения.
 */
class CJSCore
{
	/** @var array<string, array> */
	public static array $registered = [];

	public static function RegisterExt(string $name, array $params): void
	{
		static::$registered[$name] = $params;
	}
}
// endregion ////

// Настоящий register-js.php, и намеренно БЕЗ предварительной загрузки
// constants.php: файл обязан быть самодостаточным. На портале он выполняется в
// момент подключения модуля, когда автозагрузка классов модуля может быть ещё
// не в силе, — и если он на неё понадеется, упадёт весь модуль.
require_once $root.'/register-js.php';

use Shef\Options\Main\Constants;

$errors = [];

$check = static function(string $what, $actual, $expected) use (&$errors): void
{
	if($actual === $expected)
	{
		printf("  OK   %s = %s\n", $what, var_export($actual, true));
		return;
	}

	$errors[] = sprintf(
		'%s: получено %s, ожидалось %s',
		$what,
		var_export($actual, true),
		var_export($expected, true)
	);
	printf("  FAIL %s = %s, ожидалось %s\n", $what, var_export($actual, true), var_export($expected, true));
};

echo "Публичные пути\n";

// Дефис в js и точка в css — не опечатка, а требование имён расширений
// Битрикса. Закрепляем, чтобы это не «починили».
$check('Constants::getPublicCssDir()', Constants::getPublicCssDir(), '/bitrix/css/shef.options');
$check('Constants::getPublicJsDir()', Constants::getPublicJsDir(), '/bitrix/js/shef-options');

echo "\nРаскладка install/ -> /bitrix/\n";

$settings = require $root.'/.settings.php';
$installDir = $settings['installDir']['value'] ?? [];

if(!is_array($installDir) || empty($installDir))
{
	$errors[] = 'в .settings.php пуст installDir — установщику нечего копировать';
}

/**
 * Обратное отображение публичного пути в исходный файл репозитория по той же
 * карте, которой пользуется установщик.
 */
$toSource = static function(string $publicPath) use ($installDir): null|string
{
	foreach($installDir as $map)
	{
		$to = (string)($map['to'] ?? '');
		$from = (string)($map['from'] ?? '');

		if($to === '' || $from === '')
		{
			continue;
		}

		if(str_starts_with($publicPath, $to.'/'))
		{
			return $from.mb_substr($publicPath, mb_strlen($to));
		}
	}

	return null;
};

echo "\nИмена каталогов в install/\n";

/**
 * Каталог верхнего уровня в install/css и install/js задаёт и путь, по которому
 * файл ляжет, и имя расширения Битрикса: /bitrix/js/shef-options/options-markdown
 * грузится как «shef-options.options-markdown». Переименуют каталог — отвалятся
 * все такие имена, а ошибки установки при этом не будет.
 */
$dirCheck = static function(string $sourceDir, string $publicDir, string $label) use ($root, &$errors): void
{
	$expected = basename($publicDir);
	$path = $root.'/'.$sourceDir;

	if(!is_dir($path))
	{
		$errors[] = sprintf('%s: каталога %s нет', $label, $sourceDir);
		printf("  FAIL %s: каталога %s нет\n", $label, $sourceDir);
		return;
	}

	foreach(scandir($path) as $entry)
	{
		if($entry === '.' || $entry === '..' || !is_dir($path.'/'.$entry))
		{
			continue;
		}

		if($entry === $expected)
		{
			printf("  OK   %s/%s\n", $sourceDir, $entry);
			continue;
		}

		$errors[] = sprintf(
			'%s: каталог %s/%s не совпадает с %s — файлы лягут мимо',
			$label,
			$sourceDir,
			$entry,
			$publicDir
		);
		printf("  FAIL %s/%s, ожидался %s\n", $sourceDir, $entry, $expected);
	}
};

$dirCheck('install/css', Constants::getPublicCssDir(), 'css');
$dirCheck('install/js', Constants::getPublicJsDir(), 'js');

echo "\nЗарегистрированные расширения\n";

if(empty(CJSCore::$registered))
{
	$errors[] = 'register-js.php не зарегистрировал ни одного расширения';
	echo "  FAIL ни одного расширения не зарегистрировано\n";
}

foreach(CJSCore::$registered as $extension => $params)
{
	foreach(['css', 'js'] as $kind)
	{
		foreach((array)($params[$kind] ?? []) as $publicPath)
		{
			$publicPath = (string)$publicPath;
			$source = $toSource($publicPath);

			if(null === $source)
			{
				$errors[] = sprintf(
					'%s: путь %s не покрыт ни одной записью installDir — установщик его никуда не положит',
					$extension,
					$publicPath
				);
				printf("  FAIL %s: %s вне installDir\n", $extension, $publicPath);
				continue;
			}

			if(!is_file($root.$source))
			{
				$errors[] = sprintf(
					'%s: странице нужен %s, то есть файл %s, а его в репозитории нет',
					$extension,
					$publicPath,
					$source
				);
				printf("  FAIL %s: %s -> %s, файла нет\n", $extension, $publicPath, $source);
				continue;
			}

			printf("  OK   %s: %s -> %s\n", $extension, $publicPath, $source);
		}
	}
}

echo "\n";

if(!empty($errors))
{
	echo "Провалено:\n";
	foreach($errors as $error)
	{
		echo '  * '.$error."\n";
	}

	exit(1);
}

echo "Пути сходятся.\n";
exit(0);
