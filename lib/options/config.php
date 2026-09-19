<?php declare(strict_types=1);

namespace Shef\Options\Options;

/**
 * Реестр строковых настроек на время запроса: синглетон со стопкой состояний.
 *
 * Живёт в памяти процесса и никуда не сохраняется — это не замена
 * \Bitrix\Main\Config\Option, а место, куда модуль складывает разобранные
 * значения, чтобы не разбирать их заново в каждом вызове.
 *
 * push() и restore() дают временную подмену: сложили текущее состояние в
 * стопку, поработали в изменённом, вернули как было. Нужно там, где кусок
 * кода обязан отработать в других настройках, а соседний код об этом знать
 * не должен.
 *
 * @see \Shef\Options\Options\Singleton
 *
 * <code>
 * $config = Config::getInstance();
 * $config->setValue('mode', 'import');
 *
 * $config->push();
 * $config->setValue('mode', 'test');
 * // ... код, которому нужен режим test
 * $config->restore();
 *
 * $config->getValue('mode');        // import
 * $config->getValue('нет', 'нету'); // нету
 * </code>
 */
class Config
	extends Singleton
{
	/** @var array<string, string> текущее состояние */
	protected array $hashmap = [];

	/** @var array<int, array<string, string>> стопка состояний для push/restore */
	protected array $cache = [];

	/**
	 * Задано ли значение.
	 *
	 * Отдельный метод, потому что «ключа нет» и «значение пустое» — разные
	 * вещи, и различить их после getValue() уже нельзя.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function hasValue(string $key): bool
	{
		return array_key_exists($key, $this->hashmap);
	}

	/**
	 * Значение по ключу либо умолчание.
	 *
	 * Раньше здесь стояло `return $this->hashmap[$key];` без проверки: на
	 * незаданном ключе PHP 8 писал в лог «Undefined array key», а возврат
	 * null из метода с типом string валил вызов TypeError'ом. То есть
	 * опечатка в имени настройки роняла не настройку, а запрос целиком.
	 *
	 * @param string $key
	 * @param string $default что вернуть, если ключа нет
	 *
	 * @return string
	 */
	public function getValue(string $key, string $default = ''): string
	{
		return $this->hashmap[$key] ?? $default;
	}

	/**
	 * @param string $key
	 * @param string $value
	 *
	 * @return static для цепочки вызовов
	 */
	public function setValue(string $key, string $value): static
	{
		$this->hashmap[$key] = $value;
		return $this;
	}

	/**
	 * @return array<string, string> текущее состояние целиком
	 */
	public function toArray(): array
	{
		return $this->hashmap;
	}

	/**
	 * Запоминает текущее состояние. Кладётся в стопку, то есть вложенные
	 * push() допустимы — restore() снимет последний.
	 *
	 * @return void
	 */
	public function push(): void
	{
		$this->cache[] = $this->hashmap;
	}

	/**
	 * Возвращает последнее запомненное состояние. Без пары push() не делает
	 * ничего — стопка пуста, восстанавливать нечего.
	 *
	 * @return void
	 */
	public function restore(): void
	{
		if(!empty($this->cache))
		{
			$this->hashmap = array_pop($this->cache);
		}
	}
}