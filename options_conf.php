<?php declare(strict_types=1);

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Options;

/**
 * Опции для страницы настроек
 *
 * языковой файл options.php
 *
 * Tab(prefix)->Option(code) ~> код свойства: prefix_code
 */

$response = ShOptionsConfig::getInstance(
	moduleId: 'shef.options',
	indexDoc: 'README.md'
);
if(!$response->isSuccess())
{
	return $response;
}

/** @var ShOptionsConfig $options */
$options = $response->getData()['OPTIONS'];

$options->addTab(
	(new Options\Tab('DEF'))
		->setName(Loc::getMessage($options->moduleId.'_TAB_DEF_NAME'))
		->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_TITLE'))
		->addOption(
			(new Options\RowInfo('WARNING_CATALOG'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_WARNING_CATALOG'))
				->setType(Options\TypeUIAlert::Warning)
		)
		->addOption(
			(new Options\Users('systemuserid'))
				->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_systemuserid'))
				->setDescription(Loc::getMessage($options->moduleId.'_TAB_DEF_systemuserid_descr'))
				->initSimpleUserList([
					'=GROUPS.GROUP_ID' => 1
				])->setShowRows(1)
		)
);

return $options->get();