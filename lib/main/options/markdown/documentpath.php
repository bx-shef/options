<?php declare(strict_types=1);

namespace Shef\Options\Main\Options\Markdown;

/**
 * Разрешение пути к документу внутри каталога модуля.
 *
 * Оба параметра ajax-действия приходят из браузера, поэтому склеивать их с
 * путём напрямую нельзя: «../» в любом из них уводит чтение за пределы
 * каталога модуля.
 *
 * Путь здесь не проверяется на вид, а собирается и сверяется с каталогом
 * модуля ПОСЛЕ разрешения: realpath раскрывает и «..», и символические
 * ссылки, то есть проверяется то, что будет открыто, а не то, что было
 * написано. Проверка по тексту («нет ли в строке ..») обходится ссылкой
 * внутри каталога модуля, ведущей наружу.
 *
 * Класс намеренно не зависит от ядра — его целиком гоняет
 * tests/documentpath_test.php.
 *
 * @see Controller::getContentAction()
 */
final class DocumentPath
{
	/**
	 * Идентификатор модуля.
	 *
	 * Набор символов тот же, что проверяет \Bitrix\Main\Loader::includeModule(),
	 * но сегменты между точками обязаны быть непустыми: у ядра точка разрешена,
	 * и «..» его проверку проходит.
	 */
	private const MODULE_ID = '/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/';

	/** Отдаём только разметку. */
	private const EXTENSION = 'md';

	public static function isModuleId(string $moduleId): bool
	{
		return 1 === preg_match(static::MODULE_ID, $moduleId);
	}

	/**
	 * Абсолютный путь к документу либо null, если открывать его нельзя.
	 *
	 * null один и тот же на «файла нет», «файл не разметка» и «файл за
	 * пределами каталога»: иначе ответ действия сам рассказывал бы, что
	 * лежит на диске.
	 *
	 * @param string $modulesRoot каталог modules целиком
	 * @param string $moduleId идентификатор модуля, из запроса
	 * @param string $url путь документа относительно каталога модуля, из запроса
	 */
	public static function resolve(string $modulesRoot, string $moduleId, string $url): null|string
	{
		if(!static::isModuleId($moduleId))
		{
			return null;
		}

		// Нулевой байт обрывает строку внутри файловых функций: «a.md\0../b»
		// прошло бы проверку расширения, а открылось бы другим файлом. В PHP 8
		// такой путь роняет ValueError, поэтому отсекаем до вызова.
		if(str_contains($url, "\0"))
		{
			return null;
		}

		$moduleDir = realpath($modulesRoot.'/'.$moduleId);
		if(false === $moduleDir)
		{
			return null;
		}

		$path = realpath($moduleDir.'/'.$url);
		if(false === $path || !is_file($path))
		{
			return null;
		}

		// Разделитель в конце обязателен: без него каталогу «shef.options»
		// подошёл бы сосед «shef.options-backup».
		if(!str_starts_with($path, $moduleDir.DIRECTORY_SEPARATOR))
		{
			return null;
		}

		if(static::EXTENSION !== mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)))
		{
			return null;
		}

		return $path;
	}
}
