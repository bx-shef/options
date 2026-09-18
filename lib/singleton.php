<?php

namespace Shef\Options;

/**
 * @deprecated
 * @use \Shef\Options\Options\Singleton
 */
class Singleton
{
	private static array $instances = [];

	protected function __construct(){}

	protected function __clone(){}

	public function __wakeup()
	{
		throw new \Exception("Cannot unserialize config");
	}

	public static function getInstance(): self
	{
		$subclass = static::class;
		if (!isset(self::$instances[$subclass])) {
			self::$instances[$subclass] = new static();
		}
		
		return self::$instances[$subclass];
	}
}