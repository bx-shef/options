<?php
namespace Shef\Options;

interface ISecurity
{
	public static function getCurrentUserId(): int;

	public static function getSystemUserId(): int;

	public static function isAuthorized(): bool;

	public static function isAdmin(): bool;

	public static function isInGroupOrAdmin(string $groupCode): bool;

	public static function isInGroup(string $groupCode): bool;
}