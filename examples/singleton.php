<?php declare(strict_types=1);

/**
 * Наследование синглетона.
 *
 * ЦЕЛЬ
 *   Показать, как унаследовать \Shef\Options\Options\Singleton: что обязан
 *   сделать наследник, что базовый класс делает за него и какие обходные пути
 *   он закрывает.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Когда в пределах ОДНОГО запроса нужен общий объект с состоянием: реестр
 *   уже обработанных сущностей, разобранные настройки, менеджер ресурсов.
 *   В модуле так сделаны Options\Config и Main\TempFile\Manager.
 *   Не применять как кеш между запросами — состояние умирает вместе с хитом.
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: singleton», код возврата 0.
 *   По сути: два вызова getInstance() дают один и тот же объект, у соседнего
 *   наследника — свой, а new/clone/unserialize снаружи недоступны.
 *
 * ЗАПУСК
 *   php examples/singleton.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/singleton.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Ничем: пример работает только с памятью процесса.
 */

require_once __DIR__.'/_bootstrap.php';

title('Наследование синглетона');

$load('lib/options/singleton.php');

use Shef\Options\Options\Singleton;

// region Наследник ////
/**
 * Реестр обработанных сделок: считаем в пределах запроса, чтобы не
 * пересчитывать в каждом хендлере.
 */
final class DealRegistry
	extends Singleton
{
	/** @var int[] */
	private array $ids = [];

	/**
	 * Конструктор остаётся protected — иначе смысл теряется: снаружи
	 * появится второй способ создать объект.
	 *
	 * parent::__construct() обязателен. Сейчас базовый конструктор пуст, но
	 * наследники в модуле вешают на него своё (см. TempFile\Manager —
	 * регистрацию shutdown-функции), и забытый вызов ломает не наследника,
	 * а базовый класс.
	 */
	protected function __construct()
	{
		parent::__construct();
	}

	public function add(int $id): static
	{
		$this->ids[$id] = $id;
		return $this;
	}

	/** @return int[] */
	public function getIds(): array
	{
		return array_values($this->ids);
	}
}

/** Второй наследник — чтобы показать, что экземпляры не общие. */
final class OrderRegistry
	extends Singleton
{
	public int $count = 0;
}
// endregion ////

step('Экземпляр один на класс');

DealRegistry::getInstance()->add(10);
DealRegistry::getInstance()->add(20);
DealRegistry::getInstance()->add(10);

check('состояние накопилось в одном объекте', DealRegistry::getInstance()->getIds(), [10, 20]);
check('это буквально тот же объект',
	DealRegistry::getInstance() === DealRegistry::getInstance(), true);

step('У каждого наследника свой экземпляр');

OrderRegistry::getInstance()->count = 5;

check('соседний наследник не задет', DealRegistry::getInstance()->getIds(), [10, 20]);
check('и у него своё состояние', OrderRegistry::getInstance()->count, 5);

step('Тип возврата — static, а не Singleton');

// getInstance(): static, поэтому переопределять его в наследнике не нужно:
// и анализатор, и IDE видят здесь DealRegistry.
check('класс экземпляра', get_class(DealRegistry::getInstance()), DealRegistry::class);
check('экземпляр наследника — это Singleton',
	DealRegistry::getInstance() instanceof Singleton, true);

step('Обходные пути закрыты');

// new DealRegistry() не соберётся: конструктор protected. Проверяем это
// рефлексией, потому что сам вызов — ошибка уровня компиляции.
$constructor = (new ReflectionClass(DealRegistry::class))->getConstructor();
check('конструктор недоступен снаружи', $constructor?->isPublic(), false);

$clone = (new ReflectionClass(DealRegistry::class))->getMethod('__clone');
check('клонирование недоступно снаружи', $clone->isPublic(), false);

try
{
	// Десериализация обошла бы getInstance() и дала второй экземпляр.
	unserialize(serialize(DealRegistry::getInstance()));
	check('десериализация запрещена', false, true);
}
catch(Throwable $throwable)
{
	check('десериализация бросает исключение', $throwable::class, Exception::class);
}

step('Чего синглетон НЕ делает');

note('Состояние живёт до конца запроса и между хитами не переносится.');
note('Это не кеш и не хранилище: следующий запрос начнёт с пустого реестра.');
note('Для значений между запросами — Bitrix\Main\Config\Option или свой кеш.');

done('singleton');
