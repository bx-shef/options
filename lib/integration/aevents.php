<?php
namespace Shef\Options\Integration;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;

/*/
	if (!static::isEnabledHandler(__FUNCTION__, $entityId, 'ENTITY_TYPE')) return true;
	static::disableHandler(__FUNCTION__, $entityId, 'ENTITY_TYPE');
	// ... ////
	static::enableHandler(__FUNCTION__, $entityId, 'ENTITY_TYPE');
//*/

abstract class AEvents
{
	const LOG_NAME = 'shef-Options-integration-AEvents';
	protected static $skeepLog = true;
	protected static $handlerDisallow = [];

	protected static function getCode($typeAction, $elementId = 0, $entity = 'notset')
	{
		return implode('_', [
			$typeAction
			,$elementId
			,$entity
		]);
	}

	public static function disableHandler($typeAction, $elementId = 0, $entity = 'notset'){
		$key = static::getCode($typeAction, $elementId, $entity);
		if(!isset(static::$handlerDisallow[$key])){
			static::$handlerDisallow[$key] = 0;
		}
		static::$handlerDisallow[$key]--;
		
	}
	
	public static function enableHandler($typeAction, $elementId = 0, $entity = 'notset'){
		$key = static::getCode($typeAction, $elementId, $entity);
		if(!isset(static::$handlerDisallow[$key])){
			static::$handlerDisallow[$key] = 0;
		}
		static::$handlerDisallow[$key]++;
	}
	
	public static function isEnabledHandler($typeAction, $elementId = 0, $entity = 'notset'){
		$key = static::getCode($typeAction, $elementId, $entity);
		if(!isset(static::$handlerDisallow[$key])){
			static::$handlerDisallow[$key] = 0;
		}
		return (static::$handlerDisallow[$key] >= 0);
	}
	
	protected static function log(Array $value = [], $isNotSkeep = false)
	{
		// skeep log ////
		if(!$isNotSkeep && static::$skeepLog)
		{
			return true;
		}

		_log($value, static::LOG_NAME);
	}
}