<?php declare(strict_types=1);

namespace Shef\Options\Components\Trait;

use Bitrix\Main\Application;

/**
 * Trait для подключения в компоненте механизма автозагрузки
 */
trait AutoloaderTrait
{
	abstract public function getPath();

	abstract public static function getSelfClass(): string;
	
	abstract public static function getSelfNamespace(): string;
	
	abstract public static function getSelfBaseName(): string;
	
	abstract public static function getSelfAjaxClass(): string;
	
	/**
	 * @return bool
	 */
	public function initAutoloader(): bool
	{
		$path = Application::getDocumentRoot().$this->getPath();
		return spl_autoload_register(static function(string $class)
			use ($path)
		{
			$comeClass = $class;
			
			if(stripos($comeClass, static::getSelfNamespace()) === false)
			{
				return;
			}
			
			$class = str_replace(
				static::getSelfNamespace().'\\',
				'',
				$class
			);
			
			$class = str_replace(
				[
					'\\',
					static::getSelfAjaxClass()
				],
				[
					DIRECTORY_SEPARATOR,
					'ajax'
				],
				$class
			);
			
			$class = mb_strtolower($class);
			$filename = $path.DIRECTORY_SEPARATOR.$class.'.php';
			
			if(file_exists($filename))
			{
				include $filename;
			}
		});
	}
}