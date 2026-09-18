<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Constants;

/**
 * Используется для получения данных цен и валют
 */
trait Price
{
	public static function getBaseCurrency(): string
	{
		if(\Bitrix\Main\Loader::includeModule('crm'))
		{
			return \CCrmCurrency::GetBaseCurrencyID();
		}

		return 'BYN';
	}
}