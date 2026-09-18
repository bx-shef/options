<?php declare(strict_types=1);

namespace Shef\Options\Main\Options\Markdown;

use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web;
use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Options;

Loc::loadMessages(__FILE__);

/**
 * Для вывода информации Markdown
 *
 * @see Controller
 */
class Option
	extends Options\RowInfo
{
	private string $rootPath;
	private string $indexPage;
	private Controller $controller;
	
	
	public function __construct(
		string $code,
		public readonly string $moduleId
	)
	{
		parent::__construct($code);
		$this->controller = new Controller();
		
		Extension::load([
			'shef-options.options-markdown',
		]);
	}
	
	// region Get/Set ////
	public function setRoot(string $rootPath): static
	{
		$this->rootPath = $rootPath;
		return $this;
	}
	
	public function getRoot(): string
	{
		return $this->rootPath;
	}
	
	public function setIndex(string $indexPage): static
	{
		$this->indexPage = $indexPage;
		return $this;
	}
	
	public function getIndex(): string
	{
		return $this->indexPage;
	}
	
	protected function getActionUrl(string $action, array $params = []): Web\Uri
	{
		return \Bitrix\Main\Engine\UrlManager::getInstance()->createByController(
			controller: $this->controller,
			action: $action,
			params: $params,
			absolute: true
		);
	}
	
	protected function getContent(): string
	{
		$response = $this->controller->getContentAction(
			url: $this->getRoot().'/'.$this->getIndex(),
			moduleId: $this->moduleId
		);
		if(null === $response)
		{
			return '';
		}
		
		return (string) $response['content'];
	}
	// endregion ////
	
	// region Render /////
	protected function renderTitle(): string
	{
		return '';
	}
	
	protected function renderValue(string $moduleId): string
	{
		$params = null;
		parse_str($this->getActionUrl('getContent')->getQuery(), $params);
		$confJs = [
			'containerId' => 'markdown_'.$this->getCode(),
			'actionUrl' => $params['action'],
			'rootPath' => $this->getRoot(),
			'moduleId' => $this->moduleId
		];
		ob_start();?>
		<script>
			BX.ready(() => {
				BX.ShOptions.Markdown.create(<?=Web\Json::encode($confJs)?>);
			});
		</script>
		<?php
		$js = ob_get_contents();
		ob_end_clean();
		
		return sprintf(
			'<div class="content-markdown content-markdown-hidden"><div class="content-markdown-inner" id="%s">%s</div></div>%s',
			(string) $confJs['containerId'],
			$this->getContent(),
			$js
		);
	}
	
	public function render(string $moduleId): string
	{
		return sprintf('<td colspan="2" style="%s">%s</td>', $this->getPaddingStyle(), $this->renderValue($moduleId),);
	}
	// endregion ////
}