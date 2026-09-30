<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Tools;

/**
 * Трейт enum для выбора значения по названию
 *
 * @method static array cases()
 */
trait EnumFromName
{
	/**
	 * To mirror backed enums tryFrom - returns null on failed match.
	 *
	 * @param string $name
	 * @return static|null
	 */
	public static function tryFromName(string $name): null|static
	{
		foreach(self::cases() as $case)
		{
			if($case->name === $name)
			{
				return $case;
			}
		}
		
		return null;
	}
	
	/**
	 * To mirror backed enums from - throws ValueError on failed match.
	 *
	 * @param string $name
	 * @return static
	 */
	public static function fromName(string $name): static
	{
		$case = self::tryFromName($name);
		if(!$case)
		{
			throw new \ValueError(sprintf(
				'%s is not a valid case for enum %s',
				$name,
				self::class
			));
		}
		return $case;
	}
}
