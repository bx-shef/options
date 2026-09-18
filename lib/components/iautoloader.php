<?php declare(strict_types=1);

namespace Shef\Options\Components;

/**
 * Интерфейс для подключения в компоненте механизма автозагрузки
 */
interface IAutoloader
{
	public function initAutoloader(): bool;
}