<?php

/**
 * Заглушки ядра Битрикса для тестов.
 *
 * Ровно столько, сколько нужно, чтобы подключить настоящие классы модуля:
 * исключения, пара типов и CUtil. Логику модуля здесь не повторяем — иначе
 * тест проверял бы заглушку, а не код.
 *
 * Блочный синтаксис namespace: в одном файле нужно объявить классы сразу в
 * нескольких пространствах имён.
 */

namespace Bitrix\Main
{
	if(!class_exists(SystemException::class))
	{
		class SystemException extends \Exception {}
		class ArgumentException extends SystemException {}
		class ArgumentNullException extends ArgumentException {}
		class ObjectException extends SystemException {}
	}
}

namespace Bitrix\Main\Type
{
	if(!class_exists(Date::class))
	{
		class Date
		{
			public function __construct(
				public readonly string $value = '',
				public readonly string $format = ''
			) {}

			public function toString(): string
			{
				return $this->value;
			}
		}

		class DateTime extends Date {}
	}
}

namespace Bitrix\Main\Type\Contract
{
	if(!interface_exists(Arrayable::class))
	{
		interface Arrayable
		{
			public function toArray(): array;
		}

		interface Jsonable
		{
			public function toJson(int $options = 0);
		}
	}
}

namespace
{
	if(!defined('BX_UTF_PCRE_MODIFIER'))
	{
		// В UTF-режиме Битрикс подставляет 'u'.
		define('BX_UTF_PCRE_MODIFIER', 'u');
	}

	if(!class_exists('CUtil'))
	{
		/**
		 * Транслитерация ядра. Важно не как она переводит, а с чем её зовут.
		 */
		class CUtil
		{
			public static array $lastCall = [];

			public static function translit(string $value, string $lang, array $params): string
			{
				static::$lastCall = ['value' => $value, 'lang' => $lang, 'params' => $params];
				return $value;
			}
		}
	}
}
