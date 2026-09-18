<?php declare(strict_types=1);

namespace Shef\Options\Main\TempFile;

use LogicException;
use Bitrix\Main\Application;
use Bitrix\Main\Config;
use Bitrix\Main\IO\InvalidPathException;
use Bitrix\Main\IO\Path;
use Bitrix\Main\Security;
use Shef\Options\Options\Singleton;

/**
 * Класс для хранения временных файлов
 * @see CTempFile
 */
class Manager
	extends Singleton
{
	const TempDir = 'SHEF_TEMP';
	const DirectorySeparator = '/';

	protected bool $isExitFunctionRegistered = false;
	protected array $files = [];
	
	protected function __construct()
	{
		parent::__construct();
		
		$this->registerShutdown();
	}
	
	/**
	 * Возвращает путь к временной папке
	 *
	 * @return string
	 */
	public function getAbsoluteRoot(): string
	{
		if(defined('BX_TEMPORARY_FILES_DIRECTORY'))
		{
			return rtrim(
				BX_TEMPORARY_FILES_DIRECTORY,
				static::DirectorySeparator
			);
		}
		else
		{
			return Path::combine(
				Application::getDocumentRoot(),
				(string)Config\Option::get(
					'main',
					'upload_dir',
					'upload'
				),
				'tmp'
			);
		}
	}
	
	public function addRegisterPath(
		string $tempPath,
		string $dir
	): void
	{
		if(!isset($this->files[$tempPath]))
		{
			$this->files[$tempPath] = [];
		}
		
		$this->files[$tempPath][] = $dir;
	}
	
	/**
	 * Возвращает временный файл
	 *
	 * @param string $fileName
	 * @return string
	 *
	 * @throws InvalidPathException
	 */
	public function getFileName(
		string $fileName
	): string
	{
		$dirName = $this->getAbsoluteRoot();
		
		$fileName = rel2abs(
			static::DirectorySeparator,
			static::DirectorySeparator.$fileName
		);
		
		$i = 0;

		while(true)
		{
			$i++;

			$dirAdd = '';
			if($fileName === static::DirectorySeparator)
			{
				$dirAdd = $this->getRandomString();
			}
			elseif($i < 25)
			{
				$dirAdd = substr($this->getRandomString(), 0, 3);
			}
			else
			{
				$dirAdd = $this->getRandomString();
			}

			$tempPath = $dirName.static::DirectorySeparator.$dirAdd.$fileName;

			if(!file_exists($tempPath))
			{
				$this->addRegisterPath(
					$tempPath,
					$dirName.static::DirectorySeparator.$dirAdd
				);
				
				/**
				 * @memo: function ends only here
				 */
				return $tempPath;
			}
		}
		
		throw new LogicException(
			'Not create tmp path'
		);
	}
	
	/**
	 * Возвращает временную папку.
	 *
	 * @param int $hoursToKeepFiles - указать сколько часов папка должна существовать
	 * @param array|string $subDir - указать поддериктории
	 *
	 * @return string
	 * @throws InvalidPathException
	 */
	public function getDirectoryName(
		int $hoursToKeepFiles = 0,
		array|string $subDir = ''
	): string
	{
		if($hoursToKeepFiles <= 0)
		{
			return $this->getFileName('');
		}
		
		$tempPath = '';
		
		if(empty($subDir))
		{
			$dirName = $this->getDirByHoursToKeep($hoursToKeepFiles);
			
			while(true)
			{
				$dirAdd = $this->getRandomString();
				$tempPath = $dirName.$dirAdd.static::DirectorySeparator;

				if(!file_exists($tempPath))
				{
					break;
				}
			}
		}
		else
		{
			/**
			 * @memo Fixed name during the session
			 */
			$localStorage = Application::getInstance()->getLocalSession('userSessionData');
			if(!isset($localStorage['shTempFileToken']))
			{
				$localStorage->set(
					'shTempFileToken',
					$this->getRandomString()
				);
			}
			$token = $localStorage->get('shTempFileToken');

			$subDir = implode(
				static::DirectorySeparator,
				(
					is_array($subDir)
					? $subDir
					: [$subDir, $token]
				)).static::DirectorySeparator;
			
			while(strpos($subDir, static::DirectorySeparator.static::DirectorySeparator) !== false)
			{
				$subDir = str_replace(static::DirectorySeparator.static::DirectorySeparator, static::DirectorySeparator, $subDir);
			}
			
			$isFound = false;
			for($i = $hoursToKeepFiles - 1; $i > 0; $i--)
			{
				$dirName = $this->getDirByHoursToKeep($i);

				$tempPath = $dirName.$subDir;
				
				if(
					file_exists($tempPath)
					&& is_dir($tempPath)
				)
				{
					$isFound = true;
					break;
				}
			}

			if(!$isFound)
			{
				$dirName = $this->getDirByHoursToKeep($hoursToKeepFiles);
				$tempPath = $dirName.$subDir;
			}
		}
		
		if(empty($tempPath))
		{
			throw new LogicException(
				'Not create tmp path'
			);
		}
		
		/**
		 * @memo: function ends only here
		 */
		return $tempPath;
	}
	
	/**
	 * Проверяет и создает папку если не существует
	 *
	 * @param string $path
	 * @return bool
	 */
	public function checkDirPath(
		string $path
	): bool
	{
		/**
		 * @memo: Уберем имя файла из пути
		 */
		if(mb_substr($path, -1) !== static::DirectorySeparator)
		{
			$p = mb_strrpos($path, static::DirectorySeparator);
			$path = mb_substr($path, 0, $p);
		}
		$path = rtrim($path, static::DirectorySeparator);
		
		if($path === '')
		{
			return true;
		}
		
		if(!file_exists($path))
		{
			return mkdir($path, BX_DIR_PERMISSIONS, true);
		}
		
		return is_dir($path);
	}
	
	/**
	 * Регистрирует функцию очистки временных файлов при завершении работы скрипта
	 *
	 * @return void
	 */
	protected function registerShutdown(): void
	{
		if(!$this->isExitFunctionRegistered)
		{
			$this->isExitFunctionRegistered = true;
			
			register_shutdown_function([
				$this, 'cleanUp'
			]);
		}
	}
	
	/**
	 * Возвращает путь с учетом времени жизни
	 *
	 * @param int $hoursToKeepFiles
	 * @return string
	 */
	protected function getDirByHoursToKeep(
		int $hoursToKeepFiles = 0
	): string
	{
		return sprintf(
			'%s'.static::DirectorySeparator.'%s-%s'.static::DirectorySeparator.'%s'.static::DirectorySeparator,
			$this->getAbsoluteRoot(),
			static::TempDir,
			date('Y-m-d'),
			date('H', time() + 3600 * $hoursToKeepFiles)
		);
	}
	
	/**
	 * Генерирует рандомную строку
	 *
	 * @param int $length
	 * @return string
	 */
	protected function getRandomString(
		int $length = 32
	): string
	{
		return Security\Random::getString($length);
	}
	
	// region cleanup ////
	/**
	 * PHP shutdown cleanup
	 * @return void
	 */
	public function cleanUp(): void
	{
		foreach($this->files as $tempPath => $tempDirList)
		{
			if(file_exists($tempPath))
			{
				/**
				 * @memo: clean a file from TempFile::getFileName('some.jpg');
				 */
				if(is_file($tempPath))
				{
					unlink($tempPath);
					foreach($tempDirList as $tempDir)
					{
						if(file_exists($tempDir))
						{
							@rmdir($tempDir);
						}
					}
				}
				
				/**
				 * @memo: clean whole temporary directory from TempFile::getFileName('');
				 */
				elseif(
					mb_substr($tempPath, -1) === static::DirectorySeparator
					&& strpos($tempPath, static::TempDir) === false
					&& is_dir($tempPath)
				)
				{
					static::absolutePathRecursiveDelete($tempPath);
				}
			}
			else
			{
				foreach($tempDirList as $tempDir)
				{
					if(file_exists($tempDir))
					{
						@rmdir($tempDir);
					}
				}
			}
		}

		/**
		 * @memo: clean directories with $hoursToKeepFiles > 0
		 */
		$dirName = $this->getAbsoluteRoot().static::DirectorySeparator;
		if(file_exists($dirName))
		{
			if($handle = opendir($dirName))
			{
				while(($dayFilesDir = readdir($handle)) !== false)
				{
					if(
						$dayFilesDir === '.'
						|| $dayFilesDir === '..'
					)
					{
						continue;
					}
					
					if(
						preg_match(
							"/^".static::TempDir."-(.*?)\$/",
							$dayFilesDir
						)
						&& is_dir($dirName.$dayFilesDir)
					)
					{
						static::processDirectory(
							$dirName,
							$dayFilesDir
						);
					}
				}
				closedir($handle);
			}
		}
	}
	
	/**
	 * Процесс очистки папки с учетом времени удаления
	 *
	 * @param string $dirName
	 * @param string $dayFilesDir
	 *
	 * @return void
	 */
	private static function processDirectory(
		string $dirName,
		string $dayFilesDir
	): void
	{
		$thisDayName = sprintf(
			'%s-%s',
			static::TempDir,
			date('Y-m-d')
		);
		
		if($dayFilesDir < $thisDayName)
		{
			static::absolutePathRecursiveDelete($dirName.$dayFilesDir);
		}
		elseif($dayFilesDir === $thisDayName)
		{
			if($hourHandle = opendir($dirName.$dayFilesDir))
			{
				$thisHourName = date('H');
				
				while(($hourFilesDir = readdir($hourHandle)) !== false)
				{
					if(
						$hourFilesDir === '.'
						|| $hourFilesDir === '..'
					)
					{
						continue;
					}
					
					if($hourFilesDir < $thisHourName)
					{
						static::absolutePathRecursiveDelete(
							$dirName.$dayFilesDir.static::DirectorySeparator.$hourFilesDir
						);
					}
				}
				closedir($hourHandle);
			}
		}
	}
	
	/**
	 * Рекурсивная очистка папки
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	private static function absolutePathRecursiveDelete(
		string $path
	): bool
	{
		if(
			$path === ''
			|| $path === static::DirectorySeparator
		)
		{
			return false;
		}

		$result = true;
		if(
			is_file($path)
			|| is_link($path)
		)
		{
			if(@unlink($path))
			{
				return true;
			}
			
			return false;
		}
		elseif(is_dir($path))
		{
			if($handle = opendir($path))
			{
				while(($file = readdir($handle)) !== false)
				{
					if(
						$file === '.'
						|| $file === '..'
					)
					{
						continue;
					}

					if(!static::absolutePathRecursiveDelete($path.static::DirectorySeparator.$file))
					{
						$result = false;
					}
				}
				closedir($handle);
			}
			
			$r = @rmdir($path);
			if(!$r)
			{
				return false;
			}
			
			return $result;
		}
		
		return false;
	}
	// endregion ////
}