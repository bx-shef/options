<?php
declare(strict_types=1);

namespace Shef\Options\Main;

interface ISecurity
{
	public static function getCurrentUserId(): int;

	public static function getSystemUserId(): int;

	public static function isAuthorized(): bool;

	public static function isAdmin(): bool;

	public static function isInGroupOrAdmin(string $groupCode): bool;

	public static function isInGroup(string $groupCode): bool;
}