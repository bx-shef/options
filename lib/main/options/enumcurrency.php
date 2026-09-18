<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Shef\Options\TraitList;
use Shef\Options\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Вывод валют
 */
class EnumCurrency
	extends Enum
{
	use TraitList\Modules;
	
	protected static function getModulesList(): array
	{
		return [
			'currency',
		];
	}
	
	// region prepareList ////
	protected static function getCurrencyList(
		array $filter,
		null|array $select = null,
		null|array $order = null
	): array
	{
		$conf = [
			'filter' => $filter,
			'select' => array_merge(
				['CURRENCY', 'BASE', 'CURRENT_LANG_FORMAT'],
				$select ?: []
			),
			'order' => $order ?: ['SORT' => 'ASC']
		];
		
		$collection = \Bitrix\Currency\CurrencyTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return [];
		}
		
		return array_map(
			function(\Bitrix\Currency\EO_Currency $currency)
			{
				return [
					'ID' => $currency->getCurrency(),
					'TITLE' => trim(sprintf(
						'[%s] %s %s',
						$currency->getCurrency(),
						$currency->getCurrentLangFormat()?->getFullName(),
						$currency->getBase() ? ' *BASE*' : ''
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
		
		$list = static::getCurrencyList($filter, $select, $order);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
}