<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF\Strategy;

use Shef\Options\Installator\Entity\UF;

class UFString
	extends AStrategy
	implements IStrategy
{
	/**
	 * @inheritDoc
	 */
	public function getType(): UF\EType
	{
		return UF\EType::String;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getSettings(): array
	{
		return [
			'size' => 20,
			'rows' => 1,
			'regexp' => '',
			'minLength' => 0,
			'maxLength' => 0,
			'defaultValue' => ''
		];
	}
}