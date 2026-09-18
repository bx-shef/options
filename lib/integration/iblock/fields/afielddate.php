<?php
namespace Shef\Options\Integration\IBlock\Fields;

use Shef\IBlock\Lists\Base\Fields\Types;

abstract class AFieldDate
	extends AField
{
	public function getType()
	{
		return Types::DATE;
	}

	public function prepareValue($value)
	{
		if(
			!is_object($value)
			&& strlen($value) > 0
		)
		{
			return new \Bitrix\Main\Type\Date($value);
		}

		return $value;
	}
}