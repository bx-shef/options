<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type\Strategy;

use Shef\Options\Installator\UF\Type;

class UFDouble
	extends AStrategy
	implements IStrategy
{
	public const Type = 'double';

	public static function getSettings(): array
	{
		return [
			'PRECISION' => 4,
			'SIZE' => 20,
			'REGEXP' => '',
			'MIN_VALUE' => 0.0,
			'MAX_VALUE' => 0.0,
			'DEFAULT_VALUE' => ''
		];
	}


}