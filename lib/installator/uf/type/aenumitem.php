<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type;

use Bitrix\Main\Type\Dictionary;

abstract class AEnumItem
{
	private int $id;
	private string $xmlId = '';
	private string $title = '';
	private int $sort = 500;
	private bool $isDef = false;

	public function __construct()
	{
		$this->id = 0;
	}

	// region Get|Set ////
	final public function setId(int $value): self
	{
		$this->id = $value;
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
	public function getInstallSettings(): array
	{
		return [
			'VALUE' => $this->getTitle(),
			'DEF' => $this->isDef() ? 'Y' : 'N',
			'SORT' => $this->getSort(),
			'XML_ID' => $this->getXmlId()
		];
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