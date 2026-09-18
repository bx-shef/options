<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Tools;

use Bitrix\Main\ArgumentNullException;

/**
 * Трейт для работы с текущей ДатойВремя
 *
 * Хранит форматы Дата и ДатаВремя
 * Инициализирует текущую дату
 */
trait DateTime
{
	protected string $dateTimeFormat;
	protected string $dateFormat;
	protected ?\Bitrix\Main\Type\DateTime $curDateTime;
	
	/**
	 * Инициализация формата и текущей ДатаВремя
	 * @return void
	 */
	protected function initDateTime(): void
	{
		$this->dateTimeFormat = \Bitrix\Main\Type\DateTime::convertFormatToPhp(FORMAT_DATETIME);
		$this->dateFormat = \Bitrix\Main\Type\Date::convertFormatToPhp(FORMAT_DATE);

		$this->setCurDateTime(new \Bitrix\Main\Type\DateTime());
	}
	
	/**
	 * Устанавливает текущую ДатуВремя
	 * @param \Bitrix\Main\Type\DateTime $value
	 * @return $this
	 */
	public function setCurDateTime(\Bitrix\Main\Type\DateTime $value): self
	{
		$this->curDateTime = $value;
		return $this;
	}
	
	/**
	 * Получает текущую ДатаВремя
	 * @throws ArgumentNullException
	 */
	public function getCurDateTime(): \Bitrix\Main\Type\DateTime
	{
		if(!($this->curDateTime instanceof \Bitrix\Main\Type\DateTime))
		{
			throw new \Bitrix\Main\ArgumentNullException('curDateTime');
		}

		return $this->curDateTime;
	}
}