<?php declare(strict_types=1);

namespace Shef\Options\TraitList;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;

/**
 * Используется для подключения модулей
 */
trait Modules
{
	/**
	 * Список модулей для загрузки
	 * @return string[]
	 */
	abstract protected static function getModulesList(): array;
	
	/**
	 * Загрузка модулей
	 * @return Result
	 * @throws \Bitrix\Main\LoaderException
	 */
	protected static function includeModules(): Result
	{
		$result = new Result;

		foreach(static::getModulesList() as $module)
		{
			if(!Loader::includeModule($module))
			{
				return $result->addError(new Error('module '.$module.' not loaded'));
			}
		}

		return $result;
	}
}