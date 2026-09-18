<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Security;

use Bitrix\Main;
use Bitrix\Main\EventResult;
use Bitrix\Main\Result;
use Bitrix\Main\Error;

/**
 * Используется в агентах и тп для инициализации юзера
 */
trait FixUser
{
	protected static bool $isTmpUserCreated = false;
	protected static null|int $tmpUserId = null;
	
	/**
	 * Определяет какого юзера нужно подключить
	 *
	 * @return int
	 */
	abstract protected static function getInitedUserId(): int;
	
	/**
	 * Инициализирует юзера
	 *
	 * @return void
	 */
	protected static function initUser(): void
	{
		\Bitrix\Main\Application::getInstance()->getSession()->start();
		
		static::$isTmpUserCreated = true;
		global $USER;
		
		if(isset($USER) && is_object($USER))
		{
			static::$tmpUserId = (int)$USER->GetID();
		}
		
		$newUserId = static::getInitedUserId();
		
		if($newUserId === static::$tmpUserId)
		{
			static::$isTmpUserCreated = false;
			return;
		}
		
		$USER = new \CUser();
		$USER->Authorize(
			static::getInitedUserId(),
			false,
			false
		);
		
	}
	
	/**
	 * Сбрасывает инициализацию юзера
	 *
	 * @return void
	 */
	public static function closeUser(): void
	{
		global $USER;
		
		if(static::$isTmpUserCreated)
		{
			$USER->Logout();
			if(
				isset(static::$tmpUserId)
				&& (int)static::$tmpUserId > 0
			)
			{
				$USER = new \CUser();
				$USER->Authorize(
					static::$tmpUserId,
					false,
					false
				);
				
				static::$tmpUserId = null;
				static::$isTmpUserCreated = false;
			}
		}
	}
}