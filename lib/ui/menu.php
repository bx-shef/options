<?php
namespace Shef\Options\UI;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Intranet\Binding\Menu as IntranetMenu;
use Bitrix\Main\Localization\Loc;
Loc::loadLanguageFile(__FILE__);

/*/
// @tosee: need clear cache ////
//title: links for top_panel:user_menu ////
$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->registerEventHandler(
	'intranet', 'onBuildBindingMenu'
	,'shef.options'
	,"\Shef\Options\UI\Menu", "onBuildBindingMenu"
);

\Bitrix\Main\Loader::includeModule('shef.options');
$response = \Shef\Options\UI\Menu::onBuildBindingMenuList();
_pr([$response]);
//*/
class Menu
{
	const PREFIX = 'shef_options';
	const PATH_SERVICE_LIST = 'kb/binding/menu/';
	/**
	 * Returns menu items for different binding places in Intranet.
	 * @param \Bitrix\Main\Event $event Event instance.
	 * @return array
	 */
	public static function test(): array
	{
		return [];
	}
	public static function onBuildBindingMenu(\Bitrix\Main\Event $event): array
	{
		$params = $event->getParameters();
		return static::onBuildBindingMenuList();
	}
	public static function onBuildBindingMenuList(): array
	{
		$context = [];
		$context['USER_ID'] = \Bitrix\Main\Engine\CurrentUser::get()->getId();
		$context['IS_ADMIN'] = \Bitrix\Main\Engine\CurrentUser::get()->isAdmin();

		$bindings = static::getList();

		// associate different bindings
		$bindingsAssoc = [];
		foreach ($bindings as $binding)
		{
			if (!isset($bindingsAssoc[$binding['BINDING_ID']]))
			{
				$bindingsAssoc[$binding['BINDING_ID']] = [];
			}
			$bindingsAssoc[$binding['BINDING_ID']][] = $binding;
		}
		$bindings = $bindingsAssoc;
		unset($bindingsAssoc);

		// init vars
		$items = [];
		$bindingMap = \Bitrix\Intranet\Binding\Menu::getMap();

		// build binding map
		foreach ($bindingMap as $sectionCode => $bindingSection)
		{
			foreach ($bindingSection['items'] as $itemCode => $foo)
			{
				$menuItems = [];
				$bindingCode = $sectionCode . ':' . $itemCode;
				if (isset($bindings[$bindingCode]))
				{
					foreach ($bindings[$bindingCode] as $bindingItem)
					{
						if(
							$bindingItem['IS_FOR_ADMIN']
							&& $context['IS_ADMIN'] === false
						)
						{
							continue;
						}
						$menuItems[] = [
							'id' => static::PREFIX.'_page_'.$bindingItem['ENTITY_TYPE'].$bindingItem['ENTITY_ID'],
							'text' => \htmlspecialcharsbx($bindingItem['TITLE']),
							'href' => $bindingItem['PUBLIC_URL'],
							'sectionCode' => IntranetMenu::SECTIONS['script']
							//'system' => true
							//'extension' => ''
						];
					}
				}

				$items[] = [
					'bindings' => [
						$sectionCode => [
							'include' => [
								$itemCode
							]
						]
					],
					'items' => $menuItems
				];
			}
		}
		return $items;
	}

	// @tosee: need clear cache ////
	public static function getList(): array
	{
		$result = [];
		$i = 0;
		$result[] = [
			'BINDING_ID' => 'top_panel:user_menu'
			,'ENTITY_TYPE' => 'PAGE'
			,'ENTITY_ID' => ++$i
			,'TITLE' => '[SH] Правки ядра'
			,'PUBLIC_URL' => '/local/admin/utils/has-change-files.php'
			,'IS_FOR_ADMIN' => true
		];

		$result[] = [
			'BINDING_ID' => 'top_panel:user_menu'
			,'ENTITY_TYPE' => 'PAGE'
			,'ENTITY_ID' => ++$i
			,'TITLE' => '[SH] Тестирование звонков'
			,'PUBLIC_URL' => '/local/admin/utils/demo-call/make.php'
			,'IS_FOR_ADMIN' => true
		];
		$result[] = [
			'BINDING_ID' => 'top_panel:user_menu'
			,'ENTITY_TYPE' => 'PAGE'
			,'ENTITY_ID' => ++$i
			,'TITLE' => '[SH] Дубликаты по товарам'
			,'PUBLIC_URL' => '/local/admin/utils/show-dublicate-products.php'
			,'IS_FOR_ADMIN' => true
		];
		$result[] = [
			'BINDING_ID' => 'top_panel:user_menu'
			,'ENTITY_TYPE' => 'PAGE'
			,'ENTITY_ID' => ++$i
			,'TITLE' => '[SH] Нумераторы для документов'
			,'PUBLIC_URL' => '/local/admin/conf/numerators.php'
			,'IS_FOR_ADMIN' => true
		];

		return $result;
	}
}