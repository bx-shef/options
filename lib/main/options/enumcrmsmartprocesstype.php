<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Crm\Service;
use Bitrix\Crm\Model\Dynamic;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Shef\Options\TraitList;

Loc::loadMessages(__FILE__);

/**
 * Вывод типов смарт-процессов
 */
class EnumCrmSmartProcessType
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
	 * Построение списка
	 *
	 * @throws ArgumentException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	protected static function getSmartProcessTypeList(
		array $filter,
		?array $select = null,
		?array $order = null
	): array
	{
		$conf = [
			'filter' => $filter,
			'select' => array_merge(
				['ID', 'NAME', 'TITLE', 'CODE', 'ENTITY_TYPE_ID'],
				$select ?: []
			),
			'order' => $order ?: ['ID' => 'ASC']
		];
		
		$collection = Service\Container::getInstance()->getDynamicTypeDataClass()::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return [];
		}
		
		return array_map(
			function(Dynamic\Type $type)
			{
				return [
					'ID' => $type->getId(),
					'TITLE' => sprintf(
						'[%s | id: %s | entityTypeId: %s] %s',
						$type->getName(),
						$type->getId(),
						$type->getEntityTypeId(),
						$type->getTitle(),
					)
				];
			},
			$collection->getAll()
		);
	}
	
	/**
	 * Получить список для вывода
	 *
	 * @param array $filter
	 * @param array|null $select
	 * @param array|null $order
	 * @return $this
	 * @throws ArgumentException
	 * @throws LoaderException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	public function initSimpleList(
		array $filter,
		?array $select = null,
		?array $order = null
	): static
	{
		$response = $this->includeModules();
		if(!$response->isSuccess())
		{
			throw new LoaderException(join('; ', $response->getErrorMessages()));
		}
		
		$list = static::getSmartProcessTypeList($filter, $select, $order);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
}