<?php
declare(strict_types=1);

namespace Shef\Options\Main\Options;

use Bitrix\Main\UserTable;
use Bitrix\Main\EO_User;
use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Constants;

Loc::loadMessages(__FILE__);

/**
 * Вывод пользователей
 */
class Users
	extends Enum
{
	// region prepareList ////
	protected static function getUserList(
		array $filter,
		?array $select = null,
		?array $order = null
	): array
	{
		$conf = [
			'filter' => $filter,
			'select' => array_merge(
				['ID', 'ACTIVE', 'SHORT_NAME', 'WORK_POSITION'],
				$select ?? []
			),
			'order' => $order ?? ['ID' => 'ASC']
		];
		
		$collection = UserTable::getList($conf)->fetchCollection();
		
		if($collection->count() < 1)
		{
			return [];
		}
		
		return array_map(
			function(EO_User $user)
			{
				$langKey = Constants::MODULE_ID.'_Options_Users_Active';
				if(!$user->getActive())
				{
					$langKey = Constants::MODULE_ID.'_Options_Users_NotActive';
				}
				
				return [
					'ID' => $user->getId(),
					'TITLE' => Loc::getMessage($langKey, [
						'#ID#' => $user->getId(),
						'#TITLE#' => $user->getShortName(),
						'#WORK_POSITION#' => $user->getWorkPosition()
					])
				];
			},
			$collection->getAll()
		);
	}

	public function initSimpleUserList(
		array $filter,
		?array $select = null,
		?array $order = null
	): static
	{
		$list = static::getUserList($filter, $select, $order);

		$list = array_combine(
			array_column($list, 'ID'),
			array_column($list, 'TITLE')
		);

		$this->setList($list);

		return $this;
	}
	// endregion ////
}