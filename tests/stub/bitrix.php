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

namespace Bitrix\Main\Config
{
	if(!class_exists(Option::class))
	{
		/**
		 * Настройки модуля. Важно не как они хранятся, а что приходит обратно:
		 * значение опции — всегда строка из формы, и разбирать её приходится
		 * коду модуля.
		 */
		class Option
		{
			/** @var array<string, array<string, mixed>> */
			public static array $values = [];

			public static function set(string $moduleId, string $name, mixed $value): void
			{
				static::$values[$moduleId][$name] = $value;
			}

			public static function forget(string $moduleId, string $name): void
			{
				unset(static::$values[$moduleId][$name]);
			}

			public static function get(string $moduleId, string $name, mixed $default = '', mixed $siteId = false): mixed
			{
				return static::$values[$moduleId][$name] ?? $default;
			}
		}
	}
}

namespace Bitrix\Main
{
	if(!class_exists(Error::class))
	{
		/**
		 * Result и Error — то, чем модуль возвращает «получилось / не
		 * получилось» вместе с данными. Поведение простое, но важное:
		 * isSuccess() определяется НАЛИЧИЕМ ошибок, а не отдельным флагом.
		 */
		class Error
		{
			public function __construct(
				private readonly string $message = '',
				private readonly int|string $code = 0,
				private readonly mixed $customData = null
			) {}

			public function getMessage(): string
			{
				return $this->message;
			}

			public function getCode(): int|string
			{
				return $this->code;
			}

			public function getCustomData(): mixed
			{
				return $this->customData;
			}

			public function __toString(): string
			{
				return $this->message;
			}
		}

		class Result
		{
			/** @var Error[] */
			protected array $errors = [];
			protected array $data = [];

			public function isSuccess(): bool
			{
				return empty($this->errors);
			}

			public function addError(Error $error): static
			{
				$this->errors[] = $error;
				return $this;
			}

			/** @param Error[] $errors */
			public function addErrors(array $errors): static
			{
				foreach($errors as $error)
				{
					$this->errors[] = $error;
				}

				return $this;
			}

			/** @return Error[] */
			public function getErrors(): array
			{
				return $this->errors;
			}

			/** @return string[] */
			public function getErrorMessages(): array
			{
				return array_map(
					static fn(Error $error): string => $error->getMessage(),
					$this->errors
				);
			}

			/**
			 * Данные кладутся как есть: ссылка внутри массива остаётся
			 * ссылкой, и это не мелочь — Pid::removeByGroup() наполняет
			 * список УЖЕ ПОСЛЕ вызова setData().
			 */
			public function setData(array $data): static
			{
				$this->data = $data;
				return $this;
			}

			public function getData(): array
			{
				return $this->data;
			}
		}
	}
}

namespace Bitrix\Main\IO
{
	if(!class_exists(Path::class))
	{
		/**
		 * Файловый слой ядра ровно в том объёме, в котором его зовут классы
		 * модуля: путь, файл, каталог. Работает с настоящей файловой системой —
		 * иначе проверять было бы нечего.
		 */
		class Path
		{
			public static function combine(string ...$parts): string
			{
				return implode('/', array_map(
					static fn(string $part): string => trim($part, '/'),
					array_filter($parts, static fn(string $part): bool => '' !== $part)
				));
			}
		}

		class Directory
		{
			public function __construct(private readonly string $path) {}

			public function getPath(): string
			{
				return $this->path;
			}

			public function isExists(): bool
			{
				return is_dir($this->path);
			}
		}

		class File
		{
			public function __construct(private readonly string $path) {}

			public function getPath(): string
			{
				return $this->path;
			}

			public function getName(): string
			{
				return basename($this->path);
			}

			public function getExtension(): string
			{
				return pathinfo($this->path, PATHINFO_EXTENSION);
			}

			public function getDirectory(): Directory
			{
				return new Directory(dirname($this->path));
			}

			public function isExists(): bool
			{
				return is_file($this->path);
			}

			public function getContents(): string
			{
				return (string)file_get_contents($this->path);
			}

			public function putContents(mixed $data): int|false
			{
				return file_put_contents($this->path, (string)$data);
			}

			public function delete(): bool
			{
				return is_file($this->path) && unlink($this->path);
			}
		}
	}
}

namespace Bitrix\Main\Security
{
	if(!class_exists(Random::class))
	{
		class Random
		{
			public static function getString(int $length = 32): string
			{
				return substr(bin2hex(random_bytes((int)ceil($length / 2))), 0, $length);
			}
		}
	}
}

namespace Bitrix\Main
{
	if(!class_exists(Application::class))
	{
		class Application
		{
			public static string $documentRoot = '';

			public static function getDocumentRoot(): string
			{
				return static::$documentRoot;
			}
		}
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
	if(!defined('BX_DIR_PERMISSIONS'))
	{
		define('BX_DIR_PERMISSIONS', 0777);
	}

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
