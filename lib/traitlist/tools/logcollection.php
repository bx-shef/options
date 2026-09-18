<?php

namespace Shef\Options\TraitList\Tools;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main;
use Bitrix\Main\Error;
use Bitrix\Main\Type\Dictionary;

/**
 * Трейт для работы с логом обработки
 */
trait LogCollection
{
	/** @var ?Dictionary Лог сихронизации */
	private ?Dictionary $logCollection = null;

	/**
	 * @return void
	 */
	protected function initLogCollection(): void
	{
		$this->logCollection = new Dictionary;
	}

	/**
	 * @param array $logs
	 * @return $this
	 * @throws ArgumentNullException
	 */
	final public function setLogCollection(array $logs): self
	{
		$this->getLogCollection()->setValues($logs);
		return $this;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 * @throws ArgumentNullException
	 */
	final public function addLogCollection(string $name, mixed $value): self
	{
		$this->getLogCollection()->set($name, $value);
		return $this;
	}

	/**
	 * @return Dictionary
	 * @throws ArgumentNullException
	 */
	final public function getLogCollection(): Dictionary
	{
		if(!($this->logCollection instanceof Dictionary))
		{
			throw new \Bitrix\Main\ArgumentNullException('logCollection');
		}
		return $this->logCollection;
	}
}