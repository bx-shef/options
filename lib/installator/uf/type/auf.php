<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type;

use Bitrix\Main\Error;
use Bitrix\Main\Result;

abstract class AUF
	extends \Shef\Options\Options\Singleton
{
	private int $id;
	private string $entityType; // CRM_LEAD ////
	protected string $name; // UF_CRM_SHUNP_SYNC_RESULT ////
	protected string $title; // FieldName ////
	protected int $sort = 500;
	protected bool $isMultiple = false;
	protected bool $isRequired = false;
	protected bool $isShowFilter = false;
	protected bool $isShowInList = false;
	protected bool $isEditable = false;
	protected bool $isSearchable = false;

	// region Construct ////
	protected function __construct()
	{
		parent::__construct();
	}

	public function init(): self
	{
		$this->setId(0);
		$this->getExist();

		return $this;
	}

	abstract public static function getTypeStrategy(): Strategy\IStrategy;
	// endregion ////

	// region Init Id ////
	public function getExist(): Result
	{
		$result = new Result();

		$element = \CUserTypeEntity::getList(
			['ID'],
			[
				'ENTITY_ID' => $this->getEntityType(),
				'FIELD_NAME' => $this->getName()
			]
		)->fetch();

		$elementId = (int)$element['ID'];
		if($elementId < 1)
		{
			return $result->addError(new Error('UF '.$this->getName().' not exist'));
		}

		$this->setId($elementId);

		return $result->setData([
			'ID' => $this->getId()
		]);
	}
	// endregion ////

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

	final public function setEntityType(string $value): self
	{
		$this->entityType = $value;
		return $this;
	}

	final public function getEntityType(): string
	{
		return $this->entityType;
	}

	final public function setName(string $value): self
	{
		$this->name = $value;
		return $this;
	}

	final public function getName(): string
	{
		return $this->name;
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

	final public function isMultiple(): bool
	{
		return $this->isMultiple;
	}

	final public function isRequired(): bool
	{
		return $this->isRequired;
	}

	final public function isShowFilter(): bool
	{
		return $this->isShowFilter;
	}

	final public function isShowInList(): bool
	{
		return $this->isShowInList;
	}

	final public function isEditable(): bool
	{
		return $this->isEditable;
	}

	final public function isSearchable(): bool
	{
		return $this->isSearchable;
	}

	abstract public function getDefValue();
	// endregion /////

	// region for Install /////
	public function getEditFormInfo(): array
	{
		return ['ru' => $this->getTitle()];
	}

	public function getListColumnInfo(): array
	{
		return $this->getEditFormInfo();
	}

	public function getListFilterInfo(): array
	{
		return $this->getEditFormInfo();
	}

	public function getHelpInfo(): array
	{
		return ['ru' => ''];
	}

	public function getErrorInfo(): array
	{
		return ['ru' => ''];
	}

	public function getInstallSettings(): array
	{
		return static::getTypeStrategy()->process($this);
	}
	// endregion /////
}