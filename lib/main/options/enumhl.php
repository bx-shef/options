<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Highloadblock as HL;
use Shef\Options\TraitList;
use Shef\Options\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Вывод HL
 */
class EnumHl
	extends Enum
{
	use TraitList\Modules;
	
	protected static function getModulesList(): array
	{
		return [
			'highloadblock',
		];
	}
	
	// region prepareList ////
	protected static function getHlList(
		array $filter,
		null|array $select = null,
		null|array $order = null
	): array
	{
		$conf = [
			'filter' => $filter,
			'select' => array_merge(
				['ID', 'NAME', 'LANG'],
				$select ?: []
			),
			'order' => $order ?: ['ID' => 'ASC']
		];
		
		$collection = HL\HighloadBlockTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return [];
		}
		
		return array_map(
			function(HL\HighloadBlock $hl)
			{
				return [
					'ID' => $hl->getId(),
					'TITLE' => trim(sprintf(
						'[%s | id: %s] %s',
						$hl->getName(),
						$hl->getId(),
						$hl->getLang()?->getName()
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
		
		$list = static::getHlList($filter, $select, $order);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
}