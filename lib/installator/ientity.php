<?php declare(strict_types=1);

namespace Shef\Options\Installator;

/**
 * Интерфейс сущности
 */
interface IEntity
{
	public const EnumN = 'N';
	public const EnumY = 'Y';
	
	public function getId(): int;
	
	/**
	 * Возвращает подготовленные к установке данные
	 *
	 * @return array
	 */
	public function getInstallSettings(): array;
}