<?php declare(strict_types=1);

namespace Shef\Options\Installator\Strategy;

use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Controller\UserFieldConfig;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Shef\Options\Installator;
use Shef\Options\Installator\Entity\UF;

/**
 * @see UserFieldConfig
 * @link https://dev.1c-bitrix.ru/rest_help/userfieldconfig/index.php RestApi
 */
class UfStrategy
	implements IStrategy
{
	/**
	 * @throws ArgumentOutOfRangeException
	 */
	public function process(Installator\IEntity $entity): Result
	{
		$result = new Result();
		
		if(!($entity instanceof UF\AEntity))
		{
			return $result->addError(new Error(sprintf(
				'Not Support Entity %s',
				$entity::class
			)));
		}
		
		$data = [];
		
		$response = $this->setUf($entity);
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}
		$data['setUf'] = $response->getData();
		
		$this->fixUf($entity);
		
		return $result->setData($data);
	}
	
	/**
	 * Добавляет | Изменяет
	 *
	 * @param UF\AEntity $entity
	 * @return Result
	 * @throws ArgumentOutOfRangeException
	 */
	private function setUf(UF\AEntity $entity): Result
	{
		$result = new Result();
		
		$controller = new UserFieldConfig();
		
		$isNew = $entity->getId() < 1;
		if(!$isNew)
		{
			$response = $controller->updateAction(
				$entity->getModuleId(),
				$entity->getId(),
				$entity->getInstallSettings()
			);
		}
		else
		{
			$response = $controller->addAction(
				$entity->getModuleId(),
				$entity->getInstallSettings()
			);
		}
		
		if(null === $response)
		{
			return $result->addErrors($controller->getErrors());
		}

		$entity->setId((int)$response['field']['id']);
		
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
			
			if($entity instanceof UF\AEntityEnum)
			{
				$entity->prepareEnumsId();
			}
		}

		return $result;
	}
	
	/**
	 * Используем для донастройки UF тк UserFieldConfig не все поддерживает
	 * @param UF\AEntity $entity
	 * @return void
	 *
	 * @see UserFieldConfig::prepareFields
	 */
	private function fixUf(UF\AEntity $entity): void
	{
		if($entity->getId() < 1)
		{
			return;
		}
		
		$driver = new \CUserTypeEntity();
		
		if($entity->isEditable() === false)
		{
			$driver->update(
				$entity->getId(),
				[
					'EDIT_IN_LIST' => 'N'
				]
			);
		}
	}
}