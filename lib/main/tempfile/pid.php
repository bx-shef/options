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
	 * Путь к своему pid-файлу.
	 *
	 * Каталог задаётся группой, имя — префиксом и pid процесса: два процесса
	 * одной группы получают разные файлы, а один и тот же процесс при
	 * повторном создании объекта — тот же самый.
	 *
	 * @return string
	 */
	public function getPath(): string
	{
		return $this->getFile()->getPath();
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
	
	/**
	 * Жив ли процесс с таким идентификатором.
	 *
	 * Три состояния, и третье здесь главное: true — жив, false — точно нет,
	 * null — выяснить нечем. Вызывающий обязан различать «нет» и «не знаю»,
	 * иначе на машине без /proc и без ext-posix чистка снесёт чужие живые
	 * блокировки.
	 *
	 * @param int $pid
	 * @return bool|null
	 */
	protected static function isProcessAlive(int $pid): null|bool
	{
		if($pid <= 0)
		{
			return false;
		}
		
		// Linux: самый прямой ответ, без прав и сигналов.
		if(is_dir('/proc'))
		{
			return is_dir('/proc/'.$pid);
		}
		
		if(
			extension_loaded('posix')
			&& function_exists('posix_kill')
		)
		{
			// Сигнал 0 ничего не делает, но проверяет доставимость.
			if(posix_kill($pid, 0))
			{
				return true;
			}
			
			return match(posix_get_last_error())
			{
				// EPERM: процесс есть, он просто не наш. Это «жив».
				1 => true,
				// ESRCH: такого процесса нет.
				3 => false,
				default => null,
			};
		}
		
		return null;
	}
	
	/**
	 * Убирает из каталога группы pid-файлы, чей процесс уже не живёт.
	 *
	 * Зовётся из add(), и в этом весь смысл: процесс, убитый по -9 или
	 * упавший по фатальной ошибке, свой pid-файл убрать не успевает. Без
	 * чистки такая блокировка держит группу вечно, и следующий запуск
	 * молча не стартует.
	 *
	 * Удаляется только то, про что ТОЧНО известно, что процесса нет.
	 * «Не знаю» (нет ни /proc, ни ext-posix) считается за «жив»: лишний
	 * невычищенный файл — это задержка до ручного вмешательства, а лишнее
	 * удаление — два процесса в группе, где должен быть один.
	 *
	 * Свой файл не трогается никогда, чужие расширения — тоже: в каталоге
	 * группы разбираем только *.lock.
	 *
	 * Каталог просматривается без рекурсии: pid-файлы группы лежат в нём
	 * плоско, а спуск вглубь означал бы удаление в чужих каталогах.
	 *
	 * @param IO\File $fileOri свой pid-файл — его и только его оставляем
	 *
	 * @return string[] пути удалённых файлов
	 */
	public function clearDir(IO\File $fileOri): array
	{
		$removed = [];
		
		$directory = $fileOri->getDirectory()->getPath();
		
		if(!is_dir($directory))
		{
			return $removed;
		}
		
		foreach(new \DirectoryIterator($directory) as $item)
		{
			if(
				$item->isDot()
				|| !$item->isFile()
			)
			{
				continue;
			}
			
			$file = new IO\File($item->getPathname());
			
			if($file->getName() === $fileOri->getName())
			{
				continue;
			}
			
			if($file->getExtension() !== 'lock')
			{
				continue;
			}
			
			// Удаляем, только когда процесса ТОЧНО нет: true и null оставляем.
			if(false !== static::isProcessAlive((int)trim($file->getContents())))
			{
				continue;
			}
			
			if($file->delete())
			{
				$removed[] = $file->getPath();
			}
			
			unset($file);
		}
		
		unset($item);
		
		return $removed;
	}

	/**
	 * Создаёт pid-файл текущего процесса.
	 *
	 * Перед созданием убирает из каталога группы блокировки мёртвых
	 * процессов — @see clearDir(). Повторный вызов из того же процесса
	 * ничего не делает и возвращает true: имя файла содержит pid, то есть
	 * свой файл процесс узнаёт по имени.
	 *
	 * @return bool false — записать файл не удалось
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