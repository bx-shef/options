<?php declare(strict_types=1);

/**
 * Настраиваемые параметры модуля
 *
 * * requireModules -> обязательные модулей
 * * requirePhpExt -> обязательные расширения PHP
 * * registerAutoLoadClasses -> авто подгрузка классов
 * * registerNamespace -> авто подгрузка Namespace
 * * options -> опции устанавливаемые через окружение
 * * installEvents -> события для установки
 * * installDir -> пути установки файлов
 * * controllers -> контроллеры для ajax
 * * ui.entity-selector -> провайдер для диалога выбора сущностей
 * * intranet.customSection -> указывает провайдер страниц левого меню Если нужно использовать из другого модуля - то в installLeftMenu[] указываем moduleId
 * * installLeftMenu -> разделы и страницы в левом меню
 *
 * @memo installLeftMenu[].pages[].settingsRow не серилизовать.
 * @memo installLeftMenu[].code и installLeftMenu[].pages[].code писать без разделителей
 * @memo installLeftMenu[].pages[].settingsRow первый параметр компонет. Остальное смотреть в контроллере intranet.customSection
 *
 */

return [
	'requireModules' => [
		'value' => [],
		'readonly' => true,
	],
	'requirePhpExt' => [
		'value' => [],
		'readonly' => true,
	],
	'registerAutoLoadClasses' => [
		'value' => [],
		'readonly' => true,
	],
	'registerNamespace' => [
		'value' => [],
		'readonly' => true,
	],
	'options' => [
		'value' => [],
		'readonly' => true,
	],
	'installEvents' => [
		'value' => [],
		'readonly' => true,
	],
	'installDir' => [
		'value' => [
			[
				'type' => 'css',
				'from' => '/install/css',
				'to' => '/bitrix/css',
				'customPathUnInstall' => [],
				'isNeedUnInstall' => true,
			],
		],
		'readonly' => true,
	],
	'controllers' => [
		'value' => [
			'namespaces' => [],
		],
		'readonly' => true,
	]
];