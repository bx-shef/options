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
}