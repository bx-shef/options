<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\Result;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\Contract\Arrayable;
use Shef\Options\Installator;

/**
 * Абстрацкия UF
 *
 * @link https://dev.1c-bitrix.ru/api_d7/bitrix/main/userfield/index.php
 */
abstract class AEntity
	extends Installator\Entity\AEntity
{
	/** @memo see EEntityId  */
	protected string $entityId = '';
	
	/** @example UF_CRM_SHUNP_SYNC_RESULT */
	protected string $code = '';

	/** @example FieldName */
	protected string $title = '';
	protected int $sort = 500;
	protected bool $isMultiple = false;
	protected bool $isRequired = false;
	protected bool $isShowFilter = true;
	protected bool $isShowInList = true;
	protected bool $isEditable = true;
	protected bool $isSearchable = false;
	
	protected string $moduleId;
	protected Strategy\IStrategy $strategy;
	
	// region Init ////
	/**
	 * @return int|null
	 * @throws ArgumentException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	protected function getExist(): null|int
	{
		$row = \Bitrix\Main\UserFieldTable::getList([
			'select' => ['ID'],
			'filter' => [
				'=ENTITY_ID' => $this->getEntityId(),
				'=FIELD_NAME' => $this->getCode()
			],
			'limit' => 1
		])->fetchRaw();
		
		if($row === false)
		{
			return null;
		}
		
		return (int)$row['ID'];
	}
	// endregion ////

	// region Get|Set ////
	final public function getModuleId(): string
	{
		return $this->moduleId;
	}
	
	final public function setEntityId(string $value): self
	{
		$this->entityId = $value;

		return $this;
	}

	final public function getEntityId(): string
	{
		return $this->entityId;
	}

	final public function setCode(string $code): self
	{
		$this->code = $code;
		return $this;
	}

	final public function getCode(): string
	{
		return $this->code;
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

	final public function setSort(int $value): self
	{
		$this->sort = $value;
		return $this;
	}

	final public function getSort(): int
	{
		return $this->sort;
	}
	
	final public function setIsMultiple(bool $isMultiple): static
	{
		$this->isMultiple = $isMultiple;
		return $this;
	}
	
	final public function isMultiple(): bool
	{
		return $this->isMultiple;
	}

	final public function setIsRequired(bool $isRequired): static
	{
		$this->isRequired = $isRequired;
		return $this;
	}
	
	final public function isRequired(): bool
	{
		return $this->isRequired;
	}
	
	final public function setIsShowFilter(bool $isShowFilter): static
	{
		$this->isShowFilter = $isShowFilter;
		return $this;
	}
	
	final public function isShowFilter(): bool
	{
		return $this->isShowFilter;
	}
	
	final public function setIsShowInList(bool $isShowInList): static
	{
		$this->isShowInList = $isShowInList;
		return $this;
	}
	
	final public function isShowInList(): bool
	{
		return $this->isShowInList;
	}
	
	final public function setIsEditable(bool $isEditable): static
	{
		$this->isEditable = $isEditable;
		return $this;
	}
	
	final public function isEditable(): bool
	{
		return $this->isEditable;
	}
	
	final public function setIsSearchable(bool $isSearchable): static
	{
		$this->isSearchable = $isSearchable;
		return $this;
	}
	
	final public function isSearchable(): bool
	{
		return $this->isSearchable;
	}
	
	public function getEditFormInfo(): array
	{
		return ['ru' => $this->getTitle()];
	}
	// endregion /////

	// region for Install /////
	/**
	 * @inheritDoc
	 */
	public function getInstallSettings(): array
	{
		return $this->strategy->process($this);
	}
	// endregion /////
	
	// region autoInstall ////
	protected function getInstallatorStrategy(): Installator\Strategy\IStrategy
	{
		return new Installator\Strategy\UfOldStrategy();
	}
	// endregion ////
}