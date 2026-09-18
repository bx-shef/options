<?php
namespace Shef\Options;

use Bitrix\Sale\EntityPropertyValue;
use Bitrix\Sale\Registry;

abstract class AProperty
{
	const TITLE = 'NOTSET';
	const PROPERTY_CODE = 'NOTSET';
	const TYPE = 'STRING';
	const ENTITY_TYPE = 'ORDER';
	const DESCR = '';

	protected $settings = [];

	protected static $instances = [];
	public static function getInstance(array $config = [])
	{
		if(!isset(static::$instances[get_called_class()]))
		{
			static::$instances[get_called_class()] = new static($config);
		}

		return static::$instances[get_called_class()];
	}

	protected function __construct(array $config = [])
	{
		$this->config = $config;
		$this->initSettings();
	}

	public static function title(): string
	{
		return static::TITLE;
	}

	public static function getCode(): string
	{
		return static::PROPERTY_CODE;
	}
	public static function getDescr(): string
	{
		return static::DESCR;
	}
	public static function getType(): string
	{
		return static::TYPE;
	}
	public static function getEntityType(): string
	{
		return static::ENTITY_TYPE;
	}
	public static function getSort(): int
	{
		return 100;
	}
	public static function isUseAtContactPersonType(): bool
	{
		return false;
	}
	public static function isUseAtCompanyPersonType(): bool
	{
		return false;
	}
	public static function isFiltered(): bool
	{
		return false;
	}
	public static function isRequired(): bool
	{
		return false;
	}
	public static function isMultiple(): bool
	{
		return false;
	}
	public static function getInstallSettings(): array
	{
		return [];
	}
	public static function getOptionsList(): array
	{
		return [];
	}
	
	public static function getInstallFields(): array 
	{
		return [
			'CODE' => static::getCode(),
			'XML_ID' => static::getCode(),
			'SORT' => static::getSort(),
			'NAME' => static::title(),
			'TYPE' => static::getType(),
			'ENTITY_TYPE' => static::getEntityType(),
			'IS_FILTERED' => static::isFiltered() ? 'Y' : 'N',
			'REQUIRED' => static::isRequired() ? 'Y' : 'N',
			'MULTIPLE' => static::isMultiple() ? 'Y' : 'N',
			'SETTINGS' => static::getInstallSettings()
			,'ADDITIONAL' => [
				'IS_FOR_CONTACT' => static::isUseAtContactPersonType()
				,'IS_FOR_COMPANY' => static::isUseAtCompanyPersonType()
				,'OPTIONS_LIST' => static::getOptionsList()
			]
		];
	}

	public function getTitle(): string
	{
		return static::title();
	}
	public static function getPropertyObjForOrder(): ?\Bitrix\Crm\Order\Property
	{
		$property = \Bitrix\Crm\Order\ShipmentProperty::getList([
			'filter' => [
				'=CODE' => static::getCode()
			]
		])->fetchRaw();
		if(empty($property))
		{
			return null;
		}
		$registry = Registry::getInstance(Registry::REGISTRY_TYPE_ORDER);
		$propertyClassName = $registry->getPropertyClassName();
		return new $propertyClassName($property, []);
	}

	public static function getPropertyObjForShipment(): ?\Bitrix\Crm\Order\ShipmentProperty
	{
		$property = \Bitrix\Crm\Order\ShipmentProperty::getList([
			'filter' => [
				'=CODE' => static::getCode()
			]
		])->fetchRaw();
		if(empty($property))
		{
			return null;
		}
		$registry = Registry::getInstance(Registry::REGISTRY_TYPE_ORDER);
		$propertyClassName = $registry->getShipmentPropertyClassName();
		return new $propertyClassName($property, []);
	}

	public function initSettings(): void
	{
		$this->settings = [];
	}
	public function getSettings(): array
	{
		return $this->settings;
	}

	public function getDefValue()
	{
		return null;
	}

	public function getByOrder(\Bitrix\Crm\Order\Order &$order): ?EntityPropertyValue
	{
		$propertyCollection = $order->getPropertyCollection();
		return $propertyCollection->getItemByOrderPropertyCode(static::getCode());
	}
	public function getByShipment(\Bitrix\Crm\Order\Shipment &$shipment): ?EntityPropertyValue
	{
		$propertyCollection = $shipment->getPropertyCollection();
		return $propertyCollection->getItemByOrderPropertyCode(static::getCode());
	}

	public function parseValue(EntityPropertyValue $propertyItem)
	{
		$propertyList = array_merge($propertyItem->getFieldValues(), $propertyItem->getProperty());
		$value = \Bitrix\Sale\Internals\Input\Manager::getValue($propertyList, $propertyList['VALUE']);
		return $value;
	}

	public function getParseValueByOrder(\Bitrix\Crm\Order\Order &$order)
	{
		if(static::getEntityType() === 'SHIPMENT')
		{
			$shipment = static::getFirstShipment($order);
			if(!$shipment)
			{
				return null;
			}
			return $this->getParseValueByShipment($shipment);
		}
		elseif(static::getEntityType() === 'ORDER')
		{
			$propertyOrderValue = $this->getByOrder($order);
			if($propertyOrderValue)
			{
				return $this->parseValue($propertyOrderValue);
			}
		}
		return null;
	}

	public function getParseValueByShipment(\Bitrix\Crm\Order\Shipment &$shipment)
	{
		$propertyShipmentValue = $this->getByShipment($shipment);
		if($propertyShipmentValue)
		{
			return $this->parseValue($propertyShipmentValue);
		}
		return null;
	}

	public static function getFirstShipment(\Bitrix\Crm\Order\Order &$order): ?\Bitrix\Crm\Order\Shipment
	{
		$result = null;

		foreach ($order->getShipmentCollection()->getNotSystemItems() as $shipment)
		{
			$result = $shipment;
			break;
		}

		return $result;
	}

	public static function getView($value): string
	{
		return '';
	}
}