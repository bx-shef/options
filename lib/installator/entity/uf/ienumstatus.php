<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF;

/**
 * Интерфейс перечисления для статусов
 *
 * @see EEnumStatus
 */
interface IEnumStatus
{
	/**
	 * Id статуса по умолчанию
	 * @return int
	 */
	public function getDefaultValue(): int;
	
	/**
	 * Тип Не определен
	 * @return EnumItem|null
	 */
	public function getTypeUndefined(): null|EnumItem;
	
	/**
	 * Тип Новый
	 * @return EnumItem|null
	 */
	public function getTypeNew(): null|EnumItem;
	
	/**
	 * Тип В обработке
	 * @return EnumItem|null
	 */
	public function getTypeProcess(): null|EnumItem;
	
	/**
	 * Тип Успех
	 * @return EnumItem|null
	 */
	public function getTypeSuccess(): null|EnumItem;
	
	/**
	 * Тип Брак
	 * @return EnumItem|null
	 */
	public function getTypeFail(): null|EnumItem;
	
	/**
	 * Массив Id статусов в работе
	 * @return array
	 */
	public function getWorkStatusIdList(): array;
	
	/**
	 * Id успешного статуса
	 * @return int
	 */
	public function getWinStatusId(): int;
	
	/**
	 * Массив Id статусов брака
	 * @return array
	 */
	public function getLoseStatusIdList(): array;
}