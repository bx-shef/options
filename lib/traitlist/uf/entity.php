<?php
declare(strict_types=1);

namespace Shef\Options\TraitList\UF;

use Bitrix\Main\Type\Dictionary;
use Bitrix\Main\Result;
use Shef\Options\Installator\UF;

/**
 * Trait Entity
 * @package Shef\Options\TraitList\UF
 *
 * Трейт для хранения, создания UF
 *
 */
trait Entity
{
	// region UF.work ////

	/** @var Dictionary[UF\Type\AUF]|null */
	protected ?Dictionary $userFields = null;

	/**
	 * Инициализирует словарь Dictionary UF
	 *
	 * Если нужно вносит UF в БД
	 *
	 * @return Result
	 * @throws \Bitrix\Main\ArgumentTypeException
	 */
	protected function initUserFields(): Result
	{
		$this->userFields = new Dictionary();
		return $this->buildUserFields();
	}

	/**
	 * Строит словарь Dictionary UF на основании перечисления array UF и вносит UF в БД
	 *
	 * @return Result
	 * @throws \Bitrix\Main\ArgumentTypeException
	 */
	protected function buildUserFields(): Result
	{
		foreach($this->getUserFieldItems() as $item)
		{
			$this->addUserField($item);
		}

		return $this->processUserFields();
	}

	/**
	 * Создает UF в БД
	 *
	 * @return Result
	 * @throws \Bitrix\Main\ArgumentTypeException
	 */
	protected function processUserFields(): Result
	{
		$result = new Result();

		$response = UF\Manager::build($this->getUserFields());
		if(!$response->isSuccess())
		{
			$result->addErrors($response->getErrors());
		}

		return $result;
	}

	/**
	 * Добавляет UF в словарь Dictionary
	 *
	 * @param UF\Type\AUF $value
	 * @return $this
	 * @throws \Bitrix\Main\ArgumentTypeException
	 */
	protected function addUserField(UF\Type\AUF $value): self
	{
		$this->getUserFields()->set($value->getName(), $value);
		return $this;
	}
	
	/**
	 * Возвращает перечисление array конкретных UF
	 *
	 * @memo реализацию UF\Type\AUF нужно создать в своем модуле
	 *
	 * @return UF\Type\AUF[]
	 */
	abstract protected function getUserFieldItems(): array;
	// endregion ////

	// region IEntity /////

	/**
	 * @inheritDoc
	 *
	 * @throws \Bitrix\Main\ArgumentTypeException
	 */
	public function getUserFields(): Dictionary
	{
		if(!($this->userFields instanceof Dictionary))
		{
			throw new \Bitrix\Main\ArgumentTypeException('userFields', Dictionary::class);
		}

		return $this->userFields;
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \Bitrix\Main\ArgumentTypeException
	 */
	public function getUserFieldByName(string $value): ?UF\Type\AUF
	{
		/** @var Uf\Type\AUF $field */
		$field = $this->getUserFields()->get($value);
		if($field instanceof UF\Type\AUF)
		{
			return $field;
		}
		
		return null;
	}
	// endregion ////
}