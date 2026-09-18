<?php
namespace Shef\Options\Installator\Order;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Sale\Internals\ServiceRestrictionTable;
use Bitrix\Sale\Delivery\Restrictions\ByPublicMode;
use Bitrix\Sale\Delivery\Services;

class Shipment
	extends AInstaller
{
	protected static function installRestrictionNoPublic(int $serviceId): Result
	{
		$result = new Result();

		$response = static::includeModules();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		return ServiceRestrictionTable::add([
			'SORT' => 100,
			'SERVICE_ID' => $serviceId,
			'PARAMS' => ['PUBLIC_SHOW' => 'N'],
			'SERVICE_TYPE' => '0',
			'CLASS_NAME' => '\\' . ByPublicMode::class
		]);
	}
	
	public static function installShipment(array $options): Result
	{
		$result = new Result();

		$response = static::includeModules();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		$serviceIds = [];
		$shipmentList = $options['list'] ?? [];
		foreach($shipmentList as $item)
		{
			// region get by XML_ID ////
			$deliveryServiceFields = Services\Manager::getList([
				'filter' => [
					'=XML_ID' => $item['XML_ID']
				]
				,'select' => ['ID']
			])->fetchRaw();
			if((int)$deliveryServiceFields['ID'] > 0)
			{
				$serviceIds[] = (int)$deliveryServiceFields['ID'];
				continue;
			}
			$fields = $item;
			unset($fields['ADDITIONAL']);
			$response = Services\Manager::add($item);
			if(!$response->isSuccess())
			{
				return $result->addErrors($response->getErrors());
			}
			$serviceId = $response->getId();
			$serviceIds[] = $serviceId;
			if($item['IS_RESTRICTION_NO_PUBLIC'])
			{
				static::installRestrictionNoPublic($serviceId);
			}
			// endregion ////
		}
		
		return $result->setData([
			'SERVICE_IDS' => $serviceIds
		]);
		
		return $result;
	}
}