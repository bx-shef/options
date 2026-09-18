<?php

namespace Shef\Options\Integration\IBlock\Fields;

use Shef\IBlock\Lists\Base\Fields\Types;

abstract class AFieldListLink
	extends AField
{
	public function getType()
	{
		return Types::LIST_LINK;
	}

	abstract protected function getLinkEntity(): \Shef\Options\Integration\IBlock\AEntity;
	
	public function getLinkEntityInstance(): \Shef\Options\Integration\IBlock\AEntity
	{
		return $this->getLinkEntity();
	}

	public function getLinkEntityId(): int
	{
		return $this->getLinkEntity()::getIblockId();
	}

	public function prepareValue($value)
	{
		$response = $this->getLinkEntity()->getList([
			'filter' => [
				'=ID' => (int)$value
			],
			'limit' => 1
		]);
		return reset($response);
	}
}