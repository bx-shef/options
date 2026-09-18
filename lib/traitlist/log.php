<?php
namespace Shef\Options\TraitList;

/**
 * Trait Log
 * @package Shef\Options\TraitList
 *
 * Используется в Агентах, Событиях для логирования
 *
 */
trait Log
{
	protected static function isSkipLog(): bool
	{
		return true;
	}

	protected static function getLogFile(): string
	{
		return 'shef-options-trait-list-Events';
	}

	protected static function log(
		array $value = [],
		bool $isNotSkip = false
	): void
	{
		// skip log ////
		if(!$isNotSkip && static::isSkipLog())
		{
			return;
		}

		_log($value, static::getLogFile());
	}
}