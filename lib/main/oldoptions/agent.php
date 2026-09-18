<?php

namespace Shef\Options\Main\Options;

use Bitrix\Main\ArgumentNullException;
use Shef\InSync\Agents;

/**
 * Для вывода опции настройки Агетн на страницы параметров модуля
 *
 * @see \Shef\Options\Main\Options\Controller\Agent
 */
if(!\Bitrix\Main\Loader::includeModule('shef.insync'))
{
	class Agent
	{
		public static function __callStatic(string $name, array $arguments): mixed
		{
			return null;
		}
	}
	
	return;
}
class Agent
	extends RowInfo
{
	private ?Agents\Entity $agentEntity = null;
	
	public function __construct(Tab $tab, string $code)
	{
		parent::__construct($tab, $code);
		
		$this->setType(TypeUIAlert::Warning);
		
		\Bitrix\Main\UI\Extension::load([
			'ui.buttons',
			'ui.buttons.icons',
			'ajax'
		]);
	}
	
	// region Get/Set ////
	/**
	 * @param Agents\Entity $agentEntity
	 * @return $this
	 */
	public function setAgentEntity(Agents\Entity $agentEntity): self
	{
		$this->agentEntity = $agentEntity;
		return $this;
	}
	
	/**
	 * @return Agents\Entity
	 * @throws ArgumentNullException
	 */
	public function getAgentEntity(): Agents\Entity
	{
		if(!($this->agentEntity instanceof Agents\Entity))
		{
			throw new \Bitrix\Main\ArgumentNullException('agentEntity');
		}
		
		return $this->agentEntity;
	}
	
	public function getDescription(): string
	{
		$descr = parent::getDescription();
		
		$obParser = new \CTextParser;
		$descr = $obParser->convertText($descr);
		unset($obParser);
		
		return $descr;
	}
	
	public function getAgentEntityUrlStop(): string
	{
		$urlManager = \Bitrix\Main\Engine\UrlManager::getInstance();
		return $urlManager->create(
			'shef:options.options.agent.stopAgent',
			[
				'agentId' => $this->getAgentEntity()->getId(),
				'moduleId' => $this->getAgentEntity()->getModule()
			],
			false
		)->getUri();
	}
	
	public function getAgentEntityUrlStart(): string
	{
		$urlManager = \Bitrix\Main\Engine\UrlManager::getInstance();
		return $urlManager->create(
			'shef:options.options.agent.startAgent',
			[
				'agentId' => $this->getAgentEntity()->getId(),
				'moduleId' => $this->getAgentEntity()->getModule()
			],
			false
		)->getUri();
	}
	// endregion ////
}