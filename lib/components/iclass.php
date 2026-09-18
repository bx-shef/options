<?php declare(strict_types=1);

namespace Shef\Options\Components;

/**
 * Интерфейс для указания что компонент содержит файл class.php
 */
interface IClass
{
	public static function getSelfClassWithNamespace(): string;
}