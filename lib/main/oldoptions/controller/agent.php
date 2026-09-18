<?php

namespace Shef\Options\Main\Options\Controller;

use Bitrix\Main\Web\Uri;
use Shef\Options\Components\Actions;
use Shef\Options\TraitList;

/*/
	/bitrix/services/main/ajax.php?action=shef:options.options.agent.startAgent&agentId=-100&moduleId=shef.currency
	
	\Bitrix\Main\Loader::includeModule('shef.options');
	$urlManager = \Bitrix\Main\Engine\UrlManager::getInstance();
	$element['path1'] = $urlManager->create(
		'shef:options.options.agent.startAgent', [
			'agentId' => -100,
			'moduleId' => 'shef.currency'
		], true
	)->getUri();

	echo '<a href="'.$element['path1'].'">path1</a><br />';
	_pr([$element]);
//*/


/**
 * Ajax контроллер для запуска агентов из страницы настроек модуля
 * @see \Shef\Options\Main\Options\Agent
 */
class Agent
	extends \Bitrix\Main\Engine\Controller
{
	use TraitList\Modules;
	
	protected static function getModulesList(): array
	{
		return [
			'shef.insync'
		];
	}
	
	protected function init(): void
	{
		parent::init();
		
		$response = $this->includeModules();
		if(!$response->isSuccess())
		{
			$this->addErrors($response->getErrors());
		}
	}
	
	public function configureActions()
	{
		return [
			'startAgent' => Actions\Normal::get(),
			'stopAgent' => Actions\Normal::get(),
		];
	}
	
	public function startAgentAction(int $agentId, string $moduleId): ?\Bitrix\Main\Engine\Response\Redirect
	{
		$response = \Shef\InSync\Agents\Manager::startById($agentId);
		$isError = false;
		$message = '';
		if(!$response->isSuccess())
		{
			$isError = true;
			$message = implode(';', $response->getErrorMessages());
		}
		
		$url = new Uri('/bitrix/admin/settings.php');
		$url->addParams([
			'lang' => LANGUAGE_ID,
			'mid' => $moduleId,
			'isError' => $isError ? 'Y' : 'N',
			'msg' => $message,
		]);
		
		$response = new \Bitrix\Main\Engine\Response\Redirect($url);
		
		return $response;
	}
	
	public function stopAgentAction(int $agentId, string $moduleId): ?\Bitrix\Main\Engine\Response\Redirect
	{
		$response = \Shef\InSync\Agents\Manager::stopById($agentId);
		
		$isError = false;
		$message = '';
		if(!$response->isSuccess())
		{
			$isError = true;
			$message = implode(';', $response->getErrorMessages());
		}
		
		
		$url = new Uri('/bitrix/admin/settings.php');
		$url->addParams([
			'lang' => LANGUAGE_ID,
			'mid' => $moduleId,
			'isError' => $isError ? 'Y' : 'N',
			'msg' => $message,
		]);
		
		$response = new \Bitrix\Main\Engine\Response\Redirect($url);
		
		return $response;
	}
	
	
}