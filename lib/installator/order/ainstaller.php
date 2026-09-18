<?php
namespace Shef\Options\Installator\Order;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;

abstract class AInstaller
	extends \Shef\Options\Installator\AInstaller
{
	public const PROPERTY_GROUP_CODE = 'DELIVERY_SERVICE';

	protected static function includeModules(): Result
	{
		$result = new Result();
		$response = parent::includeModules();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		$list = ['sale', 'crm'];
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