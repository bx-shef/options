<?php
namespace Shef\Options\Integration\IBlock\Fields;

use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Shef\IBlock\Lists\Base\Fields\Types;

abstract class AField
{
	const CODE = 'NOT_SET';
	const SORT = 100;

	protected $iblockId = 0;
	protected $propertyId = 0;
	protected $property = [];
	protected $settings = [];
	protected $config = [];

	protected static $enums = [];


	protected static $instances = [];
	public static function getInstance(array $config = [])
	{
		if(!isset(static::$instances[get_called_class()]))
		{
			static::$instances[get_called_class()] = new static($config);
		}

		return static::$instances[get_called_class()];
	}

	protected function includeModule()
	{
		try
		{
			return Loader::includeModule('iblock');
		}
		catch(LoaderException $exception)
		{
			return false;
		}
	}

	protected function __construct(array $config = [])
	{
		$this->includeModule();
		$this->iblockId = (int)$config['IBLOCK_ID'];
		$this->config = $config;
		$this->initSettings();
		$this->initId();
	}

	///////////////////////////////////////////////

	public function getSqlTable()
	{
		// sh_z_participation_property ////
		return $this->config['IBLOCK_SQL_PROPS_TABLE_ALIAS'];
	}
	public function getPropertyCode()
	{
		return 'PROPERTY_'.static::getCode();
	}
	public function getPropertyCodeId()
	{
		return 'PROPERTY_'.$this->getPropertyId();
	}
	public function getCode()
	{
		return static::CODE;
	}

	public function getType()
	{
		return Types::STRING;
	}

	public function getTitle()
	{
		return 'NotSet';
	}

	public function getPropertyId()
	{
		return $this->propertyId;
	}

	public function getProperty()
	{
		return $this->property;
	}

	public function initSettings()
	{
		$this->settings = [];
	}
	public function getSettings()
	{
		return $this->settings;
	}

	public function initId()
	{
		$this->propertyId = 0;
		$cursor = \CIBlockProperty::GetList(
			['id' => 'asc']
			,[
				'CODE' => $this->getCode()
				,'IBLOCK_ID' => $this->iblockId
			]
		);

		while($property = $cursor->Fetch())
		{
			$this->propertyId = (int)$property['ID'];
			$this->property = $property;
			break;
		}
	}

	public function getDefValue()
	{
		return null;
	}

	public function getByOrder(\Bitrix\Crm\Order\Order &$order)
	{
		return null;
	}

	public function getByPayment(\Bitrix\Crm\Order\Payment &$payment)
	{
		return null;
	}
	public function getByPaymentReturn(\Bitrix\Crm\Order\Payment &$payment)
	{
		return null;
	}
	public function getByShipment(\Bitrix\Crm\Order\Shipment &$shipment)
	{
		return null;
	}

	public function getByCheck(\Bitrix\Sale\Cashbox\Check &$check)
	{
		return null;
	}

	public function parseValue($value)
	{
		return $value;
	}

	public function encode($value)
	{
		return \Bitrix\Main\Web\Json::encode($value);
	}

	public function decode($value, $isHtmlDecode = false)
	{
		if($isHtmlDecode === true)
		{
			$value = htmlspecialcharsback($value);
		}
		try
		{
			$result = \Bitrix\Main\Web\Json::decode($value);
		}
		catch (\Bitrix\Main\ArgumentException $e)
		{
			if($isHtmlDecode === false)
			{
				return $this->decode($value, true);
			}
			$result = [];
		}

		return $result;
	}
}