<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF;

use Bitrix\Main\Type\Dictionary;

/**
 * Абстракция UF типа перечисление
 */
abstract class AEntityEnum
	extends AEntity
{
	/** @var null|Dictionary */
	private null|Dictionary $enums;
	
	protected function __construct()
	{
		parent::__construct();
		
		$this->strategy = new Strategy\UFEnum();
		$this->enums = new Dictionary();
	}
	
	/**
	 * @inheritDoc
	 */
	protected function init(): void
	{
		parent::init();
		
		$this->prepareEnumsId();
	}
	
	/**
	 * @return Dictionary
	 */
	final public function getEnums(): Dictionary
	{
		return $this->enums;
	}
	/**
	 * @return array
	 */
	final public function getEnumsList(): array
	{
		return array_map(
			function(EnumItem $enum)
			{
				return $enum->toArray();
			},
			$this->getEnums()->toArray()
		);;
	}
	
	/**
	 * @param EnumItem $enumItem
	 * @return $this
	 */
	final public function addEnum(EnumItem $enumItem): static
	{
		$this->enums->set(
			$enumItem->getXmlId(),
			$enumItem
		);
		
		return $this;
	}
	
	/**
	 * Возвращает перечисление по XmlId
	 *
	 * @param string $xmlId
	 * @return EnumItem|null
	 */
	public function getEnumByXmlId(string $xmlId): null|EnumItem
	{
		/** @var EnumItem $field */
		$field = $this->getEnums()->get($xmlId);
		if($field instanceof EnumItem)
		{
			return $field;
		}

		return null;
	}
	
	/**
	 * Инициирует ID перечислений
	 *
	 * @return void
	 */
	public function prepareEnumsId(): void
	{
		$cursor = \CUserFieldEnum::GetList(
			['SORT' => 'ASC'],
			['USER_FIELD_ID' => $this->getId()]
		);
		while($enumField = $cursor->fetch())
		{
			$enum = $this->enums->get($enumField['XML_ID']);
			if($enum instanceof EnumItem)
			{
				$enum->setId((int)$enumField['ID']);
			}

		}
		unset($cursor);
	}
}