<?php
declare(strict_types=1);

// Файл выполняется из include.php, то есть в момент подключения самого модуля.
// Полагаться здесь на автозагрузку классов модуля нельзя — она к этому моменту
// может быть ещё не в силе, и упадёт весь модуль, а не одна кнопка. Поэтому
// класс подключается явно, ровно как include.php подключает autoload.php.
// require_once сверяет разрешённый путь, так что повторная загрузка
// автозагрузчиком позже — холостая.
require_once __DIR__.'/lib/main/constants.php';

use Shef\Options\Main\Constants;

// region Css ////
// Путь берём из Constants, а не пишем строкой: он же определяет, куда
// установщик разложит файлы. @see Constants::getPublicCssDir
\CJSCore::RegisterExt('shef-options-admin', [
	'css' => Constants::getPublicCssDir().'/admin-options.css',
	'skip_core' => true
]);
// endregion ////
