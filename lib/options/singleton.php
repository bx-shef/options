<?php declare(strict_types=1);

namespace Shef\Options\Options;

class Singleton
{
	private static array $instances = [];

	protected function __construct(){}

	protected function __clone(){}
	
	/**
	 * @throws \Exception
	 */
	public function __wakeup()
	{
		throw new \Exception("Cannot unserialize config");
	}

	public static function getInstance(): static
	{
		$subclass = static::class;
		if(!isset(self::$instances[$subclass]))
		{
			self::$instances[$subclass] = new static();
		}
		
		return self::$instances[$subclass];
	}
}