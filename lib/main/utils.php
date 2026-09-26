<?php declare(strict_types=1);

namespace Shef\Options\Main;

use Bitrix\Main\LoaderException;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;

class Utils
{
	/**
	 * Возвращает $APPLICATION
	 * @return \CMain|null
	 */
	public static function getCMainApplication(): ?\CMain
	{
		global $APPLICATION;
		
		return $APPLICATION;
	}
	
	/**
	 * Возвращает CTextParser вкл в режим HTML
	 * Используется для разбора сообщений
	 *
	 * @return \CTextParser
	 */
	public static function getTextParserToHtml(): \CTextParser
	{
		static $obParser;
		if(null === $obParser)
		{
			$obParser = new \CTextParser;
			$obParser->allow['HTML'] = 'Y';
			$obParser->allow['CODE'] = 'Y';
		}
		
		return $obParser;
	}
	
	/**
	 * Рендерит на странице опций модуля закладку
	 *
	 * @param string $moduleId
	 * @param \CAdminTabControl $tabControl
	 * @param array $tabs
	 * @return void
	 */
	public static function renderTab(
		string $moduleId,
		\CAdminTabControl $tabControl,
		array $tabs
	): void
	{
		/** @var Options\Tab $tab */
		/** @var Options\AOption $option */
		
		foreach($tabs as $tab)
		{
			if(!($tab instanceof Options\Tab))
			{
				continue;
			}
			
			$tabControl->BeginNextTab();
			
			echo $tab->render($moduleId);
			
			foreach($tab->getOptionList() as $option)
			{
				if(!($option instanceof Options\AOption))
				{
					continue;
				}
				
				echo $tab->renderOption($moduleId, $option);
			}
		}
	}
	
	/**
	 * Реазизует подсветку синтаксиса php
	 * @param string $codeBlock
	 * @param array|null $colorMap
	 * @return void
	 * @throws LoaderException
	 */
	public static function highlightPhp(
		string &$codeBlock,
		null|array $colorMap = null
	): void
	{
		if(stripos($codeBlock, '&lt;?php') === 0)
		{
			$codeBlock = htmlspecialcharsback($codeBlock);
		}
		
		$codeBlock = str_replace(
			[
				'<code>', '<\code>',
			],
			'',
			highlight_string($codeBlock, true)
		);
		
		if(null === $colorMap)
		{
			$colorMap = static::getColorMap();
		}
		
		$codeBlock = str_replace(
			array_keys($colorMap),
			array_values($colorMap),
			$codeBlock
		);
	}
	
	/**
	 * Список цветов на подмену для подсветки синтаксиса PHP
	 *
	 * Цвета литералами, а не из перечисления чужого модуля: ветка на
	 * shef.uiclear возвращала ровно эти же значения, а модуля нет и не будет.
	 *
	 * @return string[]
	 */
	protected static function getColorMap(): array
	{
		return [
			ini_get('highlight.html') => '#20c997',
			ini_get('highlight.keyword') => '#7cfc00',
			ini_get('highlight.string') => '#fd7e14',
			ini_get('highlight.default') => '#3F9EF7',
			ini_get('highlight.comment') => '#e4e6ef',
		];
	}
	
	/**
	 * Преобразует список департаментов в спиок пользователей
	 *
	 * @param array $rows
	 * @return Result
	 * @throws LoaderException
	 *
	 * @see \Shef\Options\Main\Options\Department
	 */
	public static function convertEntityListDepartmentToUserId(
		array $rows
	): Result
	{
		$result = new Result();
		
		$listAllUsers = array_filter(
			$rows,
			function(array $row)
			{
				return $row['id'] === 'all-users';
			}
		);
		
		if(!empty($listAllUsers))
		{
			unset($listAllUsers);
			
			return $result->setData([
				'IS_ALL_USERS' => true,
				'LIST' => null
			]);
		}
		
		$list = [];
		// region Users ////
		$list = array_merge(
			$list,
			array_column(
				array_filter(
					$rows,
					function(array $row)
					{
						return $row['entityId'] === 'user';
					}
				),
				'id'
			)
		);
		// endregion ////
		
		// region Department ////
		$listDepartment = array_column(
			array_filter(
				$rows,
				function(array $row)
				{
					return $row['entityId'] === 'department';
				}
			),
			'id'
		);
		if(
			Loader::includeModule('intranet')
			&& !empty($listDepartment)
		)
		{
			$cursor = \CIntranetUtils::getDepartmentEmployees(
				arDepartments: $listDepartment,
				bRecursive: true,
				bSkipSelf: false,
				onlyActive: 'N',
				arSelect: ['ID']
			);
			while($fields = $cursor->GetNext())
			{
				$list[] = (int)$fields['ID'];
			}
			
			unset($cursor);
		}
		
		unset($listDepartment);
		// endregion ////
		
		\Bitrix\Main\Type\Collection::normalizeArrayValuesByInt(
			$list,
			false
		);
		
		$list = array_unique($list);
		
		return $result->setData([
			'IS_ALL_USERS' => false,
			'LIST' => $list
		]);
	}
	
	/**
	 * Возвращает URL сервера
	 * @return string
	 */
	public static function getInternalUrl(): string
	{
		$context = Application::getInstance()->getContext();
		if(php_sapi_name() === 'cli')
		{
			if(\Bitrix\Main\Loader::includeModule('crm'))
			{
				return (string)\Bitrix\Main\Config\Option::get('crm', 'portal_protocol_url');
			}
			
			$protocol = 'https';
			$host = \Bitrix\Main\Config\Option::get("main", "server_name");
			
			return "{$protocol}://{$host}";
		}
		
		$server = $context->getServer();
		$protocol = $context->getRequest()->isHttps()
			? "https"
			: "http";
		
		$host = explode(':', $server->getHttpHost());
		
		$host = reset($host);
		
		$port = (int)$server->getServerPort();
		if($port !== 80 && $port !== 443 && $port > 0 && strpos($host, ":") === false)
		{
			$host .= ":".$port;
		}
		// _pr(["{$protocol}://{$host}"]); ////
		return "{$protocol}://{$host}";
	}
	
	/**
	 * Кодирует параметры компонента
	 *
	 * @param array $params
	 * @param string $componentName
	 * @return string
	 * @throws \Bitrix\Main\ArgumentTypeException
	 */
	public static function signComponentParams(
		array $params,
		string $componentName
	): string
	{
		$signer = new \Bitrix\Main\Security\Sign\Signer;
		
		$componentName = str_replace(':', '.', $componentName);
		
		return $signer->sign(
			base64_encode(serialize($params)),
			'signed_'.$componentName
		);
	}
	
	/**
	 * Декодирует параметры компонента
	 *
	 * @param string $params
	 * @param string $componentName
	 * @return array|null
	 * @throws \Bitrix\Main\ArgumentTypeException
	 */
	public static function unsignComponentParams(
		string $params,
		string $componentName
	): null|array
	{
		$signer = new \Bitrix\Main\Security\Sign\Signer;
		
		$componentName = str_replace(':', '.', $componentName);
		
		try
		{
			return (array)unserialize(
				base64_decode(
					$signer->unsign(
						$params,
						'signed_' . $componentName
					)
				),
				['allowed_classes' => false]
			);
		}
		catch (\Bitrix\Main\Security\Sign\BadSignatureException $e)
		{
			return null;
		}
	}
}