<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Crm;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Shef\Options\TraitList;

Loc::loadMessages(__FILE__);

/**
 * Вывод пресетов реквизитов
 */
class EnumCrmRqPreset
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
	protected static function getRqPresetList(
		array $filter,
		?array $select = null,
		?array $order = null
	): array
	{
		$conf = [
			'filter' => $filter,
			'select' => array_merge(
				['ID', 'NAME', 'ACTIVE', 'COUNTRY_ID'],
				$select ?: []
			),
			'order' => $order ?: ['SORT' => 'ASC']
		];
		
		$collection = Crm\PresetTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return [];
		}
		
		$countryList = \Bitrix\Crm\EntityPreset::getCountryList();
		
		return array_map(
			function(Crm\EO_Preset $type)
				use ($countryList)
			{
				return [
					'ID' => $type->getId(),
					'TITLE' => sprintf(
						'[id: %s | %s] %s%s',
						$type->getId(),
						$countryList[$type->getCountryId()] ?: $type->getCountryId(),
						$type->getName(),
						!$type->getActive()
							? '**not active**'
							: ''
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
		
		$list = static::getRqPresetList($filter, $select, $order);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
	}