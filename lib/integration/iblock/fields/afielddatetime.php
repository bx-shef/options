<?php
namespace Shef\Options\Integration\IBlock\Fields;

use Shef\IBlock\Lists\Base\Fields\Types;

abstract class AFieldDateTime
	extends AField
{
	public function getType()
	{
		return Types::DATE_TIME;
	}

	public function prepareValue($value)
	{
		if(
			!is_object($value)
			&& strlen($value) > 0
		)
		{
			return new \Bitrix\Main\Type\DateTime($value);
		}

		return $value;
	}
}