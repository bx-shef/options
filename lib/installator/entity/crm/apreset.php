<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\Crm;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Crm;
use Bitrix\Main\Type\Dictionary;
use CCrmOwnerType;
use Shef\Options\Installator;

/**
 * Абстракция для пресетов реквизитов
 */
abstract class APreset
	extends Installator\Entity\AEntity
	implements Installator\IEntityUf
{
	use Installator\Trait\EntityUfTrait;
	
	/** короткий код пресета */
	protected string $code = '';
	
	/** поля пресета */
	private Dictionary $fields;
	
	protected function __construct()
	{
		parent::__construct();
		$this->fields = new Dictionary();
	}
	
	// region Init ////
	/**
	 * @return int|null
	 * @throws ArgumentException
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 */
	protected function getExist(): null|int
	{
		$row = Crm\PresetTable::getList([
			'select' => ['ID'],
			'filter' => [
				'=ENTITY_TYPE_ID' => $this->getEntityTypeId(),
				'=XML_ID' => $this->getCode()
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
	/**
	 * Возвращает код сущности реквизита
	 * @return int
	 */
	public function getEntityTypeId(): int
	{
		return Crm\EntityPreset::Requisite;
	}
	
	/**
	 * Возвращает код страны пресета
	 * @return string
	 */
	abstract public function getCountryCode(): string;
	
	/**
	 * Возвращает XmlId пресета
	 * @return string
	 */
	public function getCode(): string
	{
		return sprintf(
			'SH_UNP_PRESET_%s_%s',
			$this->code,
			$this->getCountryCode()
		);
	}
	
	/**
	 * Возвращает название пресета
	 * @return string
	 */
	abstract public function getName(): string;
	
	/**
	 * Возвращает сортировку пересета
	 * @return int
	 */
	abstract public function getSort(): int;
	
	/**
	 * Возвращает сущность для которой нужно пресет делать по умолчанию
	 * @return int
	 *
	 * @memo CCrmOwnerType::Undefined - не установит
	 * @memo CCrmOwnerType::Company - установит для компаний
	 * @memo CCrmOwnerType::Contact - установит для контактов
	 */
	public function getDefaultForEntity(): int
	{
		return CCrmOwnerType::Undefined;
	}
	// endregion /////
	
	// region for Fields /////
	/**
	 * @param PresetField $field
	 * @return $this
	 */
	public function addField(PresetField $field): static
	{
		$cnt = $this->fields->count();
		$fieldId = $cnt + 1;
		
		$field->setId($fieldId);
		$this->fields->set($field->name, $field);
		return $this;
	}
	
	public function getFieldsList(): array
	{
		return array_map(
			function(PresetField $field)
			{
				return $field->toArray();
			},
			$this->getFields()->toArray()
		);
	}
	
	/**
	 * @return Dictionary
	 */
	public function getFields(): Dictionary
	{
		return $this->fields;
	}
	// endregion /////

	// region for Install /////
	/**
	 * @inheritDoc
	 *
	 * @memo В данном сулачае это нобор полей для SETTINGS
	 */
	public function getInstallSettings(): array
	{
		$listFields = $this->getFields()->toArray();
		/** @var PresetField $lastField */
		$lastField = end($listFields);
		return [
			'FIELDS' => $this->getFieldsList(),
			'LAST_FIELD_ID' => $lastField?->getId()
		];
	}
	
	abstract protected function getUserFieldItems(): array;
	// endregion /////
	
	// region autoInstall ////
	protected function getInstallatorStrategy(): Installator\Strategy\IStrategy
	{
		return new Installator\Strategy\CrmPresetStrategy();
	}
	// endregion ////
	
	// region Tools ////
	/**
	 * Возвращает Id страны пресета
	 * @return int
	 */
	public function getCountryId(): int
	{
		return (int) GetCountryIdByCode($this->getCountryCode());
	}
	// endregion ////
}