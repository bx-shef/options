<?php
declare(strict_types=1);

namespace Shef\Options\Main;

use Bitrix\Main\Application;
use Bitrix\Main\Config;

class Constants
{
	public const MODULE_ID = 'shef.options';
	
	public static function getModuleId(): string
	{
		return static::MODULE_ID;
	}
	
	public static function getSettingsOptions(): array 
	{
		$list = Config\Configuration::getInstance(static::getModuleId())
			->get('options');
		
		if(!is_array($list))
		{
			$list = [];
		}
		
		return $list;
	}
	
	// region SystemUser ////
	public static function getSystemUserId(): int
	{
		return (int)Config\Option::get(static::MODULE_ID, 'DEF_systemuserid', 1);
	}
	// endregion ////

	// region Публичные пути фронта ////
	/**
	 * Каталог модуля браузеру недоступен: в поставке nginx стоит deny all на
	 * ^/bitrix/(modules|local_cache|stack_cache|managed_cache|php_interface).
	 * Поэтому install/css и install/js раскладываются установщиком в /bitrix/css
	 * и /bitrix/js (карта в .settings.php, ключ installDir), а эти два метода —
	 * единственный источник публичных путей.
	 *
	 * Расходиться им нельзя: файлы лягут в одно место, страница попросит из
	 * другого, и выглядеть это будет как «стили пропали», а не как ошибка
	 * установки. Сходимость проверяется в tests/assets_test.php.
	 *
	 * @return string
	 */
	public static function getPublicCssDir(): string
	{
		return '/bitrix/css/'.static::MODULE_ID;
	}

	/**
	 * Через дефис, а не через точку: так называются каталоги расширений
	 * Битрикса. Несимметрично с css — и так надо.
	 *
	 * @return string
	 */
	public static function getPublicJsDir(): string
	{
		return '/bitrix/js/'.str_replace('.', '-', static::MODULE_ID);
	}
	// endregion ////
}