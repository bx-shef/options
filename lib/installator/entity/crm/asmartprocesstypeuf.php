<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\Crm;

use Shef\Options\Installator;

/**
 * Абстрацкия UF для смартпроцессов
 */
abstract class ASmartProcessTypeUf
	extends Installator\Entity\UF\AEntity
{
	protected string $shortCode = '';
	
	/**
	 * Для UF смартпроцессов UF можно только после инициализации опеределить
	 * @return void
	 *
	 * @see \Shef\Options\Installator\Entity\Crm\ASmartProcessTypeUf::initByEntityId
	 */
	protected function init(): void
	{
		if($this->getEntityId() === '')
		{
			return;
		}
		
		parent::init();
	}
	
	final public function initByEntityId(string $value): self
	{
		$this->entityId = $value;
		
		$this->code = Installator\Entity\UF\EEntityId::generateUfCode(
			$this->entityId,
			$this->shortCode
		);
		
		$this->init();
		
		return $this;
	}
	
	// region autoInstall ////
	protected function getInstallatorStrategy(): Installator\Strategy\IStrategy
	{
		return new Installator\Strategy\UfStrategy();
	}
	// endregion ////
}