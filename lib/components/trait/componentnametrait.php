<?php declare(strict_types=1);

namespace Shef\Options\Components\Trait;

use Bitrix\Main\Application;

/**
 * Trait для обработки названий компонента
 */
trait ComponentNameTrait
{
	public static function getCalledClass(): string
	{
		$selfClass = static::class;
		$classParts = explode('\\', $selfClass);
		return end($classParts);
	}
	
	public static function getSelfBaseName(): string
	{
		return str_replace(
			[
				'Component',
				'AjaxController'
			],
			'',
			static::getCalledClass()
		);
	}
	
	public static function getSelfNamespace(): string
	{
		return str_replace('\\'.static::getSelfClass(), '', static::class);
	}
	
	public static function getSelfClass(): string
	{
		return static::getSelfBaseName().'Component';
	}
	
	public static function getSelfClassWithNamespace(): string
	{
		return join('\\', [
			static::getSelfNamespace(),
			static::getSelfClass()
		]);
	}
	
	public static function getSelfAjaxClass(): string
	{
		return static::getSelfBaseName().'AjaxController';
	}
	
	public static function getSelfAjaxClassWithNamespace(): string
	{
		return join('\\', [
			static::getSelfNamespace(),
			static::getSelfAjaxClass()
		]);
	}
}