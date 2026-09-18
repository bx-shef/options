<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Constants;

/**
 * Используется для получения данных каталога
 */
trait Catalog
{
	protected static ?int $catalogId = null;
	protected static ?int $skuId = null;
	
	abstract public static function getModuleId(): string;
	
	public static function getCatalogId(): int
	{
		if(null === static::$catalogId)
		{
			if(\Bitrix\Main\Loader::includeModule('crm'))
			{
				static::$catalogId = (int)\Bitrix\Crm\Product\Catalog::getDefaultId();
			}
		}

		return (int)static::$catalogId;
	}

	public static function getCatalogSKUId(): int
	{
		if(null === static::$skuId)
		{
			if(\Bitrix\Main\Loader::includeModule('crm'))
			{
				static::$skuId = (int)\Bitrix\Crm\Product\Catalog::getDefaultOfferId();
			}
		}

		return (int)static::$skuId;
	}

	protected static function getPropertyId(
		int $iblockId,
		string $propCodeId,
		string $propCodeValPrev,
		string $propCur
	): int
	{
		if(!\Bitrix\Main\Loader::includeModule('iblock'))
		{
			return 0;
		}

		$propPrev = trim(\Bitrix\Main\Config\Option::get(static::getModuleId(), $propCodeValPrev, ''));

		if($propCur === '')
		{
			\Bitrix\Main\Config\Option::set(static::getModuleId(), $propCodeId, 0);
			\Bitrix\Main\Config\Option::set(static::getModuleId(), $propCodeValPrev, '');
			return 0;
		}
		elseif($propPrev <> $propCur)
		{

			$cursor = \CIBlockProperty::GetList(
				[],
				[
					'IBLOCK_ID' => $iblockId,
					'CODE' => $propCur
				]
			);

			while($propertyUrl = $cursor->GetNext())
			{
				\Bitrix\Main\Config\Option::set(static::getModuleId(), $propCodeId, (int)$propertyUrl['ID']);
				\Bitrix\Main\Config\Option::set(static::getModuleId(), $propCodeValPrev, $propCur);
				break;
			}
		}

		return (int)\Bitrix\Main\Config\Option::get(static::getModuleId(), $propCodeId, 0);
	}
}