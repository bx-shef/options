<?php declare(strict_types=1);

namespace Shef\Options\Installator;

/**
 * Интерфейс указывает что ID новой сущности нужно сохранить в свойство
 * после создания
 */
interface ISaveOption
{
	/**
	 * В какой моудль сохранять
	 * @return string
	 */
	public function getModuleIdForSaveOption(): string;
	
	/**
	 * В какое поле сохранять
	 * @return string
	 */
	public function getCodeForSaveOption(): string;
	
	/**
	 * Какой Id сохранять
	 * @return int
	 */
	public function getId(): int;
}