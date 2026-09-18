<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF\Strategy;

use Shef\Options\Installator\Entity\UF;

class UFDouble
	extends AStrategy
	implements IStrategy
{
	/**
	 * @inheritDoc
	 */
	public function getType(): UF\EType
	{
		return UF\EType::Double;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getSettings(): array
	{
		return [
			'precision' => 4,
			'size' => 20,
			'regexp' => '',
			'minValue' => 0.0,
			'maxValue' => 0.0,
			'defaultValue' => ''
		];
	}
}