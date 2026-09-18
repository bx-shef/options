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
use Bitrix\Crm;
use Bitrix\Main\Type;
use Shef\Options\TraitList;

Loc::loadMessages(__FILE__);

/**
 * Вывод данных справочников CRM
 */
class EnumCrmSource
	extends Enum
{
	use TraitList\Modules;
	
	protected string $entityType = '';
	
	protected static function getModulesList(): array
	{
		return [
			'crm',
		];
	}
	
	public function setEntityType(string $entityType): static
	{
		$this->entityType = $entityType;
		return $this;
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
	 */
	protected static function getSourceList(array $filter): array
	{
		$result = [];
		
		$conf = [
			'filter' => $filter,
			'select' => ['ID', 'STATUS_ID', 'NAME', 'SORT'],
			'order' => ['ID' => 'ASC']
		];
		
		$collection = Crm\StatusTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return $result;
		}
		
		$result = array_map(
			function(Crm\EO_Status $status)
			{
				return [
					'ID' => $status->getStatusId(),
					'SORT' => $status->getSort(),
					'TITLE' => sprintf(
						'[id: %s] %s',
						$status->getStatusId(),
						$status->getName()
					)
				];
			},
			$collection->getAll()
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
		
		$list = static::getSourceList($filter);
		
		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);
		
		$this->setList($list);
		
		return $this;
	}
	// endregion ////
}