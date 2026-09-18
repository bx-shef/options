<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Tools;

/**
 * Трейт для работы названиями классов
 */
trait SelfClass
{
	/**
	 * Для корректного определения имени класса
	 *
	 * Returns the fully qualified name of this class.
	 *
	 * @return string
	 */
	final public static function getClassName(): string
	{
		return '\\'.get_called_class();
	}
}