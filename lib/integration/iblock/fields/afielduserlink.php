<?php

namespace Shef\Options\Integration\IBlock\Fields;

use Bitrix\Main\UserTable;
use Shef\IBlock\Lists\Base\Fields\Types;

abstract class AFieldUserLink
	extends AField
{
	public function getType()
	{
		return Types::USER_LINK;
	}

	public function prepareValue($entityId, array $options = [])
	{
		$conf = [
			'select' => [
				'ID',
				'NAME',
				'SECOND_NAME',
				'LAST_NAME',
				'WORK_POSITION',
				'PERSONAL_PHONE',
				'PERSONAL_MOBILE',
				'WORK_PHONE',
				'UF_PHONE_INNER',
				'EMAIL'
			]
		];
		$response = UserTable::getByPrimary((int)$entityId, $conf)->fetch();
		if($response)
		{
			$result = &$response;
		}
		unset($response);
		$result['ID'] = (int)$result['ID'];
		$result['WORK_POSITION'] = trim($result['WORK_POSITION']);
		/*/
		_pr([
			$entityId
			,$result
		]);
		//*/
		return $result;
	}
}