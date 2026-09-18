<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type\Strategy;

use Shef\Options\Installator\UF\Type;

class UFEnum
	extends AStrategy
	implements IStrategy
{
	public const Type = 'enumeration';

	public static function getSettings(): array
	{
		return [
			'DISPLAY' => 'UI',
			'LIST_HEIGHT' => 1,
			'CAPTION_NO_VALUE' => '',
			'DEFAULT_VALUE' => '',
			'SHOW_NO_VALUE' => 'Y'
		];
	}
}