<?php declare(strict_types=1);

namespace Shef\Options\Components;

/**
 * Интерфейс для указания что компонент содержит файл ajax.php
 */
interface IAjax
{
	public static function getSelfAjaxClassWithNamespace(): string;
}