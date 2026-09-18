<?php declare(strict_types=1);

namespace Shef\Options\Components;

use Bitrix\Main\Application;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Errorable;
use Bitrix\Main\Localization\Loc;
use Shef\Options\TraitList;
use Shef\Options\Main\Utils;

Loc::loadMessages(__FILE__);

abstract class AComponent
	extends \CBitrixComponent
	implements IClass, IAutoloader, Errorable
{
	use Trait\ComponentNameTrait;
	use Trait\AutoloaderTrait;
	use TraitList\Tools\DateTime;
	use TraitList\Tools\ErrorCollection;
	use TraitList\Modules;
	
	// region arParams ////
	protected function listKeysSignedParameters(): ?array
	{
		return [];
	}
	/**
	 * Инициализация $this->arParams
	 * @return void
	 */
	protected function initParams(): void
	{}
	
	/**
	 * Выполняет проверку обязательных параметром
	 *
	 * @return void
	 * @throws \Bitrix\Main\ArgumentNullException
	 * <code>
	 * if((int)$this->arParams['DEMO'] < 1)
	 * {
	 * 	$this->addError(new Error('test Error'));
	 * 	return;
	 * }
	 * </code>
	 */
	protected function checkRequiredParams(): void
	{}
	// endregion ////
	
	// region arResult ////
	/**
	 * Инициализация $this->arResult
	 * @return void
	 */
	protected function initResult(): void
	{
		$this->arResult = [];
	}
	// endregion ////
	
	// region Work ////
	/**
	 * Выполнение компонента из публичной части
	 * @return mixed|void|null
	 * @throws \Bitrix\Main\ArgumentNullException
	 */
	public function executeComponent()
	{
		$this->initErrorCollection();
		
		$this->initAutoloader();
		
		$response = $this->includeModules();
		if(!$response->isSuccess())
		{
			$this->getErrorCollection()->add($response->getErrors());
			$this->printErrors();
			return;
		}
		
		// @memo Params mast call before set Result ////
		$this->initParams();
		$this->checkRequiredParams();
		if(!$this->getErrorCollection()->isEmpty())
		{
			$this->printErrors();
			return;
		}
		
		$this->setPageProperty();
		
		$this->initResult();
		$this->process();
		if(!$this->getErrorCollection()->isEmpty())
		{
			$this->printErrors();
			return;
		}
		
		$this->renderTemplate();
	}
	
	protected function renderTemplate(): void
	{
		$this->includeComponentTemplate();
	}
	
	abstract protected function process(): void;
	// endregion ////
	
	// region Tools.Page ////
	/**
	 * Устанавливает данные для страницы
	 * <code>
	 * Utils::getCMainApplication()->SetTitle('TITLE');
	 * Utils::getCMainApplication()->SetPageProperty('title', 'PAGE_TITLE');
	 * </code>
	 * @return void
	 */
	protected function setPageProperty(): void
	{}
	// endregion ////
	
}