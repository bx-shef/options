<?php declare(strict_types=1);

namespace Shef\Options\Installator\Strategy;

use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Controller\UserFieldConfig;
use Bitrix\Main\Engine\Response\Converter;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Shef\Options\Installator;
use Shef\Options\Installator\Entity\UF;
use Shef\Options\Main\Utils;

/**
 * Реализация UF старая
 */
class UfOldStrategy
	implements IStrategy
{
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
		
		if($entity instanceof UF\AEntityEnum)
		{
			$response = $this->setEnumItems($entity);
			if(!$response->isSuccess())
			{
				return $result->addErrors($response->getErrors());
			}
			$data['setEnumItems'] = $response->getData();
		}
		
		return $result->setData($data);
	}
	
	/**
	 * Добавляет
	 *
	 * @param UF\AEntity $entity
	 * @return Result
	 */
	private function setUf(UF\AEntity $entity): Result
	{
		$result = new Result();
		
		if($entity->getId() > 0)
		{
			return $result;
		}
		
		$driver = new \CUserTypeEntity();
		
		$converter = new Converter(Converter::TO_UPPER | Converter::KEYS | Converter::TO_SNAKE);
		$allowedKeys = [
			'entityId' => true,
			'fieldName' => true,
			'userTypeId' => true,
			'xmlId' => true,
			'sort' => true,
			'multiple' => true,
			'mandatory' => true,
			'showFilter' => true,
			'showInList' => true,
			'editInList' => true,
			'isSearchable' => true,
			'settings' => true,
			'editFormLabel' => true,
			'enum' => true,
		];
		$fields = array_intersect_key($entity->getInstallSettings(), $allowedKeys);
		$fields = $converter->process($fields);

		$elementId = $driver->add($fields);
		if(false === $elementId)
		{
			$error = 'Error Add UF';
			$systemException = Utils::getCMainApplication()->getException();
			if($systemException instanceof \CApplicationException)
			{
				$error = $systemException->getString();
			}
			
			return $result->addError(new Error(sprintf(
				'Error At UF %s: %s',
				$entity->getCode(),
				$error
			)));
		}
		
		$entity->setId((int)$elementId);
		
		return $result;
	}
	
	/**
	 * Добавляет элементы enum
	 *
	 * @param UF\AEntityEnum $field
	 * @return Result
	 *
	 * @link: https://dev.1c-bitrix.ru/api_help/main/reference/cuserfieldenum/setenumvalues.php Bitrix.API
	 */
	private function setEnumItems(UF\AEntityEnum $field): Result
	{
		$result = new Result();
		
		if($field->getId() < 1)
		{
			return $result->addError(new Error(sprintf(
				'UF %s not installed',
				$field->getCode(),
			)));
		}
		
		$enumList = [];
		$index = 0;
		
		/** @var UF\EnumItem $enum */
		foreach($field->getEnums()->toArray() as $enum)
		{
			$keyEnum = null;
			if($enum->getId() > 0)
			{
				$keyEnum = $enum->getId();
			}
			else
			{
				$keyEnum = 'n'.$index;
				$index++;
			}
			
			$enumList[$keyEnum] = $enum->getInstallSettings();
		}
		
		$obEnum = new \CUserFieldEnum;
		$response = $obEnum->SetEnumValues($field->getId(), $enumList);
		if($response === false)
		{
			$error = 'Error Add Enum Field';
			$systemException = Utils::getCMainApplication()->getException();
			if($systemException instanceof \CApplicationException)
			{
				$error = $systemException->getString();
			}
			
			return $result->addError(new Error(sprintf(
				'Error At UF %s: %s',
				$field->getCode(),
				$error
			)));
		}
		
		$field->prepareEnumsId();
		
		return $result;
	}
}