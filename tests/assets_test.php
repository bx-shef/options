<?php declare(strict_types=1);

/**
 * Сходимость публичных путей фронта.
 *
 * Каталог модуля браузеру недоступен, поэтому install/css раскладывается
 * установщиком в /bitrix/css. Путей при этом два: тот, куда установщик кладёт
 * файл, и тот, который страница просит у браузера. Разойдутся — файлы лягут в
 * одно место, страница попросит из другого, и выглядеть это будет как «стили
 * пропали», а не как ошибка установки. На портале такое ловится глазами,
 * здесь — тестом.
 *
 * Своего JS модуль больше не раскладывает, но getPublicJsDir() остался: по
 * нему установщик убирает каталог, оставшийся на порталах от версий до 3.0.0.
 *
 * Ядро подменяется заглушкой, классы модуля подключаются настоящие: тест
 * проверяет код модуля, а не свою копию его логики.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

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

Check::group('публичные пути');

// Дефис в js и точка в css — не опечатка, а требование имён расширений
// Битрикса. Закрепляем, чтобы это не «починили».
Check::same('Constants::getPublicCssDir()', Constants::getPublicCssDir(), '/bitrix/css/shef.options');
Check::same('Constants::getPublicJsDir()', Constants::getPublicJsDir(), '/bitrix/js/shef-options');

$settings = require $root.'/.settings.php';
$installDir = $settings['installDir']['value'] ?? [];

Check::same('installDir не пуст — установщику есть что копировать', !empty($installDir), true);

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

Check::group('имена каталогов в install/');

/**
 * Каталог верхнего уровня в install/css задаёт и путь, по которому файл ляжет,
 * и имя расширения Битрикса. Переименуют каталог — файлы лягут мимо, а ошибки
 * установки при этом не будет.
 */
$topLevelDirs = static function(string $sourceDir) use ($root): array
{
	$path = $root.'/'.$sourceDir;

	if(!is_dir($path))
	{
		return [];
	}

	return array_values(array_filter(
		scandir($path),
		static fn(string $entry): bool => '.' !== $entry && '..' !== $entry && is_dir($path.'/'.$entry)
	));
};

Check::same('install/css содержит ровно каталог публичного пути',
	$topLevelDirs('install/css'), [basename(Constants::getPublicCssDir())]);

// Своего JS в поставке нет — и каталога install/js быть не должно: пустая
// запись в installDir копировала бы пустоту, а непустая уехала бы мимо всех
// проверок этого теста.
Check::same('install/js в репозитории нет', is_dir($root.'/install/js'), false);

Check::group('зарегистрированные расширения');

Check::same('register-js.php зарегистрировал расширение', empty(CJSCore::$registered), false);

$broken = [];

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
				$broken[] = sprintf(
					'%s: путь %s не покрыт ни одной записью installDir — установщик его никуда не положит',
					$extension,
					$publicPath
				);
				continue;
			}

			if(!is_file($root.$source))
			{
				$broken[] = sprintf(
					'%s: странице нужен %s, то есть файл %s, а его в репозитории нет',
					$extension,
					$publicPath,
					$source
				);
			}
		}
	}
}

Check::same('каждый файл расширения лежит там, куда его положит установщик', $broken, []);

Check::finish();
