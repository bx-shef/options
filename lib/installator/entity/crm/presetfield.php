<?php
declare(strict_types=1);

namespace Shef\Options\Installator\Entity\Crm;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Type\Contract\Arrayable;
use Bitrix\Main\Type\Dictionary;

class PresetField
	implements \JsonSerializable, Arrayable
{
	protected int $id = 0;
	
	public function __construct(
		public readonly string $name,
		public readonly string $title,
		public readonly int $sort,
		public readonly bool $isInShortList = false,
	)
	{
	}
	// region Get|Set ////
	/**
	 * @param int $id
	 * @return $this
	 */
	public function setId(int $id): static
	{
		$this->id = $id;
		return $this;
	}
	
	/**
	 * @return int
	 */
	public function getId(): int
	{
		return $this->id;
	}
	// endregion /////
	
	/**
	 * JsonSerializable::jsonSerialize
	 * Specify data which should be serialized to JSON
	 * @return array
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array
	{
		return $this->toArray();
	}
	
	public function toArray(): array
	{
		return [
			'ID' => $this->getId(),
			'FIELD_NAME' => $this->name,
			'FIELD_TITLE' => $this->title,
			'IN_SHORT_LIST' => $this->isInShortList ? 'Y' : 'N',
			'SORT' => $this->sort,
		];
	}
}