<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Type\Dictionary;
use Shef\Options\Main\Utils;
use Shef\Options\Installator;

/**
 * Class Manager
 * @package Shef\Options\Installator\UF
 *
 * Установщик UF
 *
 */
class Manager
	implements Installator\IInstallator
{
	/**
	 * По списку добавляет UF
	 *
	 * Если поле уже существует, пропустит его
	 *
	 * @param Dictionary[] $list
	 * @return Result
	 */
	public static function build(Dictionary $list): Result
	{
		/** @var Type\AUF $field */

		$result = new Result();
		foreach($list as $field)
		{
			$response = static::process($field);
			if(!$response->isSuccess())
			{
				$result->addErrors($response->getErrors());
			}
		}

		return $result;
	}

	/**
	 * Обрабатывает UF
	 * @param Type\AUF $field
	 * @return Result
	 */
	private static function process(Type\AUF $field): Result
	{
		$result = new Result();

		if($field->getId() > 0)
		{
			return $result;
		}

		$response = static::add($field);
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		if($field instanceof Type\AUFEnum)
		{
			$response = static::setEnum($field);
			if(!$response->isSuccess())
			{
				return $result->addErrors($response->getErrors());
			}
		}

		return $result->setData($response->getData());
	}

	/**
	 * Добавляет UF
	 * @param Type\AUF $field
	 * @return Result
	 */
	private static function add(Type\AUF $field): Result
	{
		$result = new Result();

		$entity = new \CUserTypeEntity();

		$elementId = $entity->add($field->getInstallSettings());
		if(false === $elementId)
		{
			$error = 'error add field';
			$strEx = Utils::getCMainApplication()->getException();
			if($strEx instanceof \CApplicationException)
			{
				$error = $strEx->getString();
			}

			return $result->addError(new Error('Error at UF '.$field->getName().': '.$error));
		}

		$field->setId((int)$elementId);

		return $result;
	}

	/**
	 * Добавляет элементы enum
	 * @see: https://dev.1c-bitrix.ru/api_help/main/reference/cuserfieldenum/setenumvalues.php
	 *
	 * @param Type\AUF $field
	 * @return Result
	 */
	private static function setEnum(Type\AUFEnum $field): Result
	{
		$result = new Result();

		$conf = [];
		$index = 0;

		/** @var Type\AEnumItem $enum */
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

			$conf[$keyEnum] = $enum->getInstallSettings();
		}

		$obEnum = new \CUserFieldEnum;
		$response = $obEnum->SetEnumValues($field->getId(), $conf);
		if($response === false)
		{
			$error = 'error add enum field';
			$strEx = Utils::getCMainApplication()->getException();
			if($strEx instanceof \CApplicationException)
			{
				$error = $strEx->getString();
			}

			return $result->addError(new Error('Error at UF '.$field->getName().': '.$error));
		}

		$field->reInitEnums();

		return $result;
	}
}