<?php
declare(strict_types=1);

namespace Shef\Options\Main;

use Shef\Options\Main\Constants;

/**
 * Работа с текущим пользователем
 *
 * @see \CCrmSecurityHelper
 *
 */
class Security
	implements ISecurity
{
	protected static ?\Bitrix\Main\Engine\CurrentUser $cuser = null;

	// region \Shef\Options\ISecurity ////
	public static function getCurrentUserId(): int
	{
		return (int)(static::getCurrentUser()->getId());
	}

	public static function getSystemUserId(): int
	{
		return Constants::getSystemUserId();
	}

	public static function isAuthorized(): bool
	{
		return static::getCurrentUserId() > 0;
	}

	public static function isAdmin(): bool
	{
		return static::getCurrentUser()->isAdmin();
	}

	public static function isInGroupOrAdmin(string $groupCode): bool
	{
		if(static::isAdmin())
		{
			return true;
		}

		return static::isInGroup($groupCode);
	}

	public static function isInGroup(string $groupCode): bool
	{
		$group = \Bitrix\Main\GroupTable::getRow([
			'filter' => ['=STRING_ID' => $groupCode],
			'select' => ['ID']
		]);

		if(!array($group))
		{
			$group = [];
		}

		$groupId = (int)$group['ID'];
		$userGroups = \Bitrix\Main\Engine\CurrentUser::get()->getUserGroups();
		if (
			$groupId > 0
			&& count(array_intersect($userGroups, [$groupId])) > 0
		)
		{
			return true;
		}
		return false;
	}
	// endregion ////

	// region Tools ////
	public static function getCurrentUser(): \Bitrix\Main\Engine\CurrentUser
	{
		if(null === static::$cuser)
		{
			static::$cuser = \Bitrix\Main\Engine\CurrentUser::get();
		}

		return static::$cuser;
	}
	// endregion ////
}