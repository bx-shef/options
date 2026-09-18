<?php

namespace Shef\Options\Main\Options;

/**
 * Перечисление типов сообщений
 *
 * Используется для определения как выводить сообщение
 */
enum TypeUIAlert: string
{
	case Error = 'errortext';
	case Note = 'notetext';
	case Warning = 'warningtext';
}
