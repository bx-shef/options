<?php
namespace Shef\Options;

use Bitrix\Main\Result;
use Bitrix\Main\Error;

class Controller
{
	const TYPE_INT = 'INT';
	const TYPE_FLOAT = 'FLOAT';
	const TYPE_STRING = 'STRING';
	
	protected static function getTitle($module, $name)
	{
		return $module.$name;
	}
	
	protected static function getValue($type, $value)
	{
		$result = null;
		switch($type)
		{
			case static::TYPE_INT:
				$result = intval($value);
			break;
			case static::TYPE_FLOAT:
				$result = floatval($value);
			break;
			case static::TYPE_STRING:
				$result = trim($value);
			break;
			default:
				$result = ''.$value;
			break;
		}
		
		return $result;
	}
	
	public static function get($module, $name, $type, $defValue)
	{
		$optionTitle = static::getTitle($module, $name);
		$defValue = static::getValue($type, $defValue);
		
		$value = \Bitrix\Main\Config\Option::get('shef.options', $optionTitle, $defValue);
		
		return static::getValue($type, $value);
	}
	
	public static function set($module, $name, $type, $value)
	{
		$optionTitle = static::getTitle($module, $name);
		$value = static::getValue($type, $value);
		
		return \Bitrix\Main\Config\Option::set('shef.options', $optionTitle, $value);
		
	}
}