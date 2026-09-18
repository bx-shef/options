<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Shef\Options\TraitList;
use Shef\Options\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Вывод ставок НДС
 */
class EnumVat
	extends Enum
{
	use TraitList\Modules;
	
	protected static function getModulesList(): array
	{
		return [
			'catalog',
		];
	}
	
	// region prepareList ////
	protected static function getVatList(
		array $filter,
		null|array $select = null,
		null|array $order = null
	): array
	{
		$conf = [
			'filter' => $filter,
			'select' => array_merge(
				['ID', 'NAME', 'ACTIVE', 'RATE'],
				$select ?: []
			),
			'order' => $order ?: ['SORT' => 'ASC']
		];
		
		$collection = \Bitrix\Catalog\VatTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return [];
		}
		
		return array_map(
			function(\Bitrix\Catalog\EO_Vat $vat)
			{
				return [
					'ID' => $vat->getId(),
					'TITLE' => trim(sprintf(
						'[id: %s | rate: %s] %s %s',
						$vat->getId(),
						(float)$vat->getRate(),
						$vat->getName(),
						$vat->getActive() ? '' : ' *notActive*'
					))
				];
			},
			$collection->getAll()
		);
	}
	
	/**
	 * @throws LoaderException
	 */
	public function initSimpleList(
		array $filter,
		null|array $select = null,
		null|array $order = null
	): static
	{
		$response = $this->includeModules();
		if(!$response->isSuccess())
		{
			throw new LoaderException(join('; ', $response->getErrorMessages()));
		}
		
		$list = static::getVatList($filter, $select, $order);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
}