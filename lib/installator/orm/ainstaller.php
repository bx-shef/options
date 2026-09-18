<?php
namespace Shef\Options\Installator\Orm;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;

abstract class AInstaller
	extends \Shef\Options\Installator\AInstaller
{
	public static function installModel(array $options): Result
	{
		/** @var \Bitrix\Main\ORM\Data\DataManager $entityClass */
		/** @var \Bitrix\Main\ORM\Entity $entity */

		$result = new Result();

		$response = static::includeModules();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		$db = \Bitrix\Main\Application::getConnection();

		$entityClass = $options['class'];
		$entity = $entityClass::getEntity();

		if (!$db->isTableExists($entity->getDBTableName()))
		{
			try
			{
				$entity->createDbTable();
				return $result->setData(['IS_NEW' => true]);
			}
			catch(\Exception $e)
			{
				return $result->addError(new Error($e->getMessage()));
			}
		}
		return $result->setData(['IS_NEW' => false]);
	}
}