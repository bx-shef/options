<?php declare(strict_types=1);

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Config\Configuration;
use Bitrix\Main\Type\Dictionary;
use Shef\Options\Main\Options;

if(class_exists('ShOptionsConfig'))
{
	return;
}

Loc::loadMessages(__FILE__);

/**
 * Класс сборки опций
 *
 * Был введен для стандартизации файла options_conf.php
 *
 * @see options_conf.php
 */
class ShOptionsConfig
{
	/** @var null|Bitrix\Main\Type\Dictionary Табы */
	private null|Bitrix\Main\Type\Dictionary $tabs;
	
	private array $requireModules = [];
	private array $requirePhpExt = [];
	
	public static function getInstance(
		string $moduleId,
		null|string $indexDoc = null
	): Result
	{
		$result = new Result();
		
		$options = new static(
			moduleId: $moduleId,
			indexDoc: $indexDoc
		);
		
		$response = $options->init();
		if(!$response->isSuccess())
		{
			unset($options);
			return $result->addErrors($response->getErrors());
		}
		
		return $result->setData([
			'OPTIONS' => $options
		]);
	}
	
	protected function __construct(
		public readonly string $moduleId,
		public readonly null|string $indexDoc = null
	)
	{
		$this->tabs = new Dictionary;
		$list = Configuration::getInstance($this->moduleId)->get('requireModules');
		if(is_array($list))
		{
			$this->requireModules = $list;
		}
		
		$list = Configuration::getInstance($this->moduleId)->get('requirePhpExt');
		if(is_array($list))
		{
			$this->requirePhpExt = $list;
		}
	}
	
	private function init():Result
	{
		$result = new Result();
		
		$response = $this->includeSharewareModule();
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}
		
		return $result;
	}
	
	public function get(): array
	{
		$response = $this->requirePhpExt();
		if(!$response->isSuccess())
		{
			$this
				->clearTabs()
				->addTab($this->getTabPhpExt($response))
			;
		}
		
		$this->addTab($this->getTabDocs());
		$this->addTab($this->getTabRequire());
		
		return array_values($this->tabs->toArray());
	}
	
	// region includeSharewareModule ////
	private function includeSharewareModule(): Result
	{
		$result = new Result();
		$response = Loader::includeSharewareModule($this->moduleId);
		if(!in_array(
			$response,
			[
				Loader::MODULE_INSTALLED,
				Loader::MODULE_DEMO,
			]
		))
		{
			return $result->addError(new Error(Loc::getMessage(
				'shef_options_fail_action',
				[
					'#MODULE#' => $this->moduleId
				])
			));
		}
		
		return $result;
	}
	// endregion ////
	
	// region require PHP.Ext ////
	private function requirePhpExt(): Result
	{
		$result = new Result();
		
		foreach($this->requirePhpExt as $extension)
		{
			if(!extension_loaded($extension))
			{
				$result->addError(new Error(Loc::getMessage(
					'shef_requirePhpExt',
					[
						'#EXTENSION#' => $extension
					]
				)));
			}
		}
		
		return $result;
	}
	// endregion ////
	
	// region tabs ////
	private function clearTabs(): static
	{
		$this->tabs->clear();
		return $this;
	}
	
	public function addTab(null|Options\Tab $tab): static
	{
		if(null === $tab)
		{
			return $this;
		}
		
		$this->tabs->set(
			name: $tab->getCode(),
			value: $tab
		);
		
		return $this;
	}
	
	public function getTab(string $code): null|Options\Tab
	{
		$tab = $this->tabs->get($code);
		if($tab instanceof Options\Tab)
		{
			return $tab;
		}
		
		return null;
	}
	// endregion ////
	
	// region tab.PHP.Ext ////
	private function getTabPhpExt(Result $problemsPhpExtension): null|Options\Tab
	{
		if($problemsPhpExtension->isSuccess())
		{
			return null;
		}
		
		return (new Options\Tab('PHP_EXTENSION'))
			->setName(Loc::getMessage('shef_TAB_EXTENSION_TITLE'))
			->setTitle(Loc::getMessage('shef_TAB_EXTENSION_NAME'))
			->addOption(
				(new Options\RowInfo('EXTENSION_ERROR'))
					->setDescription(join(
						'<br>'.PHP_EOL,
						$problemsPhpExtension->getErrorMessages()
					))
					->setType(Options\TypeUIAlert::Error)
			)
		;
	}
	// endregion ////
	
	// region tab.Docs ////
	private function getTabDocs(): null|Options\Tab
	{
		if(null === $this->indexDoc)
		{
			return null;
		}
		
		return (new Options\Tab('DOCS'))
			->setName(Loc::getMessage('shef_TAB_DOCS_NAME'))
			->setTitle(Loc::getMessage('shef_TAB_DOCS_TITLE'))
			->addOption(
				(new Options\Markdown\Option('DOCS', $this->moduleId))
					->setRoot('')
					->setIndex($this->indexDoc)
			)
		;
	}
	// endregion ////
	
	// region tab.Require ////
	private function getTabRequire(): null|Options\Tab
	{
		$tab = (new Options\Tab('REQUIRE'))
			->setName(Loc::getMessage('shef_TAB_REQUIRE_NAME'))
			->setTitle(Loc::getMessage('shef_TAB_REQUIRE_TITLE'))
		;
		
		if(!empty($this->requirePhpExt))
		{
			$tab->addOption(
				(new Options\RowInfo('EXTENSION_NOTE'))
					->setDescription(Loc::getMessage(
						'shef_requirePhpExt_list',
						[
							'#EXTENSION#' => join(', ', $this->requirePhpExt)
						]
					))
					->setType(Options\TypeUIAlert::Note)
			);
		}
		
		if(!empty($this->requireModules))
		{
			$tab->addOption(
				(new Options\RowInfo('MODULES_NOTE'))
					->setDescription(Loc::getMessage(
						'shef_requireModules_list',
						[
							'#MODULE#' => join(', ', $this->requireModules)
						]
					))
					->setType(Options\TypeUIAlert::Note)
			);
		}
		
		if(empty($tab->getOptionList()))
		{
			unset($tab);
			return null;
		}
		
		return $tab;
	}
	// endregion ////
}