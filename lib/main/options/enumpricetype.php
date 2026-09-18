<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Shef\Options\TraitList;
use Shef\Options\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Вывод типов цен
 */
class EnumPriceType
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
	protected static function getPriceTypeList(
		array $filter,
		null|array $select = null,
		null|array $order = null
	): array
	{
		$conf = [
			'filter' => $filter,
			'select' => array_merge(
				['ID', 'NAME', 'BASE', 'CURRENT_LANG'],
				$select ?: []
			),
			'order' => $order ?: ['SORT' => 'ASC']
		];
		
		$collection = \Bitrix\Catalog\GroupTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return [];
		}
		
		return array_map(
			function(\Bitrix\Catalog\EO_Group $group)
			{
				return [
					'ID' => $group->getId(),
					'TITLE' => trim(sprintf(
						'[%s] %s %s',
						$group->getName(),
						$group->getCurrentLang()?->getName(),
						$group->getBase() ? ' *BASE*' : ''
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
		
		$list = static::getPriceTypeList($filter, $select, $order);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
}