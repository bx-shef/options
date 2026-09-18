<?php declare(strict_types=1);

namespace Shef\Options\Main\TempFile;

use Bitrix\Main\IO\FileNotFoundException;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\IO;
use Shef\Options\Main\Constants;

/**
 * Класс работы с pid-файлом
 * Осуществляет создание, удаление, очистку
 */
class Pid
{
	protected readonly int $pid;
	protected IO\File $file;
	
	// region Tools ////
	/**
	 * Возвращает путь к папке хранения pid-файлов
	 *
	 * @param string $group
	 * @return string
	 */
	public static function getBasePath(
		string $group
	): string
	{
		return sprintf(
			'%s/%s/%s',
			Manager::getInstance()->getAbsoluteRoot(),
			Constants::getModuleId(),
			$group
		);
	}
	
	/**
	 * Удаляет pid-файл по группе
	 *
	 * @param string $group
	 * @param int|null $stopSignal
	 *
	 * @return Result
	 * @throws FileNotFoundException
	 */
	public static function removeByGroup(
		string $group,
		null|int $stopSignal = null
	): Result
	{
		$result = new Result();
		
		$list = [];
		$result->setData([
			'filePathList' => &$list
		]);
		
		if(null === $stopSignal)
		{
			// SIGTERM ////
			$stopSignal = 15;
		}
		
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				static::getBasePath($group),
				\RecursiveDirectoryIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
			),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		
		foreach($iterator as $item)
		{
			if($item->isFile())
			{
				$file = new IO\File($item->getPathname());
				
				if($file->getExtension() !== 'lock')
				{
					continue;
				}
				
				$pid = $file->getContents();
				if((int)$pid > 0)
				{
					if(
						extension_loaded('posix')
						&& function_exists('posix_kill'))
					{
						$response = posix_kill((int)$pid, $stopSignal);
					}
					else
					{
						$response = @exec(sprintf(
							'kill -%s %s',
							$stopSignal,
							$pid
						));
					}
				}
				
				
				if($file->delete())
				{
					$list[] = $file->getPath();
				}
				else
				{
					return $result->addError(new Error(sprintf(
						'Problem delete file %s',
						$file->getPath()
					)));
				}
				
				unset($file);
			}
		}
		
		unset($item, $iterator, $list);
		
		return $result;
	}
	// endregion ////
	
	public function __construct(
		protected readonly string $group,
		protected readonly null|string $fileNamePrefix = null
	)
	{
		$this->pid = getmypid();
		
		$this->file = new IO\File($this->getFilePath());
	}
	
	/**
	 * Возвращает объект pid-файла
	 * 
	 * @return IO\File
	 */
	protected function getFile(): IO\File
	{
		return $this->file;
	}
	
	/**
	 * Возвращает путь к pid-файлу и регистрирует его на удаление
	 *
	 * @return string
	 */
	protected function getFilePath(): string
	{
		$path = static::getBasePath(
			$this->group
		);
		
		$filePath = sprintf(
			'%s/%s.lock',
			$path,
			join('_', array_filter([
				$this->fileNamePrefix,
				$this->pid
			]))
		);
		
		Manager::getInstance()->checkDirPath($filePath);
		
		Manager::getInstance()->addRegisterPath(
			$path,
			$filePath
		);
		
		unset($path);
		
		return $filePath;
	}
	
	public function clearDir(IO\File $fileOri): void
	{
		return;
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				$fileOri->getDirectory()->getPath(),
				\RecursiveDirectoryIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
			),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		
		foreach($iterator as $item)
		{
			if($item->isFile())
			{
				$file = new IO\File($item->getPathname());
				
				if($file->getName() === $fileOri->getName())
				{
					continue;
				}
				
				if($file->delete())
				{
					$list[] = $file->getPath();
				}
				
				unset($file);
			}
		}
		
		unset($item, $iterator, $list);
	}

	/**
	 * Создает pid-файл
	 *
	 * @return bool
	 */
	public function add(): bool
	{
		$file = $this->getFile();
		
		$this->clearDir($file);
		
		if($file->isExists())
		{
			return true;
		}
		
		$response = $file->putContents($this->pid);
		if($response === false)
		{
			return false;
		}
		
		return true;
	}
	
	/**
	 * Проверка наличия pid-файла
	 * 
	 * @return bool
	 */
	public function isExist(): bool
	{
		return $this->getFile()->isExists();
	}
	
	/**
	 * Удаляет pid-файл
	 *
	 * @return Result
	 */
	public function remove(): Result
	{
		$result = new Result();
		
		$file = $this->getFile();
		
		$result->setData([
			'filePath' => $file->getPath()
		]);
		
		if(!$file->isExists())
		{
			return $result;
		}
		
		$response = $file->delete();
		if($response === false)
		{
			return $result->addError(new Error(sprintf(
				'Problem delete file %s',
				$file->getPath()
			)));
		}
		
		return $result;
	}
	// endregion ////
}