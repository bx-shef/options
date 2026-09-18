<?php declare(strict_types=1);

namespace Shef\Options\Components;

use Bitrix\Main\Application;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Errorable;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Localization\Loc;
use Shef\Options\TraitList;
use Shef\Options\Main\Utils;

Loc::loadMessages(__FILE__);

abstract class AControllerable
	extends AComponent
	implements IClass, IAutoloader, Controllerable, Errorable
{
	// region Ajax ////
	/**
	 * Возвращает фильтры по умолчанию для AJAX запросов
	 *
	 * Умолчание — Normal, то есть проверки ядра: аутентификация, метод
	 * запроса, csrf. Раньше здесь стоял Free, и тогда КАЖДОЕ действие
	 * наследника, не переопределившего этот метод, отвечало без входа на
	 * портал и без проверки csrf — включая те, что что-то меняют.
	 *
	 * Компоненту, который обязан работать для гостей, Free никуда не делся,
	 * но выбирают его теперь явно и в одном месте — в configureActions()
	 * этого компонента.
	 *
	 * @see \Shef\Options\Components\Actions\IActionsFilterList
	 *
	 * @return array
	 */
	public function getConfigureActionsDefFilter(): array
	{
		return Actions\Normal::get();
	}
	
	/**
	 * Возвращает список действий для AJAX
	 * @return array[]
	 *
	 * <code>
	 * return [
	 * 	'demo' => $this->getConfigureActionsDefFilter()
	 * ];
	 * </code>
	 */
	abstract public function configureActions(): array;
	
	protected function initAjax(): void
	{
		$this->initErrorCollection();
		
		$this->initAutoloader();
		
		$response = $this->includeModules();
		if(!$response->isSuccess())
		{
			$this->getErrorCollection()->add($response->getErrors());
			return;
		}
		
		$this->initParams();
		$this->checkRequiredParams();
		if(!$this->getErrorCollection()->isEmpty())
		{
			return;
		}
		
		$this->initResult();
	}
	// endregion ////
}