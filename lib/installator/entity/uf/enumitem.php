<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF;

use Bitrix\Main\Type\Contract\Arrayable;
use Shef\Options\Installator;

/**
 * Реализацияч элемента перечисления UF типа перечисление
 */
class EnumItem
	implements Installator\IEntity, Arrayable
{
	private int $id = 0;
	private string $xmlId = '';
	private string $title = '';
	private int $sort = 500;
	private bool $isDef = false;

	public function __construct()
	{
		$this->id = 0;
	}

	// region Get|Set ////
	final public function setId(int $id): self
	{
		$this->id = $id;
		return $this;
	}

	final public function getId(): int
	{
		return $this->id;
	}

	final public function setXmlId(string $value): self
	{
		$this->xmlId = $value;
		return $this;
	}

	final public function getXmlId(): string
	{
		return $this->xmlId;
	}

	final public function setTitle(string $value): self
	{
		$this->title = $value;
		return $this;
	}

	final public function getTitle(): string
	{
		return $this->title;
	}

	public function setSort(int $value): self
	{
		$this->sort = $value;
		return $this;
	}

	final public function getSort(): int
	{
		return $this->sort;
	}

	final public function setIsDef(bool $value): self
	{
		$this->isDef = $value;
		return $this;
	}

	final public function isDef(): bool
	{
		return $this->isDef;
	}
	// endregion ////

	// region for Install /////
	/**
	 * @inheritDoc
	 */
	public function getInstallSettings(): array
	{
		return array_merge(
			(
				$this->getId() > 0
				? ['id' => $this->getId()]
				: []
			),
			[
				'xmlId' => $this->getXmlId(),
				'def' => $this->isDef() ? 'Y' : 'N',
				'value' => $this->getTitle(),
				'sort' => $this->getSort()
			]
		);
	}
	// endregion ////

	// region for Tools /////
	public function toArray(): array
	{
		return [
			'ID' => $this->getId(),
			'VALUE' => $this->getTitle(),
			'DEF' => $this->isDef(),
			'SORT' => $this->getSort(),
			'XML_ID' => $this->getXmlId()
		];
	}
	// endregion ////
}