<?php declare(strict_types=1);

namespace Shef\Options\TraitList;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;

/*/
	if (!static::isEnabledHandler(__FUNCTION__, $entityId, 'ENTITY_TYPE')) return true;
	static::disableHandler(__FUNCTION__, $entityId, 'ENTITY_TYPE');
	// ... ////
	static::enableHandler(__FUNCTION__, $entityId, 'ENTITY_TYPE');
//*/

/**
 * Используется в событиях для блокировок от повторных вызовов
 *
 */
trait Events
{
	protected static array $handlerDisallow = [];

	protected static function getCode(
		string $typeAction,
		int $entityId = 0,
		string $entityType = 'notset'
	): string
	{
		return implode('_', [
			$typeAction,
			$entityId,
			$entityType
		]);
	}

	public static function disableHandler(
		string $typeAction,
		int $entityId = 0,
		string $entityType = 'notset'
	): void
	{
		$key = static::getCode($typeAction, $entityId, $entityType);
		
		if(!isset(static::$handlerDisallow[$key]))
		{
			static::$handlerDisallow[$key] = 0;
		}
		
		static::$handlerDisallow[$key]--;
	}
	
	public static function enableHandler(
		string $typeAction,
		int $entityId = 0,
		string $entityType = 'notset'
	): void
	{
		$key = static::getCode($typeAction, $entityId, $entityType);
		
		if(!isset(static::$handlerDisallow[$key]))
		{
			static::$handlerDisallow[$key] = 0;
		}
		
		static::$handlerDisallow[$key]++;
	}
	
	public static function isEnabledHandler(
		string $typeAction,
		int $entityId = 0,
		string $entityType = 'notset'
	): bool
	{
		$key = static::getCode($typeAction, $entityId, $entityType);
		
		if(!isset(static::$handlerDisallow[$key]))
		{
			static::$handlerDisallow[$key] = 0;
		}
		return (static::$handlerDisallow[$key] >= 0);
	}
}