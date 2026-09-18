<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Tools;

/**
 * Трейт для работы с режимом отладки/разработки
 */
trait IsDebug
{
	private bool $isDebug = false;
	
	/**
	 * Вкл/Выкл режим отладки
	 *
	 * @param bool $isDebug
	 * @return $this
	 */
	public function setIsDebug(bool $isDebug): self
	{
		$this->isDebug = $isDebug;
		return $this;
	}
	
	/**
	 * Указывает что работает в режиме отладки.
	 *
	 * @return bool
	 */
	public function isDebug(): bool
	{
		return $this->isDebug;
	}
}