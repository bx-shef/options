<?php

declare(strict_types=1);

/**
 * Pid::removeByGroup(): каталога группы может не быть.
 *
 * Метод зовут из DoUninstall() — так учит навык shef-new-agent, и так сделано
 * в модулях линейки. Каталог блокировок создаётся ПЕРВЫМ запуском, поэтому у
 * агента, который ни разу не отработал, его не существует. На отсутствующем
 * каталоге RecursiveDirectoryIterator бросает UnexpectedValueException, и
 * удаление модуля обрывалось на середине: файлы уже сняты, движки и
 * регистрация остались (issue #43, падало удаление shef.toolsai).
 *
 * Сторож держит обе стороны: на отсутствующем каталоге метод не бросает и
 * отдаёт успешный Result, а на существующем по-прежнему снимает замки — иначе
 * «починкой» можно было бы объявить ранний выход всегда.
 *
 * Ядро подменяется заглушками, класс подключается настоящий. Временный
 * каталог задаётся BX_TEMPORARY_FILES_DIRECTORY: Manager читает её первой,
 * так что до DOCUMENT_ROOT портала дело не доходит.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';
require_once $root.'/lib/options/singleton.php';
require_once $root.'/lib/main/constants.php';
require_once $root.'/lib/main/tempfile/manager.php';
require_once $root.'/lib/main/tempfile/pid.php';

use Shef\Options\Main\TempFile\Pid;

$sandbox = sys_get_temp_dir().'/shef-options-pid-'.getmypid();

// Уборка вешается на завершение процесса, а не пишется в конце файла: тест
// существует ради падения, а падение до конца файла не доходит и оставило бы
// каталог в /tmp следующему прогону с тем же pid.
register_shutdown_function(static function () use ($sandbox): void {
    exec('rm -rf '.escapeshellarg($sandbox));
});

exec('rm -rf '.escapeshellarg($sandbox));

define('BX_TEMPORARY_FILES_DIRECTORY', $sandbox);

Check::group('каталога группы нет');

$missing = 'нет-такой-группы';

Check::same('каталога действительно нет', is_dir(Pid::getBasePath($missing)), false);

// Ради этой строки тест и написан: до правки здесь летел
// UnexpectedValueException, и до Check::finish() дело не доходило.
$result = Pid::removeByGroup($missing, 0);

Check::same('исключения нет, Result успешный', $result->isSuccess(), true);
Check::same('список пуст', $result->getData()['filePathList'] ?? null, []);

// Каталог не создаётся заодно: метод называется «удалить», а не «обеспечить».
Check::same('каталог не создан', is_dir(Pid::getBasePath($missing)), false);

Check::group('каталог есть, в нём замок');

$group = 'есть-группа';
$dir = Pid::getBasePath($group);
mkdir($dir, 0777, true);

$lock = $dir.'/probe_'.getmypid().'.lock';
$other = $dir.'/readme.txt';

// В файле — свой же pid, а сигнал нулевой: posix_kill($pid, 0) ничего не
// делает, только проверяет доставимость. Стрелять по живым процессам тест
// не должен.
file_put_contents($lock, (string)getmypid());
file_put_contents($other, 'не замок');

$result = Pid::removeByGroup($group, 0);

Check::same('Result успешный', $result->isSuccess(), true);
Check::same('замок удалён', is_file($lock), false);
Check::same('путь попал в список', $result->getData()['filePathList'] ?? [], [$lock]);

// В каталоге группы разбираются только *.lock: чужое расширение — не наше
// дело, а удалить его означало бы унести файл, который метод не создавал.
Check::same('посторонний файл на месте', is_file($other), true);

Check::group('каталог есть, но пустой');

$empty = 'пустая-группа';
mkdir(Pid::getBasePath($empty), 0777, true);

$result = Pid::removeByGroup($empty, 0);

Check::same('Result успешный', $result->isSuccess(), true);
Check::same('список пуст', $result->getData()['filePathList'] ?? null, []);

Check::finish();
