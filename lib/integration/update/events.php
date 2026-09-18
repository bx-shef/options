<?php
namespace Shef\Options\Integration\Update;

use Bitrix\Main\Localization\Loc;

/*/
\Bitrix\Main\Loader::includeModule('shef.options');
$items = [];
\Shef\Options\Integration\Update\Events::onAdminContextMenuShow($items);
_pr([$items]);

// Events ////
	$eventManager = \Bitrix\Main\EventManager::getInstance();
	$eventManager->registerEventHandlerCompatible(
		'main', 'OnAdminContextMenuShow'
		,'shef.options'
		,'\Shef\Options\Integration\Update\Events'
		,"onAdminContextMenuShow"
	);
// events stop ////

//*/

class Events
	extends \Shef\Options\Integration\AEvents
{
	public static function onAdminContextMenuShow(?array &$items = [])
	{
		if (
			$_SERVER['REQUEST_METHOD'] == 'GET'
			&& $GLOBALS['APPLICATION']->GetCurPage() === '/bitrix/admin/update_system.php'
		)
		{
			$items[] = array(
				"TEXT" => "Правки ядра",
				"LINK" => "javascript:window.open('/local/admin/utils/has-change-files.php', '_blank').focus()",
				"TITLE" => "",
				"ICON" => "adm-btn",
				"SORT" => 0
			);
			\Bitrix\Main\UI\Extension::load("ui.dialogs.messagebox");
?><script>
BX.ready(function(){
	let updateBtn = BX('install_updates_button');
	if(!!updateBtn)
	{
		updateBtn.onclick = function(){
			BX.UI.Dialogs.MessageBox.confirm(
				"Перед обнвлением нужно <strong style='color: red;'>проверить</strong> что все правки зафиксированы." +
				"<br />После установки обновлений нужно <strong style='color: red;'>восстановить</strong> правки." +
                "<br /><br />Открыть отчет <a href='/local/admin/utils/has-change-files.php' target='_blank'>Правки ядра</a>"
				, "ВАЖНО"
				,(messageBox, button, event) =>
				{
					button.context.close();
					InstallUpdates();
				}
				,"Установить обновления"
				,(messageBox, button, event) =>
				{
					button.context.close();
				}
			);
		};
	}
	let updateSelBtn = BX('install_updates_sel_button');
	if(!!updateSelBtn)
	{
		updateSelBtn.onclick = function(){
			BX.UI.Dialogs.MessageBox.confirm(
				"Перед обнвлением нужно <strong style='color: red;'>проверить</strong> что все правки зафиксированы." +
				"<br />После установки обновлений нужно <strong style='color: red;'>восстановить</strong> правки." +
				"<br /><br />Открыть отчет <a href='/local/admin/utils/has-change-files.php' target='_blank'>Правки ядра</a>"
				, "ВАЖНО"
				,(messageBox, button, event) =>
				{
					button.context.close();
					InstallUpdatesSel();
				}
				,"Установить обновления"
				,(messageBox, button, event) =>
				{
					button.context.close();
				}
			);
		};
	}
});

</script><?php
		}
	}
}