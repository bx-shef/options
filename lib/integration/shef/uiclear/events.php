<?php
declare(strict_types=1);

namespace Shef\Options\Integration\Shef\UiClear;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\UI\Extension;
use Bitrix\Main\Localization\Loc;
use Shef\Options\TraitList;
use Shef\Options\Main\Constants;

Loc::loadMessages(__FILE__);

/*/
//title: Test::onAdminContextMenuShow ////
	\Bitrix\Main\Loader::includeModule('shef.options');
	$items = [];
	\Shef\Options\Integration\Shef\UiClear\Events::onAdminContextMenuShow($items);
	_pr([$items]);
//*/

/**
 * Class Events
 *
 * Правки ядра
 *
 */
class Events
{
	use TraitList\Events;
	use TraitList\EventResponse;

	protected static function getModuleId(): string
	{
		return Constants::MODULE_ID;
	}

	public static function onBitrixMenuExtInitTopPanelUserMenu(\Bitrix\Main\Event $event): EventResult
	{
		return static::returnMainEventSuccess(
			(new Result())->setData([
				'items' => [
					[
						'TITLE' => Loc::getMessage(Constants::MODULE_ID.'_TOOLS_HAS_CHANGE_BTN_TITLE'),
						'PUBLIC_URL' => Manager::getFileUrl(),
						'IS_FOR_ADMIN' => true
					]
				]
			]),
			__FUNCTION__
		);
	}

	public static function onAdminContextMenuShow(?array &$items = []): void
	{
		$server = \Bitrix\Main\Application::getInstance()->getContext()->getServer();
		$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

		if (
			$server->getRequestMethod() === 'GET'
			&& $request->getRequestedPage() === '/bitrix/admin/update_system.php'
		)
		{
			Extension::load("ui.dialogs.messagebox");

			$items[] = array(
				"TEXT" => Loc::getMessage(Constants::MODULE_ID.'_TOOLS_HAS_CHANGE_BTN_TITLE'),
				"LINK" => "javascript:window.open('".Manager::getFileUrl()."', '_blank').focus()",
				"TITLE" => "",
				"ICON" => "adm-btn",
				"SORT" => 0
			);

			$confirm = new \stdClass();
			$confirm->title = Loc::getMessage(Constants::MODULE_ID.'_TOOLS_HAS_CHANGE_UPDATE_CONFIRM_TITLE');
			$confirm->message = Loc::getMessage(Constants::MODULE_ID.'_TOOLS_HAS_CHANGE_UPDATE_CONFIRM_MESSAGE', [
				'#DANGER_COLOR#' => '#F1416C',
				'#URL#' => Manager::getFileUrl()
			]);
			$confirm->btnSuccess = Loc::getMessage(Constants::MODULE_ID.'_TOOLS_HAS_CHANGE_UPDATE_CONFIRM_BTN_ACTION_SUCCESS');


			ob_start();
			?>
<script>
BX.ready(() => {
	let functionClick = function(event){

		let typeClick = 'global';
		if(event.target.dataset.typeClick === 'selected')
		{
			typeClick = 'selected';
		}
		BX.UI.Dialogs.MessageBox.confirm(
			"<?=$confirm->message?>",
			"<?=$confirm->title?>",
			(messageBox, button) =>
			{
				button.context.close();

				if(typeClick === 'selected')
				{
					InstallUpdatesSel();
				}
				else
				{
					InstallUpdates();
				}
			},
			"<?=$confirm->btnSuccess?>",
			(messageBox, button) =>
			{
				button.context.close();
			}
		);
	};

	let updateBtn = BX('install_updates_button');
	if(!!updateBtn)
	{
		updateBtn.dataset.typeClick = 'global';
		updateBtn.onclick = functionClick.bind(this);
	}

	let updateSelBtn = BX('install_updates_sel_button');
	if(!!updateSelBtn)
	{
		updateSelBtn.dataset.typeClick = 'selected';
		updateSelBtn.onclick = functionClick.bind(this);
	}
});
</script>
			<?php
			$stcript = ob_get_contents();
			ob_end_clean();
			Asset::getInstance()->addString($stcript);
		}
	}
	}