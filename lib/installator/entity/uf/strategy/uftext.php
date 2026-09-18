<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF\Strategy;

class UFText
	extends UFString
	implements IStrategy
{
	public static function getSettings(): array
	{
		return [
			'size' => 76,
			'rows' => 6,
			'regexp' => '',
			'minLength' => 0,
			'maxLength' => 0,
			'defaultValue' => ''
		];
	}
}