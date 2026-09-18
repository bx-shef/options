<?php
namespace Shef\Options\Integration\IBlock\Fields;

use Shef\IBlock\Lists\Base\Fields\Types;

abstract class AFieldEnum
	extends AField
{
	public function getType()
	{
		return Types::ENUM;
	}

	public function getEnumValues()
	{
		if(isset(static::$enums[$this->iblockId][$this->getCode()]))
		{
			return static::$enums[$this->iblockId][$this->getCode()];
		}

		static::$enums[$this->iblockId][$this->getCode()] = \Shef\IBlock\Main\Utils::getPropEnumValues(
			$this->iblockId
			, $this->getCode()
		);

		return static::$enums[$this->iblockId][$this->getCode()];
	}
}