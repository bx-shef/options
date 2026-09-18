<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type;

use Bitrix\Main\Type\Dictionary;

abstract class AUFEnum
	extends AUF
{
	/** @var Dictionary|AEnumItem[]  */
	private ?Dictionary $enums = null;


	public static function getTypeStrategy(): Strategy\IStrategy
	{
		return new Strategy\UFEnum();
	}

	public function init(): self
	{
		parent::init();
		$this->enums = new Dictionary();
		$this->reInitEnums();

		return $this;
	}

	final public function reInitEnums(): void
	{
		$this->enums->clear();
		$this->buildEnums();
	}

	private function buildEnums(): void
	{
		/** @var AEnumItem $item */
		foreach($this->getEnumsItems() as $item)
		{
			$this->addEnumsItem($item);
		}

		$this->prepareEnumsId();
	}

	private function addEnumsItem(AEnumItem $value): self
	{
		$this->getEnums()->set($value->getXmlId(), $value);
		return $this;
	}

	/**
	 * @return AEnumItem[]
	 */
	abstract protected function getEnumsItems(): array;
	/*/
	protected function getEnumsItems(): array
	{
		return [
			(new EnumItem())
				->setXmlId('TEST1')
				->setTitle('TEST1')
				->setSort(10)
				->setIsDef(true),
			(new EnumItem())
				->setXmlId('TEST2')
				->setTitle('TEST2')
				->setSort(20)
				->setIsDef(false)
		];
	}
	//*/

	/**
	 * @return Dictionary|AEnumItem[]
	 */
	final public function getEnums(): Dictionary
	{
		return $this->enums;
	}

	public function getEnumByXmlId(string $value): ?AEnumItem
	{
		/** @var AEnumItem $field */
		$field = $this->getEnums()->get($value);
		if($field instanceof AEnumItem)
		{
			return $field;
		}

		return null;
	}

	private function prepareEnumsId(): void
	{
		$cursor = \CUserFieldEnum::GetList(
			['SORT' => 'ASC'],
			['USER_FIELD_ID' => $this->getId()]
		);
		while($enumField = $cursor->fetch())
		{
			$enum = $this->enums->get($enumField['XML_ID']);
			if($enum instanceof AEnumItem)
			{
				$enum->setId((int)$enumField['ID']);
			}

		}
		unset($cursor);
	}
}