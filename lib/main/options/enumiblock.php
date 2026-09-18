<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Shef\Options\TraitList;
use Shef\Options\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Вывод инфоблоков
 */
class EnumIblock
	extends Enum
{
	use TraitList\Modules;
	
	protected static function getModulesList(): array
	{
		return [
			'iblock',
		];
	}
	
	// region prepareList ////
	protected static function getIblockList(
		array $filter,
		null|array $select = null,
		null|array $order = null
	): array
	{
		$conf = [
			'filter' => $filter,
			'select' => array_merge(
				['ID', 'IBLOCK_TYPE_ID', 'CODE', 'API_CODE', 'NAME', 'ACTIVE', 'TYPE'],
				$select ?: []
			),
			'order' => $order ?: [
				'TYPE.SORT' => 'ASC',
				'SORT' => 'ASC'
			]
		];
		
		$collection = \Bitrix\Iblock\IblockTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return [];
		}
		
		return array_map(
			function(\Bitrix\Iblock\Iblock $iblock)
			{
				return [
					'ID' => $iblock->getId(),
					'TITLE' => trim(sprintf(
						'[%s:%s%s | id: %s] %s %s',
						
						$iblock->getIblockTypeId(),
						$iblock->getCode(),
						$iblock->getApiCode() ? ' | ORM: '.$iblock->getApiCode().'': '',
						$iblock->getId(),
						
						$iblock->getName(),
						
						$iblock->getActive() ? '' : ' *notActive*'
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
		
		$list = static::getIblockList($filter, $select, $order);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
}