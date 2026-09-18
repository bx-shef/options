<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Tools;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main;
use Bitrix\Main\Error;
use Bitrix\Main\Type\Dictionary;

/**
 * Трейт для работы с опциями
 */
trait OptionCollection
{
	/** @var null|Dictionary Опции сихронизации */
	private ?Dictionary $optionCollection = null;
	
	/**
	 * @return void
	 */
	protected function initOptionCollection(): void
	{
		$this->optionCollection = new Dictionary;
	}
	
	/**
	 * @param array $options
	 * @return $this
	 * @throws ArgumentNullException
	 */
	final public function setOptionCollection(array $options): self
	{
		$this->getOptionCollection()->setValues($options);
		return $this;
	}
	
	/**
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 * @throws ArgumentNullException
	 */
	final public function addOptionCollection(string $name, mixed $value): self
	{
		$this->getOptionCollection()->set($name, $value);
		return $this;
	}
	
	/**
	 * @return Dictionary
	 * @throws ArgumentNullException
	 */
	final public function getOptionCollection(): Dictionary
	{
		if(!($this->optionCollection instanceof Dictionary))
		{
			throw new \Bitrix\Main\ArgumentNullException('optionCollection');
		}
		return $this->optionCollection;
	}
}