<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type\Strategy;

use Shef\Options\Installator\UF\Type;

class UFString
	extends AStrategy
	implements IStrategy
{
	public const Type = 'string';

	public static function getSettings(): array
	{
		return [
			'SIZE' => 20,
			'ROWS' => 1,
			'REGEXP' => '',
			'MIN_LENGTH' => 0,
			'MAX_LENGTH' => 0,
			'DEFAULT_VALUE' => ''
		];
	}
}