<?php declare(strict_types=1);

namespace Shef\Options\Installator\Strategy;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\SystemException;
use Bitrix\Crm;
use Shef\Options\Installator;
use Shef\Options\Installator\Entity;

/**
 * @see \Bitrix\Crm\PresetTable
 */
class CrmPresetStrategy
	implements IStrategy
{
	/**
	 * @throws SystemException
	 */
	public function process(Installator\IEntity $entity): Result
	{
		$result = new Result();
		
		if(!($entity instanceof Entity\Crm\APreset))
		{
			return $result->addError(new Error(sprintf(
				'Not Support Entity %s',
				$entity::class
			)));
		}
		
		if($entity instanceof Installator\IEntityUf)
		{
			$response = $this->setUf($entity);
			if(!$response->isSuccess())
			{
				return $result->addErrors($response->getErrors());
			}
			$data['setUf'] = $response->getData();
		}
		
		$response = $this->setPreset($entity);
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}
		
		$data['setPreset'] = $response->getData();
		
		return $result->setData($data);
	}
	
	/**
	 * Добавляет | Изменяет
	 *
	 * @param Entity\Crm\APreset $entity
	 * @return Result
	 *
	 * @throws SystemException
	 */
	private function setPreset(Entity\Crm\APreset $entity): Result
	{
		$result = new Result();
		
		$model = $entity->getInstallSettings();
		
		$isNew = $entity->getId() < 1;
		if(!$isNew)
		{
			$preset = Crm\PresetTable::wakeUpObject($entity->getId());
		}
		else
		{
			$preset = Crm\PresetTable::createObject();
			$preset->setDateCreate(new \Bitrix\Main\Type\DateTime());
			$preset->setEntityTypeId($entity->getEntityTypeId());
			$preset->setCountryId($entity->getCountryId());
			$preset->setXmlId($entity->getCode());
			$preset->setActive(true);
		}
		
		$this->fixOrm($preset);
		
		$preset->setName($entity->getName());
		$preset->setSort($entity->getSort());
		
		/** @see \Shef\Options\Installator\Strategy\CrmPresetStrategy::fixOrm */
		$preset->setSettings(serialize($entity->getInstallSettings()));

		$response = $preset->save();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}
		
		$entity->setId($preset->getId());
		
		if(in_array($entity->getDefaultForEntity(), [
			\CCrmOwnerType::Company,
			\CCrmOwnerType::Contact,
		]))
		{
			Crm\EntityRequisite::setDefaultPresetId(
				$entity->getDefaultForEntity(),
				$preset->getId()
			);
		}
		
		if($isNew)
		{
			if($entity instanceof Installator\ISaveOption)
			{
				\Bitrix\Main\Config\Option::set(
					$entity->getModuleIdForSaveOption(),
					$entity->getCodeForSaveOption(),
					$entity->getId()
				);
			}
		}
		
		return $result;
	}
	
	/**
	 * Добавляет | Изменяет UF в сущности
	 *
	 * @param Installator\IEntityUf $entity
	 * @return Result
	 * 
	 * @memo we use old uf installer
	 */
	private function setUf(Installator\IEntityUf $entity): Result
	{
		$result = new Result();
		
		$manager = new Installator\Manager(new UfOldStrategy());
		
		$response = $manager->build($entity->getUserFields());
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}
		
		return $result;
	}
	
	// region Tools ////
	/**
	 * Исправление serialize
	 *
	 * @param Crm\EO_Preset $preset
	 * @return void
	 * @throws SystemException
	 * @throws \Bitrix\Main\ArgumentException
	 *
	 * @memo bad code We need serialize(unserialize(unserialize(value)))
	 * @see \Bitrix\Crm\PresetTable::getMap -> it mast use \Bitrix\Main\ORM\Fields\ArrayField
	 */
	private function fixOrm(\Bitrix\Crm\EO_Preset $preset): void
	{
		$fieldSettings = $preset::$dataClass::getEntity()->getField('SETTINGS');
		
		if(
			$fieldSettings instanceof \Bitrix\Main\ORM\Fields\TextField
			&& $fieldSettings->isSerialized()
		)
		{
			$fieldSettings->addSaveDataModifier(
				function(mixed $value): string
				{
					$oriValue = $value;
					if(!is_array($value))
					{
						try
						{
							$value = unserialize($value);
							if(!is_array($value))
							{
								$value = unserialize($value);
							}
							
							if(!is_array($value))
							{
								throw new \Exception('Not Array At Result');
							}
						}
						catch(\Throwable $throwable)
						{
							$value = $oriValue;
						}
					}
					
					if(is_string($value))
					{
						return $value;
					}
					
					return serialize($value);
				}
			);
		}
	}
	// endregion ////
}