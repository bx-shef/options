<?php declare(strict_types=1);

namespace Shef\Options\Main\Options;

/**
 * Перечисление типов сообщений
 *
 * Используется для определения как выводить сообщение
 */
enum TypeUIAlert: string
{
	case Error = 'ui-alert-danger';
	case Note = 'ui-alert-default';
	case Warning = 'ui-alert-warning';
}
