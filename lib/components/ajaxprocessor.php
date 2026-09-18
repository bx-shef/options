<?php declare(strict_types=1);

namespace Shef\Options\Components;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Shef\Options\TraitList;

Loc::loadMessages(__FILE__);

/**
 * Абстракция для обработки ajax запросов вне компонента
 */
abstract class AjaxProcessor
	extends Controller
{
	use TraitList\Modules;
	
	abstract protected static function getModulesList(): array;
	
	/**
	 * @throws LoaderException
	 */
	protected function init(): void
	{
		parent::init();
		
		$response = $this->includeModules();
		if(!$response->isSuccess())
		{
			$this->addErrors($response->getErrors());
		}
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
	public function configureActions(): array
	{
		return parent::configureActions();
	}
	
	/**
	 * Название компонента
	 *
	 * @return string
	 */
	abstract protected static function getComponentName(): string;
	
	/**
	 * @throws ArgumentNullException
	 */
	protected function createComponentBuilder(): Builder
	{
		return new Builder(static::getComponentName());
	}
}