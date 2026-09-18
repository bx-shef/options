<?php

namespace Shef\Options\Main\Options;

use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Constants;

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

		$list = \Bitrix\Main\UserTable::getList($conf)->fetchAll();

		/*/
		_pr([
			$conf,
			$list
		]);
		//*/

		if(!is_array($list))
		{
			return [];
		}

		array_walk(
			$list,
			function(&$row)
			{
				$row['ID'] = (int)$row['ID'];
				$langKey = Constants::MODULE_ID.'_Options_Users_Active';
				if($row['ACTIVE'] !== 'Y')
				{
					$langKey = Constants::MODULE_ID.'_Options_Users_NotActive';
				}

				$row['TITLE'] = Loc::getMessage($langKey, [
					'#ID#' => $row['ID'],
					'#TITLE#' => (string)$row['SHORT_NAME'],
					'#WORK_POSITION#' => (string)$row['WORK_POSITION']
				]);
			}
		);

		return $list;
	}

	public function initSimpleUserList(
		array $filter,
		?array $select = null,
		?array $order = null
	): self
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