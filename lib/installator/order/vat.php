<?php
namespace Shef\Options\Installator\Order;

use Bitrix\Main\Result;
use Bitrix\Main\Error;

class Vat
	extends AInstaller
{
	public static function getDefVat(): Result
	{
		$result = new Result();

		$response = static::includeModules();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		$vatList = \CCrmVat::GetAll();
		if(!is_array($vatList))
		{
			return $result->addError(new Error('Empty VAT list. See crm'));
		}
		if(count($vatList) < 1)
		{
			return $result->addError(new Error('Empty VAT list. See crm'));
		}

		$result->setData([
			'VAT' => reset($vatList)
		]);

		return $result;
	}
}