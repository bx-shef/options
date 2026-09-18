<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Error;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\Result;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\Contract\Arrayable;
use Shef\Options\Options;
use Shef\Options\Installator;

/**
 * Абстракция сущности
 */
abstract class AEntity
	extends Options\Singleton
	implements Installator\IEntity, Arrayable
{
	private int $id = 0;
	
	// region Construct ////
	
	/**
	 * @throws ObjectPropertyException
	 * @throws SystemException
	 * @throws ArgumentException
	 */
	final public static function getInstance(): static
	{
		$instance = parent::getInstance();
		
		$instance->init();
		
		return $instance;
	}
	
	protected function __construct()
	{
		parent::__construct();
	}
	
	// region Init ////
	/**
	 * Востановит сущность
	 * Если нету - Автоматически проинсталирует
	 *
	 * @return void
	 */
	protected function init(): void
	{
		$entityId = $this->getExist();
		if((int)$entityId > 0)
		{
			$this->setId($entityId);
		}
		else
		{
			$response = $this->autoInstall();
		}
	}
	
	abstract protected function getExist(): null|int;
	// endregion ////
	
	// region Get|Set ////
	final public function setId(int $id): static
	{
		$this->id = $id;
		return $this;
	}
	
	final public function getId(): int
	{
		return $this->id;
	}
	
	/**
	 * Возвращает значение по умолчанию
	 *
	 * @return mixed
	 */
	public function getDefaultValue(): mixed
	{
		return null;
	}
	// endregion /////
	
	// region for Install /////
	/**
	 * @inheritDoc
	 */
	abstract public function getInstallSettings(): array;
	// endregion /////
	
	// region autoInstall ////
	abstract protected function getInstallatorStrategy(): Installator\Strategy\IStrategy;
	
	/**
	 * Переустановка сущности
	 * @return Result
	 */
	public function reInstall(): Result
	{
		return $this->autoInstall();
	}
	/**
	 * Занимается установкой сущности
	 *
	 * @return Result
	 */
	private function autoInstall(): Result
	{
		$result = new Result();
		
		$manager = new Installator\Manager(
			$this->getInstallatorStrategy()
		);
		
		$response = $manager->process($this);
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}
		
		return $result->setData($response->getData());
	}
	// endregion ////
	
	// region for Tools /////
	public function toArray(): array
	{
		return $this->getInstallSettings();
	}
	// endregion ////
}