<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\Crm;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Crm\Service;
use Shef\Options\Installator;

/**
 * Абстрацкия для смартпроцессов
 * 
 * @link https://dev.1c-bitrix.ru/rest_help/crm/dynamic/settings.php RestApi
 */
abstract class ASmartProcessType
	extends Installator\Entity\AEntity
	implements Installator\IEntityUf
{
	use Installator\Trait\EntityUfTrait;
	
	protected string $code = '';
	
	// region Init ////
	/**
	 * @return int|null
	 * @throws ArgumentException
	 * @throws ObjectNotFoundException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	protected function getExist(): null|int
	{
		$row = Service\Container::getInstance()->getDynamicTypeDataClass()::getList([
			'select' => ['ID'],
			'filter' => [
				'=CODE' => $this->getCode()
			],
			'limit' => 1
		])->fetchRaw();
		
		if($row === false)
		{
			return null;
		}
		
		return (int)$row['ID'];
	}
	// endregion ////
	
	// region Get|Set ////
	public function getCode(): string
	{
		return $this->code;
	}
	
	public function getEntityTypeId(): int
	{
		static $entityTypeId;
		
		if(null === $entityTypeId)
		{
			if($this->getId() < 1)
			{
				$entityTypeId = 0;
			}
			else
			{
				$entity = Service\Container::getInstance()->getDynamicTypeDataClass()::getList([
					'filter' => [
						'=ID' => $this->getId()
					],
					'select' => ['ENTITY_TYPE_ID']
				])->fetchObject();
				
				$entityTypeId = (int)($entity?->getEntityTypeId());
			}
		}
		
		return $entityTypeId;
	}
	
	public function getFactory(): Service\Factory\Dynamic
	{
		static $factory;
		if(null === $factory)
		{
			$factory = Service\Container::getInstance()->getFactory($this->getEntityTypeId());
		}
		
		if(null === $factory)
		{
			throw new \LogicException(sprintf(
				'No get factory by entityTypeId=%s',
				$this->getEntityTypeId()
			));
		}
		
		return $factory;
	}
	// endregion /////

	// region for Install /////
	/**
	 * @inheritDoc
	 */
	abstract public function getInstallSettings(): array;
	
	abstract protected function getUserFieldItems(): array;
	// endregion /////
	
	// region autoInstall ////
	protected function getInstallatorStrategy(): Installator\Strategy\IStrategy
	{
		return new Installator\Strategy\SmartProcessTypeStrategy();
	}
	// endregion ////
}