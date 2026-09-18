<?php
namespace Shef\Options\Installator\Crm;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;

abstract class AInstaller
	extends \Shef\Options\Installator\AInstaller
{
	protected static function includeModules(): Result
	{
		$result = new Result();
		$response = parent::includeModules();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		$list = ['crm'];
		foreach($list as $module)
		{
			if(!Loader::includeModule($module))
			{
				return $result->addError(new Error($module.' not installed'));
			}
		}
		return $result;
	}
}