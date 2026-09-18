<?php
namespace Shef\Options;

use Bitrix\Sale\EntityPropertyValue;

abstract class ABasketItemProperty
{
	const TITLE = 'NOTSET';
	const PROPERTY_CODE = 'NOTSET';
	const SORT = 0;

	public static function getTitle(): string
	{
		return static::TITLE;
	}

	public static function getCode(): string
	{
		return static::PROPERTY_CODE;
	}
	public static function getSort(): string
	{
		return static::SORT;
	}

	public static function getConfig(): array
	{
		return [
			'NAME' => static::getTitle()
			,'CODE' => static::getCode()
			,'SORT' => static::getSort()
		];
	}
}