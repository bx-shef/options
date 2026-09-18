<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Constants;

/**
 * Используется для получения данных о сотрудниках
 */
trait User
{
	public static function getSystemUserId(): int
	{
		return \Shef\Options\Main\Security::getSystemUserId();
	}
}