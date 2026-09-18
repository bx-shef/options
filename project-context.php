<?php declare(strict_types=1);

if(class_exists('ShComposerContext'))
{
	return;
}

/**
 * Знает существует ли Composer и некоторые его подробности
 *
 * @see .settings.php
 */
class ShComposerContext
{
	private null|string $composerVendorPath = null;
	private null|string $composerJsonFile = null;
	
	private static array $instances = [];
	
	protected function __construct()
	{
		// region Composer ////
		$this->initComposerJsonFile();
		$this->readComposerJsonFile();
		// endregion ////
	}
	
	public static function getInstance(): self
	{
		$subclass = static::class;
		if(!isset(self::$instances[$subclass]))
		{
			self::$instances[$subclass] = new static();
		}
		
		return self::$instances[$subclass];
	}
	
	// region Composer ////
	/**
	 * Пытается понять есть ли composer
	 * Смотрит относительно настройек Битрикс
	 *
	 * @return void
	 */
	private function initComposerJsonFile(): void
	{
		$composerSettings = \Bitrix\Main\Config\Configuration::getValue('composer');
		if(!empty($composerSettings['config_path']))
		{
			$jsonPath = $composerSettings['config_path'];
			$jsonPath = ($jsonPath[0] === '/')
				? $jsonPath // absolute ////
				: realpath($_SERVER["DOCUMENT_ROOT"].'/'.$jsonPath); // relative ////
			
			if(!empty($jsonPath))
			{
				$this->composerJsonFile = $jsonPath;
			}
			
			unset($jsonPath);
		}
		unset($composerSettings);
	}
	
	/**
	 * Пытается из настроек composer выбрать vendor-dir
	 *
	 * @return void
	 */
	private function readComposerJsonFile(): void
	{
		if(
			empty($this->composerJsonFile)
			|| !file_exists($this->composerJsonFile)
			|| !is_readable($this->composerJsonFile)
		)
		{
			return;
		}
		
		// default vendor path has the same parent dir as composer.json has ////
		$this->composerVendorPath = dirname($this->composerJsonFile).'/vendor';
		
		$jsonContent = json_decode(
			file_get_contents($this->composerJsonFile),
			true
		);
		
		if(isset($jsonContent['config']['vendor-dir']))
		{
			$realPath = realpath(
				dirname($this->composerJsonFile)
				.DIRECTORY_SEPARATOR
				.$jsonContent['config']['vendor-dir']
			);
			
			if($realPath === false)
			{
				throw new LogicException(
					sprintf(
						'Failed to load vendor libs from %s, path \'%s\' is not readable',
						$this->composerJsonFile,
						$jsonContent['config']['vendor-dir']
					));
			}
			
			$this->composerVendorPath = &$realPath;
			
			unset($realPath);
		}
		unset($jsonContent);
	}
	
	/**
	 * Провверяет используется ли композер на проекте
	 * @return bool
	 */
	public function isProjectUseComposer(): bool
	{
		return null !== $this->composerVendorPath;
	}
	
	/**
	 * Возвращает путь к composer папке vendor
	 * @return string
	 */
	public function getProjectComposerPathVendor(): string
	{
		return (string)$this->composerVendorPath;
	}
	
	public function requireProjectComposerAutoLoad(): void
	{
		if(!$this->isProjectUseComposer())
		{
			return;
		}
		
		require $this->getProjectComposerPathVendor().'/autoload.php';
	}
	// endregion ////
}

/**
 * Класс сборки настроек
 * Был введен для стандартизации в .settings.php блока registerNamespace
 *
 * @see .settings.php
 */
class ShProjectContext
{
	/** @var AShProjectNamespaceRow[]  */
	private array $collectionNamespace = [];
	public ShComposerContext $composerContext;
	
	function __construct(
		public string $modulePath
	)
	{
		$this->composerContext = ShComposerContext::getInstance();
	}
	
	// region Namespace ////
	/**
	 * Возвращает данные для registerNamespace
	 * @return array
	 */
	public function getNamespaceList(): array
	{
		return array_merge(...array_map(
			static function(AShProjectNamespaceRow $value): array
			{
				return $value->get();
			},
			$this->collectionNamespace
		));
	}

	/**
	 * Добавляет Namespace в коллекцию (добавляет контекст)
	 *
	 * @param AShProjectNamespaceRow $namespaceRow
	 *
	 * @return $this
	 */
	public function addNamespace(AShProjectNamespaceRow $namespaceRow): static
	{
		$this->collectionNamespace[] = $namespaceRow->setContext($this);
		return $this;
	}
	// endregion ////
}

/**
 * Адстракция для хранения Namespace
 */
abstract class AShProjectNamespaceRow
{
	private null|ShProjectContext $context = null;
	
	public function __construct(
		public readonly string $namespace,
		public readonly string $path,
		null|ShProjectContext $context = null,
	)
	{
		if(null !== $context)
		{
			$this->setContext($context);
		}
	}
	
	public function setContext(ShProjectContext $context): static
	{
		$this->context = $context;
		return $this;
	}
	
	public function getContext(): ShProjectContext
	{
		if(null === $this->context)
		{
			throw new LogicException('Context not set');
		}
		return $this->context;
	}
	
	/**
	 * Возвращает массив [namespace => path]
	 * Если не нужно подключать - то стоит вернуть пустой массив
	 *
	 * Используется в [registerNamespace]
	 *
	 * @return string[]
	 */
	abstract public function get(): array;
}

/**
 * Namespace для классов своего модуля
 */
class ShProjectNamespaceSelf
	extends AShProjectNamespaceRow
{
	public function get(): array
	{
		return [
			$this->namespace => $this->getContext()->modulePath.$this->path
		];
	}
}

/**
 * Namespace для классов Composer
 */
class ShProjectNamespaceComposer
	extends AShProjectNamespaceRow
{
	/**
	 * @inheritDoc
	 */
	public function get(): array
	{
		if(!$this->getContext()->composerContext->isProjectUseComposer())
		{
			return [
				$this->namespace => $this->getContext()->modulePath.'/vendor'.$this->path
			];
		}
		
		/**
		 * Если папка/файл существует в composer/vendor то не будем добавлять его в автозагрзку
		 * относительно текущего модуля
		 *
		 * @memo file_exists -> test DIR && FILE
		 */
		if(file_exists($this->getContext()->composerContext->getProjectComposerPathVendor().$this->path))
		{
			return [];
		}
		
		return[
			$this->namespace => $this->getContext()->modulePath.'/vendor'.$this->path
		];
	}
}
