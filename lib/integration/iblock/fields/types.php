<?php
namespace Shef\Options\Integration\IBlock\Fields;

class Types
{
	const STRING = 'string';
	const TEXT = 'text';
	const JSON = 'json';
	const INT = 'int';
	const FLOAT = 'float';
	const MONEY = 'money';
	const ENUM = 'enum';
	const DATE = 'date';
	const DATE_TIME = 'datetime';
	const LIST_LINK = 'list_link';
	const CRM_LINK = 'crm_link';
	const USER_LINK = 'user_link';

	public static function getOrderFieldCode()
	{
		return 'ORDER_ID';
	}

	public static function getOrderPaymentFieldCode()
	{
		return 'ORDER_PAYMENT_ID';
	}

	public static function getOrderCheckFieldCode()
	{
		return 'ORDER_CHECK_ID';
	}

	public static function getOrderShipmentFieldCode()
	{
		return 'ORDER_SHIPMENT_ID';
	}
}