<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF\Strategy;

use Shef\Options\Installator\Entity\UF;

class UFDateTime
	extends AStrategy
	implements IStrategy
{
	/**
	 * @inheritDoc
	 */
	public function getType(): UF\EType
	{
		return UF\EType::DateTime;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getSettings(): array
	{
		return [];
	}
}