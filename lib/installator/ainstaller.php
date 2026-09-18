<?php
namespace Shef\Options\Installator;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;

abstract class AInstaller
{
	protected static function includeModules(): Result
	{
		$result = new Result();
		return $result;
	}

	protected static function getUFType(): string
	{
		return '';
	}

	protected static function addUf(array $options): Result
	{
		global $APPLICATION;
		$result = new Result();
		$entity = new \CUserTypeEntity();
		$ID = $entity->Add($options);
		if($ID === false)
		{
			$ex = $APPLICATION->GetException();
			if(!($ex instanceof \CApplicationException))
			{
				$ex = null;
			}
			return $result->addError(new Error($ex->GetString()));
		}

		return $result->setData(['ID' => (int)$ID]);
	}
	protected static function updateUf(int $entityId, array $options): Result
	{
		global $APPLICATION;
		$result = new Result();
		$entity = new \CUserTypeEntity();
		$response = $entity->Update($entityId, $options);
		if($response === false)
		{
			$ex = $APPLICATION->GetException();
			if(!($ex instanceof \CApplicationException))
			{
				$ex = null;
			}
			return $result->addError(new Error($ex->GetString()));
		}

		return $result->setData(['ID' => $entityId]);
	}
	public static function installUf(array $options): Result
	{
		$result = new Result();

		$response = static::includeModules();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}
		$propertyIds = [];
		$crmUFList = $options['list'];
		foreach($crmUFList as $ufFields)
		{
			$curField = \Bitrix\Main\UserFieldTable::getList([
				'select' => ['ID', 'FIELD_NAME'],
				'filter' => [
					'=ENTITY_ID' => static::getUFType(),
					'=FIELD_NAME' => $ufFields['FIELD_NAME'],
				],
				'order' => ['SORT' => 'ASC']
			])->fetchRaw();
			if(is_array($curField))
			{
				// update ////
				$params = [
					'MULTIPLE' => $ufFields['MULTIPLE'],
					'MANDATORY' => $ufFields['MANDATORY'],
					'SHOW_FILTER' => $ufFields['SHOW_FILTER'],
					'SHOW_IN_LIST' => $ufFields['SHOW_IN_LIST'],
					'EDIT_IN_LIST' => $ufFields['EDIT_IN_LIST'],
					'IS_SEARCHABLE' => $ufFields['IS_SEARCHABLE'],
					'SETTINGS' => $ufFields['SETTINGS'],
					'EDIT_FORM_LABEL' => $ufFields['EDIT_FORM_LABEL'],
					'LIST_COLUMN_LABEL' => $ufFields['LIST_COLUMN_LABEL'],
					'LIST_FILTER_LABEL' => $ufFields['LIST_FILTER_LABEL'],
					'HELP_MESSAGE' => $ufFields['HELP_MESSAGE'],
				];
				$response = static::updateUf($curField['ID'], $params);
				if(!$response->isSuccess())
				{
					return $result->addErrors($response->getErrors());
				}
				// region enumeration /////
				if($ufFields['USER_TYPE_ID'] === 'enumeration')
				{
					$optionsEnum = [];
					$obEnum = new \CUserFieldEnum();
					$cursor = $obEnum::GetList([], [
						'USER_FIELD_ID' => $curField['ID']
					]);
					while($option = $cursor->GetNext())
					{
						$optionsEnum[$option['XML_ID']] = (int)$option['ID'];
					}
					foreach($ufFields['ADDITIONAL'] as $option)
					{
						if(!array_key_exists($option['XML_ID'], $optionsEnum))
						{
							$obEnum->SetEnumValues($curField['ID'], [
								"n0" => $option
							]);
						}
					}
				}
				// endregion ////
			}
			else
			{
				// add ////
				$params = $ufFields;
				unset($params['ADDITIONAL']);
				$response = static::addUf($params);
				if(!$response->isSuccess())
				{
					return $result->addErrors($response->getErrors());
				}
				$curField['ID'] = (int)$response->getData()['ID'];
				// region enumeration /////
				if($ufFields['USER_TYPE_ID'] === 'enumeration')
				{
					$obEnum = new \CUserFieldEnum();
					foreach($ufFields['ADDITIONAL'] as $option)
					{
						$obEnum->SetEnumValues($curField['ID'], [
							"n0" => $option
						]);
					}
				}
				// endregion ////
			}
			$propertyIds[$ufFields['FIELD_NAME']] = $curField['ID'];
		}

		return $result->setData([
			'UF_IDS' => $propertyIds
		]);
	}
}