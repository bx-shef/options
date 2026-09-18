<?php
declare(strict_types=1);

use Shef\Options\Main\Constants;

// region Css ////
// Путь берём из Constants, а не пишем строкой: он же определяет, куда
// установщик разложит файлы. @see Constants::getPublicCssDir
\CJSCore::RegisterExt('shef-options-admin', [
	'css' => Constants::getPublicCssDir().'/admin-options.css',
	'skip_core' => true
]);
// endregion ////
