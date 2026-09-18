<?php

namespace Shef\Options\Installator\Order;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Sale\Registry;
use Bitrix\Sale\Internals\OrderPropsRelationTable;
use Bitrix\Sale\Internals\OrderPropsGroupTable;
use Bitrix\Sale\PersonTypeTable;
use Bitrix\Sale\Delivery\Services\OrderPropsDictionary;
use Bitrix\Sale\Internals\OrderPropsTable;
use Bitrix\Sale\Internals\OrderPropsVariantTable;

class Property
	extends AInstaller
{
	public static function installOrderProperties(array $options): Result
	{
		$result = new Result();

		$response = static::includeModules();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		if(!$options['property_group_name'])
		{
			$result->addError(new Error('empty property group name'));
			return $result;
		}

		// region Install property groups ////
		$payerTypeGroupsMapping = [];
		$payerTypes = PersonTypeTable::getList([
			'filter' => ['ENTITY_REGISTRY_TYPE' => Registry::REGISTRY_TYPE_ORDER]
		])->fetchAll();

		foreach($payerTypes as $payerType)
		{
			$existingGroup = OrderPropsGroupTable::getList([
				'filter' => [
					'PERSON_TYPE_ID' => $payerType['ID'],
					'NAME' => $options['property_group_name'],
					'CODE' => static::PROPERTY_GROUP_CODE,
				]
			])->fetch();

			if($existingGroup)
			{
				$payerTypeGroupsMapping[$payerType['ID']] = $existingGroup['ID'];
				continue;
			}

			$persistResult = OrderPropsGroupTable::add([
				'PERSON_TYPE_ID' => $payerType['ID'],
				'NAME' => $options['property_group_name'],
				'CODE' => static::PROPERTY_GROUP_CODE,
			]);
			if(!$persistResult->isSuccess())
			{
				return $result->addError(new Error('Can not add property group'));
			}

			$payerTypeGroupsMapping[$payerType['ID']] = $persistResult->getId();
		}
		// endregion ////
		// region Install properties ////
		$propertyIds = [];
		foreach($payerTypes as $payerType)
		{
			$properties = $options['properties'];

			foreach($properties as $property)
			{
				$payerTypeId = $payerType['ID'];
				// region TEST if need add property ty personType ////
				if(
					is_array($property['ADDITIONAL'])
					&& (
						array_key_exists('IS_FOR_CONTACT', $property['ADDITIONAL'])
						|| array_key_exists('IS_FOR_COMPANY', $property['ADDITIONAL'])
					)
				)
				{
					if(
						$payerTypeId === \Bitrix\Crm\Order\PersonType::getContactPersonTypeId()
						&& (bool)$property['ADDITIONAL']['IS_FOR_CONTACT'] === false
					)
					{
						continue;
					}
					if(
						$payerTypeId === \Bitrix\Crm\Order\PersonType::getCompanyPersonTypeId()
						&& (bool)$property['ADDITIONAL']['IS_FOR_COMPANY'] === false
					)
					{
						continue;
					}
				}
				// endregion ////
				if(!isset($payerTypeGroupsMapping[$payerTypeId]))
				{
					return $result->addError(new Error('Property group not found'));
				}

				$propertyGroupId = $payerTypeGroupsMapping[$payerTypeId];

				$existingProperty = OrderPropsTable::getList([
					'filter' => [
						'PERSON_TYPE_ID' => $payerTypeId,
						'PROPS_GROUP_ID' => $propertyGroupId,
						'CODE' => $property['CODE'],
						'TYPE' => $property['TYPE'],
						'ENTITY_TYPE' => $property['ENTITY_TYPE']
					]
				])->fetch();

				$propertyFields = [
					'NAME' => $property['NAME'],
					'ACTIVE' => 'Y',
					'USER_PROPS' => 'N',
					'SORT' => $property['SORT'] ?? 1000,
					'IS_FILTERED' => $property['IS_FILTERED'] ?? 'N',
					'REQUIRED' => $property['REQUIRED'],
					'MULTIPLE' => $property['MULTIPLE'],
					'SETTINGS' => $property['SETTINGS'],
					'ENTITY_REGISTRY_TYPE' => Registry::REGISTRY_TYPE_ORDER,
					'DEFAULT_VALUE' => $property['DEFAULT_VALUE'] ?? '',
					'DESCRIPTION' => $property['DESCRIPTION'] ?? '',
					'XML_ID' => $property['XML_ID'] ?? ''
				];

				if($existingProperty)
				{
					$persistResult = OrderPropsTable::update($existingProperty['ID'], $propertyFields);
					if(!$persistResult->isSuccess())
					{
						return $result->addError(new Error('Property can not be installed'));
					}

					$propertyIds[] = $existingProperty['ID'];
				}
				else
				{
					$persistResult = OrderPropsTable::add(array_merge($propertyFields, [
						'PERSON_TYPE_ID' => $payerTypeId,
						'PROPS_GROUP_ID' => $propertyGroupId,
						'CODE' => $property['CODE'],
						'TYPE' => $property['TYPE'],
						'ENTITY_TYPE' => $property['ENTITY_TYPE'],
					]));
					if(!$persistResult->isSuccess())
					{
						return $result->addError(new Error('Property can not be installed'));
					}
					$newPropertyId = $persistResult->getId();
					$propertyIds[] = $newPropertyId;

					// region ENUM Options ////
					// @tosee: we add options only for new property ////
					if($property['TYPE'] === 'ENUM')
					{
						if(
							is_array($property['ADDITIONAL'])
							&& array_key_exists('OPTIONS_LIST', $property['ADDITIONAL'])
							&& is_array($property['ADDITIONAL']['OPTIONS_LIST'])
						)
						{
							foreach($property['ADDITIONAL']['OPTIONS_LIST'] as $enumOption)
							{
								$persistResult = OrderPropsVariantTable::add(array_merge([
									'ORDER_PROPS_ID' => $newPropertyId
									,'NAME' => $enumOption['TITLE']
									,'VALUE' => $enumOption['CODE']
									,'SORT' => $enumOption['SORT']
									,'DESCRIPTION' => $enumOption['DESCR']
									,'XML_ID' => $enumOption['XML_ID']
								], $enumOption));
								if(!$persistResult->isSuccess())
								{
									return $result->addError(new Error('Property option can not be installed'));
								}
							}
						}
					}
					// endregion ////
				}
			}
		}
		// endregion ////
		return $result->setData([
			'PROPERTY_IDS' => $propertyIds
		]);
	}

	// $options['ENTITY_TYPE'] = {P? || D} ////
	public static function attachOrderProperties(int $serviceId, array $propertyIds, array $options): Result
	{
		$result = new Result();

		$response = static::includeModules();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		foreach($propertyIds as $propertyId)
		{
			$fields = [
				'PROPERTY_ID' => $propertyId,
				'ENTITY_TYPE' => $options['ENTITY_TYPE'] ?? 'D',
				'ENTITY_ID' => $serviceId,
			];

			$existingRecord = OrderPropsRelationTable::getList([
				'filter' => [
					'PROPERTY_ID' => $propertyId,
					'ENTITY_TYPE' => $options['ENTITY_TYPE'] ?? 'D',
					'ENTITY_ID' => $serviceId,
				]
			])->fetch();

			if(!$existingRecord)
			{
				$relationAddResult = OrderPropsRelationTable::add($fields);
				if(!$relationAddResult->isSuccess())
				{
					$result->addErrors($relationAddResult->getErrors());
				}
			}
		}

		return $result;
	}
}