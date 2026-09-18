<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type\Strategy;

use Shef\Options\Installator\UF\Type;

class UFText
	extends UFString
	implements IStrategy
{
	public static function getSettings(): array
	{
		return [
			'SIZE' => 76,
			'ROWS' => 6,
			'REGEXP' => '',
			'MIN_LENGTH' => 0,
			'MAX_LENGTH' => 0,
			'DEFAULT_VALUE' => ''
		];
	}
}