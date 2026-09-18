<?php declare(strict_types=1);

namespace Shef\Options\Installator\Strategy;

use Bitrix\Crm\Controller\Type;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\SystemException;
use Bitrix\Crm\Service;
use Shef\Options\Installator;
use Shef\Options\Installator\Entity\Crm;

/**
 * @see \Bitrix\Crm\Controller\Type
 * @link https://dev.1c-bitrix.ru/rest_help/crm/dynamic/settings.php RestApi
 */
class SmartProcessTypeStrategy
	implements IStrategy
{
	/**
	 * @throws SystemException
	 */
	public function process(Installator\IEntity $entity): Result
	{
		$result = new Result();
		
		if(!($entity instanceof Crm\ASmartProcessType))
		{
			return $result->addError(new Error(sprintf(
				'Not Support Entity %s',
				$entity::class
			)));
		}
		
		$response = $this->setSmartProcessType($entity);
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}
		
		$data['setSmartProcessType'] = $response->getData();
		
		if($entity instanceof Installator\IEntityUf)
		{
			$response = $this->setUf($entity);
			if(!$response->isSuccess())
			{
				return $result->addErrors($response->getErrors());
			}
			$data['setUf'] = $response->getData();
		}
		
		return $result->setData($data);
	}
	
	/**
	 * Добавляет | Изменяет
	 *
	 * @param Crm\ASmartProcessType $entity
	 * @return Result
	 *
	 * @throws SystemException
	 */
	private function setSmartProcessType(Crm\ASmartProcessType $entity): Result
	{
		$result = new Result();
		
		$controller = new Type();
		
		$isNew = $entity->getId() < 1;
		if(!$isNew)
		{
			$type = Service\Container::getInstance()->getDynamicTypeDataClass()::getList([
				'filter' => [
					'=ID' => $entity->getId()
				]
			])->fetchObject();
			
			$response = $controller->updateAction(
				$type,
				$entity->getInstallSettings()
			);
		}
		else
		{
			$response = $controller->addAction(
				$entity->getInstallSettings()
			);
		}
		
		if(null === $response)
		{
			return $result->addErrors($controller->getErrors());
		}
		
		$entity->setId($response['type']['id']);
		
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
	 */
	private function setUf(Installator\IEntityUf $entity): Result
	{
		$result = new Result();
		
		$manager = new Installator\Manager(new UfStrategy());
		
		$response = $manager->build($entity->getUserFields());
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}
		
		return $result;
	}
}