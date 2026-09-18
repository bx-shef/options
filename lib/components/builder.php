<?php declare(strict_types=1);

namespace Shef\Options\Components;

use Bitrix\Main\ArgumentNullException;
use Shef\Options\TraitList;
use Shef\Options\Main\Utils;

/*/
//Подключение:

declare(strict_types=1);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
try
{
	if(!\Bitrix\Main\Loader::includeModule('shef.options'))
	{
		throw new \Bitrix\Main\LoaderException('module shef.options not loaded');
	}

	$component = (new \Shef\Options\Components\Builder('shef.insync:import.from.file'))
		->setTemplate('')
		->setOptionCollection([])
		->addOptionCollection('SET_TITLE', 'DEMO SET PARAMS')
		->setIsActive(true)
		->setIsHideIcons(true)
	;
	//$component->include();
	//$component->includeSlider();
	$component->includeSmart();


}
catch(\Throwable $throwable)
{
	ShowError(implode("\n", [
		'Throwable: '.$throwable->getMessage(),
		'File: '.$throwable->getFile(),
		'Line: '.$throwable->getLine(),
		'Trace: '.print_r(str_replace($_SERVER["DOCUMENT_ROOT"], '', $throwable->getTraceAsString()), true)
	]));
}
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");

//Создание объекта класса:
//** @var \Local\Component\Shef\InSync\ShefInSyncImportFromFileComponent $componentClass //
$componentClass = (new \Shef\Options\Components\Builder('shef.insync:import.from.file'))
	->setTemplate('')
	->buildClass();

$componentClass->test();
//*/


/**
 * Класс для работы с компонентами
 *
 * Умеет подключать (просто, через слайдер, автоматически)
 * Умеет создавать объект класса
 *
 */
class Builder
{
	use TraitList\Tools\OptionCollection;
	
	/**
	 * @throws ArgumentNullException
	 */
	public function __construct(
		private string $name,
		private string $template = '',
		array $params = [],
		private ?\CBitrixComponent $parent = null,
		private bool $isActive = true,
		private bool $isHideIcons = true
	)
	{
		$this->initOptionCollection();
		$this->setOptionCollection($params);
	}
	
	// region Get|Set ////
	/**
	 * @param string $name
	 * @return $this
	 */
	public function setName(string $name): static
	{
		$this->name = $name;
		return $this;
	}
	
	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}
	
	/**
	 * @param string $template
	 * @return $this
	 */
	public function setTemplate(string $template): static
	{
		$this->template = $template;
		return $this;
	}
	
	/**
	 * @return string
	 */
	public function getTemplate(): string
	{
		return $this->template;
	}
	
	/**
	 * @param \CBitrixComponent|null $parent
	 * @return $this
	 */
	public function setParent(?\CBitrixComponent $parent): static
	{
		$this->parent = $parent;
		return $this;
	}
	
	/**
	 * @return \CBitrixComponent|null
	 */
	public function getParent(): ?\CBitrixComponent
	{
		return $this->parent;
	}
	
	/**
	 * @param bool $isActive
	 * @return $this
	 */
	public function setIsActive(bool $isActive): static
	{
		$this->isActive = $isActive;
		return $this;
	}
	
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->isActive;
	}
	
	/**
	 * @param bool $isHideIcons
	 * @return $this
	 */
	public function setIsHideIcons(bool $isHideIcons): static
	{
		$this->isHideIcons = $isHideIcons;
		return $this;
	}
	
	/**
	 * @return bool
	 */
	public function isHideIcons(): bool
	{
		return $this->isHideIcons;
	}
	// endregion ////
	
	// region Render ////
	/**
	 * Подключает компонент
	 *
	 * @see \CBitrixComponent::includeComponent
	 * @see \CAllMain::IncludeComponent
	 *
	 * @return void
	 * @throws ArgumentNullException
	 *
	 */
	public function include(): void
	{
		Utils::getCMainApplication()->IncludeComponent(
			$this->getName(),
			$this->getTemplate(),
			$this->getOptionCollection()->toArray(),
			$this->getParent(),
			[
				'HIDE_ICONS' => $this->isHideIcons() ? 'Y' : 'N',
				'ACTIVE_COMPONENT' => $this->isActive() ? 'Y' : 'N'
			]
		);
	}
	
	/**
	 * Подключает компонент для работы в слайдере
	 *
	 * @return void
	 * @throws ArgumentNullException
	 */
	public function includeSlider(): void
	{
		(new self(
			'bitrix:ui.sidepanel.wrapper',
			'',
			[
				'POPUP_COMPONENT_NAME' => $this->getName(),
				'POPUP_COMPONENT_TEMPLATE_NAME' => $this->getTemplate(),
				'POPUP_COMPONENT_PARAMS' => $this->getOptionCollection()->toArray(),
				'USE_UI_TOOLBAR' => 'Y',
				'POPUP_COMPONENT_USE_BITRIX24_THEME' => 'Y',
			]
		))->include();
	}
	
	/**
	 * Подключает компонент в зависимости от контекста
	 *
	 * @return void
	 * @throws ArgumentNullException
	 */
	public function includeSmart(): void
	{
		$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
		
		if ($request->get('IFRAME') === 'Y')
		{
			$this->includeSlider();
		}
		else
		{
			$this->include();
		}
	}
	// endregion ////
	
	// region Component.Class ////
	/**
	 * Подключает класс компонента
	 *
	 * @return string - название класса
	 */
	public function initClass(): string
	{
		return \CBitrixComponent::includeComponentClass($this->getName());
	}
	
	/**
	 * Создает объект компонента
	 *
	 * @memo Параметры не передаются
	 *
	 * @return \CBitrixComponent - объект класса компонента
	 */
	public function buildClass(): \CBitrixComponent
	{
		$componentClass = $this->initClass();
		
		/** @var \CBitrixComponent $component */
		$component = new $componentClass;
		$component->initComponent($this->getName(), $this->getTemplate());
		
		if($component instanceof IAutoloader)
		{
			$component->initAutoloader();
		}
		
		return $component;
	}
	// endregion ////
}