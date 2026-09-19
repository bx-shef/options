<?php declare(strict_types=1);

/**
 * Блокировка процесса через pid-файл.
 *
 * ЦЕЛЬ
 *   Показать \Shef\Options\Main\TempFile\Pid целиком: как процесс объявляет
 *   себя занятым, как повторный запуск это видит, и главное — что делает
 *   clearDir(), то есть почему упавший процесс не держит группу вечно.
 *
 * ГДЕ ПРИМЕНЯТЬ
 *   Агенты и cron-скрипты, которым нельзя работать в два экземпляра: импорт,
 *   выгрузка, пересчёт. Группа — это имя задачи; в одной группе может быть
 *   много процессов (у каждого свой файл по его pid), и группу целиком можно
 *   остановить одним вызовом removeByGroup().
 *
 * ЧТО ДОЛЖНО ПОЛУЧИТЬСЯ
 *   Все строки «ok», последняя — «ГОТОВО: pid», код возврата 0.
 *   По сути:
 *     * add() создаёт файл с именем вида <префикс>_<pid>.lock;
 *     * clearDir() убирает блокировку МЁРТВОГО процесса и НЕ трогает живую;
 *     * remove() убирает свою, removeByGroup() — всю группу.
 *
 * ЗАПУСК
 *   php examples/pid.php
 *   DOCUMENT_ROOT=/var/www/portal php examples/pid.php
 *
 * НА ПОРТАЛЕ ОТЛИЧАЕТСЯ
 *   Файлы лягут в настоящий временный каталог портала (upload/tmp либо
 *   BX_TEMPORARY_FILES_DIRECTORY), в подкаталог shef.options/example-pid.
 *   Пример убирает за собой все файлы, пустой каталог группы остаётся.
 *   Сигналов живым процессам пример не шлёт: removeByGroup() зовётся с 0.
 */

require_once __DIR__.'/_bootstrap.php';

title('Блокировка процесса через pid-файл');

$load(
	'lib/options/singleton.php',
	'lib/main/constants.php',
	'lib/main/tempfile/manager.php',
	'lib/main/tempfile/pid.php'
);

use Bitrix\Main\IO;
use Shef\Options\Main\TempFile\Pid;

const GROUP = 'example-pid';

// region Вспомогательное для примера ////
/**
 * Идентификатор процесса, которого точно нет.
 *
 * Берём настоящий завершившийся процесс: его номер гарантированно свободен.
 * Это не часть API модуля — просто нужен «мёртвый» pid, чтобы показать чистку.
 */
$deadPid = static function(): int
{
	if(function_exists('shell_exec'))
	{
		try
		{
			$pid = (int)shell_exec(PHP_BINARY.' -r "echo getmypid();"');

			if($pid > 0 && !is_dir('/proc/'.$pid))
			{
				return $pid;
			}
		}
		catch(Throwable)
		{
			// shell_exec запрещён — пойдём запасным путём.
		}
	}

	// Запасной путь: номер за пределами обычного диапазона.
	return 4194303;
};
// endregion ////

step('Процесс объявляет себя занятым');

$lock = new Pid(GROUP);

check('до add() файла нет', $lock->isExist(), false);
check('add() отработал', $lock->add(), true);
check('файл появился', $lock->isExist(), true);

// Имя файла — это pid процесса: свой файл процесс узнаёт по имени, а чужие
// видит рядом в каталоге группы.
check('в имени файла — pid процесса', basename($lock->getPath()), getmypid().'.lock');
check('внутри файла — он же', trim(file_get_contents($lock->getPath())), (string)getmypid());

note('каталог группы: '.dirname($lock->getPath()));

step('Повторный вызов из того же процесса ничего не ломает');

check('add() повторно', $lock->add(), true);
check('файл всё тот же', $lock->isExist(), true);

step('Упавший процесс не держит группу вечно');

// Процесс, убитый по -9 или упавший по фатальной ошибке, свой pid-файл убрать
// не успевает. Без чистки такая блокировка держала бы группу навсегда.
$crashed = dirname($lock->getPath()).'/crashed_'.$deadPid().'.lock';
file_put_contents($crashed, (string)$deadPid());

check('чужой файл лежит рядом', is_file($crashed), true);

// clearDir() зовётся из add() автоматически. Здесь вызываем явно, чтобы
// увидеть, что именно он убрал.
$removed = $lock->clearDir(new IO\File($lock->getPath()));

check('убран ровно один файл', count($removed), 1);
check('и это файл мёртвого процесса', basename($removed[0] ?? ''), basename($crashed));
check('файла больше нет', is_file($crashed), false);
check('своя блокировка на месте', $lock->isExist(), true);

step('Живую блокировку не трогаем');

// Файл с ЖИВЫМ pid — берём свой собственный, он точно жив. Имя другое,
// значит для clearDir() это чужая блокировка.
$alive = dirname($lock->getPath()).'/neighbour_'.getmypid().'.lock';
file_put_contents($alive, (string)getmypid());

$removed = $lock->clearDir(new IO\File($lock->getPath()));

check('не убрано ничего', $removed, []);
check('живая блокировка на месте', is_file($alive), true);

note('Когда выяснить, жив ли процесс, нечем (нет /proc и ext-posix),');
note('файл ОСТАЁТСЯ: лишняя блокировка — это задержка, лишнее удаление —');
note('два процесса там, где должен быть один.');

step('Убрать свою блокировку');

$response = $lock->remove();

check('remove() без ошибок', $response->isSuccess(), true);
check('своего файла нет', $lock->isExist(), false);
check('чужой не тронут', is_file($alive), true);

step('Остановить всю группу');

// Второй аргумент — сигнал процессам из найденных файлов. По умолчанию 15
// (SIGTERM), то есть «остановись». Здесь 0: он ничего не делает, только
// проверяет доставимость, — примеру никого убивать не нужно.
$response = Pid::removeByGroup(GROUP, 0);

check('removeByGroup() без ошибок', $response->isSuccess(), true);
check('файлов группы не осталось', is_file($alive), false);
check('в списке удалённого — один файл', count($response->getData()['filePathList']), 1);

step('О чём помнить');

note('removeByGroup() ПО УМОЛЧАНИЮ шлёт SIGTERM процессам из файлов группы.');
note('Нужно только убрать файлы — передавайте 0 вторым аргументом.');
note('Префикс в конструкторе: new Pid(группа, префикс) даст префикс_<pid>.lock.');
note('Файл живёт до remove(): концом скрипта он сам не удаляется.');

done('pid');
