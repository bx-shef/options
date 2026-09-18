<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Crm\Category\Entity;
use Bitrix\Crm\Category\DealCategory;
use Bitrix\Main\Type;
use Shef\Options\TraitList;

Loc::loadMessages(__FILE__);

/**
 * Вывод направлений сделок
 */
class EnumCrmDealCategory
	extends Enum
{
	use TraitList\Modules;
	
	protected static function getModulesList(): array
	{
		return [
			'crm',
		];
	}
	
	// region prepareList ////
	/**
	 * @param array $filter
	 * @return array
	 * @throws ArgumentException
	 * @throws ArgumentNullException
	 * @throws ArgumentOutOfRangeException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 *
	 * @memo use $filter['NOT_USE_DEFAULT'] for skip default DealCategory
	 */
	protected static function getDealCategoryList(array $filter): array
	{
		$result = [];
		
		if(!isset($filter['NOT_USE_DEFAULT']))
		{
			$result[] = [
				'ID' => 0,
				'SORT' => DealCategory::getDefaultCategorySort(),
				'TITLE' => sprintf(
					'[id: %s | **default**] %s',
					0,
					DealCategory::getDefaultCategoryName()
				)
			];
			
			unset($filter['NOT_USE_DEFAULT']);
		}
		
		$conf = [
			'filter' => $filter,
			'select' => ['ID', 'NAME', 'SORT'],
			'order' => ['ID' => 'ASC']
		];
		
		$collection = Entity\DealCategoryTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return $result;
		}
		
		$result = array_merge(
			$result,
				array_map(
				function(Entity\EO_DealCategory $category)
				{
					return [
						'ID' => $category->getId(),
						'SORT' => $category->getSort(),
						'TITLE' => sprintf(
							'[id: %s] %s',
							$category->getId(),
							$category->getName()
						)
					];
				},
				$collection->getAll()
			)
		);
		
		Type\Collection::sortByColumn(
			$result,
			['SORT' => [SORT_NUMERIC, SORT_ASC]]
		);
		
		return $result;
	}
	
	/**
	 * Получить список для вывода
	 *
	 * @param array $filter
	 * @return $this
	 *
	 * @throws LoaderException
	 * @throws ArgumentException
	 * @throws ArgumentNullException
	 * @throws ArgumentOutOfRangeException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	public function initSimpleList(array $filter): static
	{
		$response = $this->includeModules();
		if(!$response->isSuccess())
		{
			throw new LoaderException(join('; ', $response->getErrorMessages()));
		}
		
		$list = static::getDealCategoryList($filter);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
}