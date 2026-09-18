<?php
declare(strict_types=1);

namespace Shef\Options\Installator\Trait;

use Bitrix\Main\Type\Dictionary;
use Shef\Options\Installator\Entity;

/**
 * Трейт для хранения UF сущности
 *
 * @see \Shef\Options\Installator\IEntityUf
 */
trait EntityUfTrait
{
	// region UF.work ////
	/** @var null|Dictionary */
	protected null|Dictionary $userFields = null;

	/**
	 * Инициализирует словарь Dictionary UF
	 *
	 * @return void
	 */
	protected function initUserFields(): void
	{
		$this->userFields = new Dictionary();
	}
	
	/**
	 * Возвращает перечисление array конкретных UF
	 *
	 * @return Entity\UF\AEntity[]
	 */
	abstract protected function getUserFieldItems(): array;
	
	/**
	 * Строит Dictionary на основании перечисления UF
	 *
	 * @return void
	 */
	public function makeUserFields(): void
	{
		if(!($this->userFields instanceof Dictionary))
		{
			$this->userFields = new Dictionary();
		}
		
		$this->userFields->clear();
		
		/** @var Entity\UF\AEntity $userField */
		foreach($this->getUserFieldItems() as $userField)
		{
			$this->userFields->set(
				$userField->getCode(),
				$userField
			);
		}
	}
	// endregion ////

	// region IEntityUf /////
	/**
	 * @inheritDoc
	 */
	public function getUserFields(): Dictionary
	{
		if(!($this->userFields instanceof Dictionary))
		{
			$this->userFields = new Dictionary();
		}
		
		if($this->userFields->count() < 1)
		{
			$this->makeUserFields();
		}

		return $this->userFields;
	}

	/**
	 * @inheritDoc
	 */
	public function getUserFieldByCode(string $code): null|Entity\UF\AEntity
	{
		$field = $this->getUserFields()->get($code);
		if($field instanceof Entity\UF\AEntity)
		{
			return $field;
		}
		
		return null;
	}
	// endregion ////
}