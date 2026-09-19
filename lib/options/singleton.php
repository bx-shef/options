<?php declare(strict_types=1);

namespace Shef\Options\Options;

/**
 * Базовый синглетон: один экземпляр на класс, на время запроса.
 *
 * Наследуется так:
 *
 * <code>
 * class Registry extends Singleton
 * {
 *     protected array $items = [];
 *
 *     // Конструктор остаётся protected — иначе смысл теряется:
 *     // снаружи появится второй способ создать объект.
 *     protected function __construct()
 *     {
 *         parent::__construct();
 *         $this->items = ['по умолчанию'];
 *     }
 *
 *     public function add(string $item): static
 *     {
 *         $this->items[] = $item;
 *         return $this;
 *     }
 * }
 *
 * Registry::getInstance()->add('первый');
 * Registry::getInstance()->add('второй');   // тот же объект
 * </code>
 *
 * Что здесь важно знать заранее:
 *
 * * **Экземпляры хранятся по имени класса.** `Registry::getInstance()` и
 *   `Manager::getInstance()` — разные объекты, наследник наследника получит
 *   свой. Список общий на всю иерархию и приватный: подменить его из
 *   наследника нельзя, и это защита от «почти синглетона».
 * * **`getInstance()` возвращает `static`.** То есть наследнику не нужно
 *   переопределять метод ради типа: `Registry::getInstance()` для анализатора
 *   и для IDE — это `Registry`, а не `Singleton`.
 * * **`parent::__construct()` в наследнике обязателен.** Сейчас базовый
 *   конструктор пуст, но `Manager` на нём держит регистрацию
 *   `register_shutdown_function` — наследник, забывший вызов, ломает не себя,
 *   а базовый класс. @see \Shef\Options\Main\TempFile\Manager
 * * **Копирование и десериализация закрыты.** `__clone()` protected, поэтому
 *   `clone` снаружи не соберётся; `__wakeup()` бросает исключение, поэтому
 *   объект нельзя воскресить из строки в обход `getInstance()`.
 * * **Состояние живёт до конца запроса** и между запросами не переносится.
 *   Синглетон здесь — способ не пересчитывать одно и то же в пределах хита, а
 *   не кеш и не хранилище.
 *
 * @see Config реестр значений на этой основе
 */
class Singleton
{
	/**
	 * @var array<class-string, static> по одному экземпляру на класс
	 *
	 * Приватное и на базовом классе: общее хранилище для всей иерархии,
	 * недоступное наследникам напрямую.
	 */
	private static array $instances = [];

	protected function __construct(){}

	protected function __clone(){}

	/**
	 * Синглетон нельзя воскресить из строки: это обошло бы getInstance() и
	 * дало бы второй экземпляр.
	 *
	 * @throws \Exception всегда
	 */
	public function __wakeup()
	{
		throw new \Exception("Cannot unserialize config");
	}

	/**
	 * Экземпляр вызванного класса, создаётся при первом обращении.
	 *
	 * @return static
	 */
	public static function getInstance(): static
	{
		$subclass = static::class;
		if(!isset(self::$instances[$subclass]))
		{
			self::$instances[$subclass] = new static();
		}
		
		return self::$instances[$subclass];
	}
}