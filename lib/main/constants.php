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
	/**
	 * Пользователь, от имени которого работают модули линейки, пока в
	 * настройках не выбран другой.
	 */
	public const DEFAULT_SYSTEM_USER_ID = 1;
	
	/**
	 * Идентификатор служебного пользователя из настроек модуля.
	 *
	 * Приведение (int) здесь не годится: настройка хранится строкой и приходит
	 * из формы. intval('') и intval('нет') дают 0 — работа «от имени никого»,
	 * а intval('5 62') даёт 5 — работа от имени пользователя, которого никто
	 * не выбирал. Поэтому разбираем строго: целое больше нуля либо строка из
	 * одних цифр без ведущего нуля. Всё остальное считаем незаполненным и
	 * берём умолчание — ровно то же, что вернулось бы для несохранённой опции.
	 */
	public static function getSystemUserId(): int
	{
		$value = Config\Option::get(
			static::MODULE_ID,
			'DEF_systemuserid',
			(string) static::DEFAULT_SYSTEM_USER_ID
		);
		
		if(is_int($value))
		{
			return $value > 0 ? $value : static::DEFAULT_SYSTEM_USER_ID;
		}
		
		if(is_string($value) && 1 === preg_match('/^[1-9][0-9]*$/', $value))
		{
			return (int) $value;
		}
		
		return static::DEFAULT_SYSTEM_USER_ID;
	}
	// endregion ////

	// region Публичные пути фронта ////
	/**
	 * Каталог модуля браузеру недоступен: в поставке nginx стоит deny all на
	 * ^/bitrix/(modules|local_cache|stack_cache|managed_cache|php_interface).
	 * Поэтому install/css раскладывается установщиком в /bitrix/css (карта в
	 * .settings.php, ключ installDir), а эти два метода — единственный
	 * источник публичных путей.
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
	 * Своего JS модуль больше не раскладывает: фронт вкладки документации ушёл
	 * из модуля вместе с самой вкладкой. Метод остался затем, что установщику
	 * нужно знать, какой каталог убрать на порталах, обновившихся с версий до
	 * 3.0.0. @see \shef_options::getLegacyDirList()
	 *
	 * @return string
	 */
	public static function getPublicJsDir(): string
	{
		return '/bitrix/js/'.str_replace('.', '-', static::MODULE_ID);
	}
	// endregion ////
}