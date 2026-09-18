<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Shef\Options\TraitList;
use Shef\Options\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Вывод единиц измерения
 *
 * @memo если нет названий, то нужно пересохранить сущности
 */
class EnumMeasure
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
	protected static function getMeasureList(
		array $filter,
		null|array $select = null,
		null|array $order = null
	): array
	{
		$conf = [
			'filter' => $filter,
			'select' => array_merge(
				['ID', 'MEASURE_TITLE', 'SYMBOL', 'IS_DEFAULT', 'SYMBOL_INTL'],
				$select ?: []
			),
			'order' => $order ?: [
				'IS_DEFAULT' => 'DESC',
				'ID' => 'ASC'
			]
		];
		
		$collection = \Bitrix\Catalog\MeasureTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return [];
		}
		
		return array_map(
			function(\Bitrix\Catalog\EO_Measure $measure)
			{
				return [
					'ID' => $measure->getId(),
					'TITLE' => trim(sprintf(
						'[id: %s | %s] %s %s',
						$measure->getId(),
						$measure->getSymbol() ?: $measure->getSymbolIntl(),
						$measure->getMeasureTitle(),
						$measure->getIsDefault() ? '*DEFAULT*' : ''
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
		
		$list = static::getMeasureList($filter, $select, $order);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
}