<?php

declare(strict_types=1);

/**
 * Pid::removeByGroup(): остановка группы не должна ронять установщик.
 *
 * Метод зовут из DoUninstall() модуля-потребителя — так учит навык
 * shef-new-agent, так сделано в модулях линейки. Исключение оттуда обрывает
 * удаление модуля на середине, и доудалить его потом нечем: install/index.php
 * уже снят. Поэтому метод обязан складывать отказы в Result, а не бросать.
 *
 * Issue #43 нашёл один путь к исключению: каталога группы нет (создаётся
 * первым запуском, а агент мог ни разу не отработать). Панель из пяти нашла
 * остальные, и каждый замерен:
 *
 *   * каталог есть, а прав на чтение нет — замки создал cron под другим
 *     пользователем, удаляют модуль из админки по HTTP;
 *   * обход был рекурсивным и шёл по ссылкам: одна ссылка в каталоге группы
 *     уводила метод к замкам соседнего модуля, он их снимал и рассылал их
 *     процессам SIGTERM;
 *   * имя группы не проверялось: пустое давало каталог библиотеки целиком,
 *     «..» — ещё выше;
 *   * содержимое замка уходило в шелл: «1 ; touch …» исполнялось;
 *   * сигнал шёл по номеру из файла без сверки с номером в имени;
 *   * отказ удаления одного замка бросал остаток группы неостановленным.
 *
 * Ядро подменяется заглушками, класс подключается настоящий. Временный
 * каталог задаётся BX_TEMPORARY_FILES_DIRECTORY: Manager читает её первой.
 *
 * Проверки, зависящие от прав файловой системы, под root бессмысленны — он
 * их обходит, — и тогда пропускаются ВСЛУХ через Check::skip(). CI гоняет от
 * обычного пользователя, значит там они выполняются.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/stub/bitrix.php';
require_once $root.'/tests/assert.php';
require_once $root.'/lib/options/singleton.php';
require_once $root.'/lib/main/constants.php';
require_once $root.'/lib/main/tempfile/manager.php';
require_once $root.'/lib/main/tempfile/pid.php';

use Bitrix\Main\Result;
use Shef\Options\Main\TempFile\Pid;

/**
 * Жив ли процесс — «не знаю». Так выглядит хост без /proc и без ext-posix.
 * Подмена работает потому, что вспомогательные методы Pid — protected
 * static, а зовутся через static::.
 */
final class PidUnknownAlive extends Pid
{
    protected static function isProcessAlive(int $pid): null|bool
    {
        return null;
    }
}

/**
 * Считает попытки послать сигнал и ничего не посылает. Нужен там, где «не
 * послали» и «послали» снаружи неотличимы: мёртвому процессу сигнал не
 * вредит, сигнал 0 не вредит никому.
 */
final class PidSignalCounter extends Pid
{
    public static int $calls = 0;

    protected static function sendSignal(int $pid, int $stopSignal, Result $result): void
    {
        static::$calls++;
    }
}

$sandbox = sys_get_temp_dir().'/shef-options-pid-'.getmypid();

/** Живые дочерние процессы — снимаем их, чем бы тест ни кончился. */
$children = [];

// Уборка вешается на завершение процесса, а не пишется в конце файла: тест
// существует ради падения, а падение до конца файла не доходит.
register_shutdown_function(static function () use ($sandbox, &$children): void {
    foreach ($children as $proc) {
        if (is_resource($proc)) {
            @proc_terminate($proc, 9);
            @proc_close($proc);
        }
    }

    // chmod обратно: каталог без прав на запись не снести даже рекурсивно.
    foreach ((array)glob($sandbox.'/shef.options/*') as $path) {
        if (is_dir($path)) {
            @chmod($path, 0o755);
        }
    }

    exec('rm -rf '.escapeshellarg($sandbox));
});

exec('rm -rf '.escapeshellarg($sandbox));

define('BX_TEMPORARY_FILES_DIRECTORY', $sandbox);

/** Под root проверки прав не работают: он читает и пишет мимо них. */
$isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;

/** Создаёт каталог группы и возвращает путь. */
$makeGroup = static function (string $group): string {
    $dir = Pid::getBasePath($group);
    mkdir($dir, 0o777, true);

    return $dir;
};

/** Кладёт замок: имя — <префикс>_<pid>.lock, содержимое — что передали. */
$makeLock = static function (string $dir, string $name, string $content): string {
    $path = $dir.'/'.$name;
    file_put_contents($path, $content);

    return $path;
};

/** Живой дочерний процесс: [handle, pid]. */
$spawn = static function () use (&$children): array {
    // Массивом, а не строкой: строка поднимает ещё и sh, и pid оказался бы
    // не у php, а у оболочки — сигнал ушёл бы не туда.
    $proc = proc_open(
        [PHP_BINARY, '-r', 'sleep(30);'],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes
    );

    $children[] = $proc;
    $status = proc_get_status($proc);

    return [$proc, (int)$status['pid']];
};

/** Ждёт завершения процесса; отдаёт последний status. */
$waitGone = static function ($proc, float $seconds = 5.0): array {
    $deadline = microtime(true) + $seconds;

    do {
        $status = proc_get_status($proc);
        if (!$status['running']) {
            return $status;
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);

    return $status;
};

Check::group('каталога группы нет');

$missing = 'нет-такой-группы';

Check::same('каталога действительно нет', is_dir(Pid::getBasePath($missing)), false);

// Ради этой строки тест и написан: до правки летел UnexpectedValueException.
$result = Pid::removeByGroup($missing, 0);

Check::same('исключения нет, Result успешный', $result->isSuccess(), true);
Check::same('список пуст', $result->getData()['filePathList'] ?? null, []);

// Каталог не создаётся заодно: метод называется «снять», а не «обеспечить».
Check::same('каталог не создан', is_dir(Pid::getBasePath($missing)), false);

Check::group('каталог есть, в нём замок');

$dir = $makeGroup('есть-группа');
$lock = $makeLock($dir, 'probe_'.getmypid().'.lock', (string)getmypid());
$other = $makeLock($dir, 'readme.txt', 'не замок');

$result = Pid::removeByGroup('есть-группа', 0);

Check::same('Result успешный', $result->isSuccess(), true);
Check::same('замок снят', is_file($lock), false);
Check::same('путь попал в список', $result->getData()['filePathList'] ?? [], [$lock]);

// В каталоге группы разбираются только *.lock: чужое расширение не наше
// дело, а удалить его значило бы унести файл, который метод не создавал.
Check::same('посторонний файл на месте', is_file($other), true);

Check::group('каталог есть, но пустой');

$result = Pid::removeByGroup($makeGroup('пустая-группа') ? 'пустая-группа' : '', 0);

Check::same('Result успешный', $result->isSuccess(), true);
Check::same('список пуст', $result->getData()['filePathList'] ?? null, []);

Check::group('обход плоский: в подкаталог не спускаемся');

$dir = $makeGroup('группа-с-подкаталогом');
mkdir($dir.'/deeper', 0o777, true);
$nested = $makeLock($dir.'/deeper', 'probe_1.lock', '1');

$result = Pid::removeByGroup('группа-с-подкаталогом', 0);

// getFilePath() кладёт замки плоско, поэтому спуск вглубь не даёт ничего, а
// покупает удаление в чужих каталогах. Так же устроен и clearDir().
Check::same('Result успешный', $result->isSuccess(), true);
Check::same('замок в подкаталоге не тронут', is_file($nested), true);
Check::same('список пуст', $result->getData()['filePathList'] ?? null, []);

Check::group('по ссылке не ходим');

$neighbour = $makeGroup('сосед');
$neighbourLock = $makeLock($neighbour, 'agent_1.lock', '1');

$dir = $makeGroup('группа-со-ссылкой');

// Две ссылки, и они ловят разное. На КАТАЛОГ — старую рекурсию с
// FOLLOW_SYMLINKS: обход уходил в каталог соседа и снимал его замки. На ФАЙЛ
// с расширением .lock — чтение чужого файла: номер для сигнала брался бы
// через ссылку, а снимался бы сам указатель.
symlink($neighbour, $dir.'/link');
symlink($neighbourLock, $dir.'/stolen_1.lock');

$result = Pid::removeByGroup('группа-со-ссылкой', 0);

Check::same('Result успешный', $result->isSuccess(), true);
Check::same('замок соседа на месте', is_file($neighbourLock), true);
Check::same('ссылка не тронута', is_link($dir.'/stolen_1.lock'), true);
Check::same('список пуст', $result->getData()['filePathList'] ?? null, []);

Check::group('имя группы выводит за каталог библиотеки');

foreach (['' => 'пустое имя', '..' => 'каталог выше'] as $bad => $what) {
    $result = Pid::removeByGroup($bad, 0);

    Check::same($what.': Result неуспешный', $result->isSuccess(), false);
    Check::same(
        $what.': причина названа',
        str_contains(implode(' ', $result->getErrorMessages()), 'is outside of'),
        true
    );
}

// Главное — не текст ошибки, а что ничего не снято: пустое имя давало
// каталог библиотеки целиком, то есть замки ВСЕХ групп ВСЕХ модулей.
Check::same('замок соседа цел', is_file($neighbourLock), true);
Check::same('замок в подкаталоге цел', is_file($nested), true);

Check::group('сигнал по умолчанию — SIGTERM');

$dir = $makeGroup('живая-группа');
[$proc, $pid] = $spawn();
$makeLock($dir, 'agent_'.$pid.'.lock', (string)$pid);

// Без второго аргумента. Умолчание опасное, и до этого теста его не
// проверяло ничто: мутации «15 на 9» и «убрать kill совсем» были зелёными.
$result = Pid::removeByGroup('живая-группа');
$status = $waitGone($proc);

Check::same('Result успешный', $result->isSuccess(), true);
Check::same('процесс остановлен', $status['running'], false);
Check::same('остановлен именно сигналом 15', $status['termsig'] ?? null, 15);

Check::group('сигнал 0 не стреляет');

$dir = $makeGroup('нетронутая-группа');
[$aliveProc, $alivePid] = $spawn();
$makeLock($dir, 'agent_'.$alivePid.'.lock', (string)$alivePid);

$result = Pid::removeByGroup('нетронутая-группа', 0);

Check::same('Result успешный', $result->isSuccess(), true);
Check::same('замок снят', $result->getData()['filePathList'] !== [], true);
Check::same('процесс жив', proc_get_status($aliveProc)['running'], true);

Check::group('номер в имени и в содержимом расходятся');

$dir = $makeGroup('подменённая-группа');
[$safeProc, $safePid] = $spawn();

// Содержимое — настоящий живой процесс, имя — чужой номер. Такой замок либо
// испорчен, либо чужой, и сигнал по нему ушёл бы не тому.
$lock = $makeLock($dir, 'agent_1.lock', (string)$safePid);

$result = Pid::removeByGroup('подменённая-группа');

Check::same('Result неуспешный', $result->isSuccess(), false);
Check::same(
    'причина названа',
    str_contains(implode(' ', $result->getErrorMessages()), 'differ'),
    true
);
Check::same('по чужому номеру не стреляли', proc_get_status($safeProc)['running'], true);
Check::same('замок всё равно снят', is_file($lock), false);

Check::group('вместо номера мусор');

$dir = $makeGroup('мусорная-группа');

// Номер в мусоре — своего ребёнка, а не «1»: на мутации «только цифры →
// (int)» номер доживает до сигнала, и с «1» тест слал бы SIGTERM процессу
// init. Сама инъекция проверяется ниже, в ветке без ext-posix — здесь
// шелла нет, и маркер не появился бы в любом случае.
[$junkProc, $junkPid] = $spawn();
$lock = $makeLock(
    $dir,
    'agent_'.$junkPid.'.lock',
    $junkPid.' ; touch '.escapeshellarg($sandbox.'/PWNED')
);

$result = Pid::removeByGroup('мусорная-группа');

Check::same('Result неуспешный', $result->isSuccess(), false);
Check::same(
    'причина названа',
    str_contains(implode(' ', $result->getErrorMessages()), 'holds no pid'),
    true
);
Check::same('по мусору не стреляли', proc_get_status($junkProc)['running'], true);
Check::same('замок снят', is_file($lock), false);

Check::group('в имени нет номера');

$dir = $makeGroup('безымянная-группа');
[$namelessProc, $namelessPid] = $spawn();

// getFilePath() пишет номер в имя ВСЕГДА, так что имя без номера — чужой или
// рукотворный файл. Раньше сверка с именем на таком просто пропускалась, и
// номер постороннего процесса доводил до SIGTERM при чистом Result.
$lock = $makeLock($dir, 'agent.lock', (string)$namelessPid);

$result = Pid::removeByGroup('безымянная-группа');

Check::same('Result неуспешный', $result->isSuccess(), false);
Check::same(
    'причина названа',
    str_contains(implode(' ', $result->getErrorMessages()), 'no pid in name'),
    true
);
Check::same('не стреляли', proc_get_status($namelessProc)['running'], true);
Check::same('замок снят', is_file($lock), false);

Check::group('жив ли процесс — не знаю: не стреляем');

$dir = $makeGroup('неизвестная-группа');
[$unknownProc, $unknownPid] = $spawn();
$makeLock($dir, 'agent_'.$unknownPid.'.lock', (string)$unknownPid);

// Номер может достаться постороннему процессу, а проверить это нечем.
// clearDir() в том же положении ничего не трогает; здесь «ничего не трогать»
// значит «не стрелять». Замок снимается — в этом и работа метода.
$result = PidUnknownAlive::removeByGroup('неизвестная-группа');

Check::same('Result неуспешный', $result->isSuccess(), false);
Check::same(
    'причина названа',
    str_contains(implode(' ', $result->getErrorMessages()), 'Can not tell'),
    true
);
Check::same('процесс жив', proc_get_status($unknownProc)['running'], true);
Check::same('замок снят', $result->getData()['filePathList'] !== [], true);

Check::group('кому и когда сигнал вообще посылается');

// Здесь не важно, что будет с процессом, — важно, была ли попытка. Мёртвому
// процессу и сигналу 0 выстрел не вредит, поэтому снаружи «послали» и «не
// послали» неотличимы, и проверять надо счётчиком.
$dir = $makeGroup('счётная-группа');

[$deadProc, $deadPid] = $spawn();
proc_terminate($deadProc, 9);
$waitGone($deadProc);
$makeLock($dir, 'agent_'.$deadPid.'.lock', (string)$deadPid);

PidSignalCounter::$calls = 0;
PidSignalCounter::removeByGroup('счётная-группа');
Check::same('мёртвому не шлём', PidSignalCounter::$calls, 0);

[$liveProc, $livePid] = $spawn();
$makeLock($dir, 'agent_'.$livePid.'.lock', (string)$livePid);

PidSignalCounter::$calls = 0;
PidSignalCounter::removeByGroup('счётная-группа', 0);
Check::same('сигнал 0 — не шлём', PidSignalCounter::$calls, 0);

$makeLock($dir, 'agent_'.$livePid.'.lock', (string)$livePid);

PidSignalCounter::$calls = 0;
PidSignalCounter::removeByGroup('счётная-группа', 15);
Check::same('живому шлём ровно раз', PidSignalCounter::$calls, 1);

Check::group('ветка без ext-posix: шелл');

if (!is_dir('/proc')) {
    Check::skip('kill через шелл', 'нет /proc — живость без posix не выяснить', 5);
} else {
    // В CI ext-posix есть, и ветку с шеллом — ту, где и была инъекция, —
    // обычный прогон не видит: проверка про маркер была зелёной просто
    // потому, что шелла не было. Поэтому сценарий гоняется в отдельном
    // процессе с выключенным posix_kill.
    $runWithout = static function (string $disable, string $group, string $content, string $name) use ($root, $sandbox): array {
        $script = $sandbox.'/no-posix.php';
        file_put_contents($script, '<?php
            declare(strict_types=1);
            define("BX_TEMPORARY_FILES_DIRECTORY", '.var_export($sandbox, true).');
            require '.var_export($root.'/tests/stub/bitrix.php', true).';
            require '.var_export($root.'/lib/options/singleton.php', true).';
            require '.var_export($root.'/lib/main/constants.php', true).';
            require '.var_export($root.'/lib/main/tempfile/manager.php', true).';
            require '.var_export($root.'/lib/main/tempfile/pid.php', true).';
            $dir = \Shef\Options\Main\TempFile\Pid::getBasePath($argv[1]);
            @mkdir($dir, 0777, true);
            file_put_contents($dir."/".$argv[3], $argv[2]);
            $r = \Shef\Options\Main\TempFile\Pid::removeByGroup($argv[1]);
            echo json_encode([
                "posix" => function_exists("posix_kill"),
                "exec" => function_exists("exec"),
                "ok" => $r->isSuccess(),
                "errors" => $r->getErrorMessages(),
                "list" => $r->getData()["filePathList"],
            ]);
        ');

        $output = [];
        $code = 0;
        exec(
            escapeshellarg(PHP_BINARY)
            .' -d '.escapeshellarg('disable_functions='.$disable)
            .' '.escapeshellarg($script)
            .' '.escapeshellarg($group)
            .' '.escapeshellarg($content)
            .' '.escapeshellarg($name)
            .' 2>&1',
            $output,
            $code
        );

        return (array)json_decode(implode('', $output), true);
    };

    $noPosix = 'posix_kill,posix_get_last_error,posix_strerror';

    [$shellProc, $shellPid] = $spawn();
    $report = $runWithout($noPosix, 'шелл-группа', (string)$shellPid, 'agent_'.$shellPid.'.lock');

    Check::same('posix_kill действительно выключен', $report['posix'] ?? null, false);
    Check::same('процесс остановлен через kill', $waitGone($shellProc)['running'], false);

    // Номер — живого процесса, и после него точка с запятой. На коде до
    // правки это исполнялось; проверка здесь бьёт по-настоящему, потому что
    // шелл есть.
    [$injProc, $injPid] = $spawn();
    $report = $runWithout(
        $noPosix,
        'инъекция-группа',
        $injPid.' ; touch '.$sandbox.'/PWNED',
        'agent_'.$injPid.'.lock'
    );

    Check::same('вторая команда не исполнилась', file_exists($sandbox.'/PWNED'), false);

    // Нет и exec: вызов бросает Error, который «@» не глушит. Раньше он
    // улетал из sendSignal(), и замок оставался на месте.
    [$noExecProc, $noExecPid] = $spawn();
    $report = $runWithout(
        $noPosix.',exec',
        'безexec-группа',
        (string)$noExecPid,
        'agent_'.$noExecPid.'.lock'
    );

    Check::same('отказ сигнала в Result', str_contains(implode(' ', $report['errors'] ?? []), 'Can not signal'), true);
    Check::same('замок всё равно снят', count($report['list'] ?? []), 1);
}

Check::group('по пути группы лежит файл');

$filePath = Pid::getBasePath('группа-файлом');
file_put_contents($filePath, 'не каталог');

$result = Pid::removeByGroup('группа-файлом', 0);

Check::same('Result успешный', $result->isSuccess(), true);
Check::same('список пуст', $result->getData()['filePathList'] ?? null, []);
Check::same('файл не тронут', is_file($filePath), true);

Check::group('каталог есть, читать нельзя');

if ($isRoot) {
    Check::skip('нечитаемый каталог даёт ошибку в Result', 'прогон от root, права не действуют', 2);
} else {
    $dir = $makeGroup('закрытая-группа');
    $makeLock($dir, 'agent_1.lock', '1');
    chmod($dir, 0o000);

    // Ровно тот отказ, из-за которого писался PR: is_dir() на таком каталоге
    // отвечает true, а итератор всё равно бросает.
    $result = Pid::removeByGroup('закрытая-группа', 0);

    Check::same('исключения нет', $result->isSuccess(), false);
    Check::same(
        'причина названа',
        str_contains(implode(' ', $result->getErrorMessages()), 'Permission denied'),
        true
    );

    chmod($dir, 0o755);
}

Check::group('удалить не удалось — остаток группы всё равно обходим');

if ($isRoot) {
    Check::skip('отказ удаления попадает в Result', 'прогон от root, права не действуют', 4);
} else {
    $dir = $makeGroup('неудаляемая-группа');
    $first = $makeLock($dir, 'agent_1.lock', '1');
    $second = $makeLock($dir, 'agent_2.lock', '2');

    // Каталог читается, но не пишется: unlink откажет на обоих файлах.
    chmod($dir, 0o500);

    $result = Pid::removeByGroup('неудаляемая-группа', 0);

    Check::same('Result неуспешный', $result->isSuccess(), false);

    // Две ошибки, а не одна: раньше здесь стоял return, и остаток группы
    // бросался неостановленным.
    Check::same('обошли оба файла', count($result->getErrorMessages()), 2);
    Check::same('список пуст', $result->getData()['filePathList'] ?? null, []);

    chmod($dir, 0o755);
    Check::same('файлы на месте', is_file($first) && is_file($second), true);
}

Check::group('clearDir(): нечитаемый каталог не роняет add()');

if ($isRoot) {
    Check::skip('clearDir на нечитаемом каталоге', 'прогон от root, права не действуют');
} else {
    // clearDir() зовётся из add(), то есть на КАЖДОМ прогоне агента, а не
    // только при удалении модуля. Исключение отсюда означало бы, что агент
    // не стартует вовсе.
    // Каталог группы создаёт сам конструктор — через Manager::checkDirPath().
    $lock = new Pid('группа-чистки');
    $dir = Pid::getBasePath('группа-чистки');

    $own = new \Bitrix\Main\IO\File($dir.'/own_1.lock');
    file_put_contents($own->getPath(), '1');
    chmod($dir, 0o000);

    Check::same('вернул пустой список, а не бросил', $lock->clearDir($own), []);

    chmod($dir, 0o755);
}

Check::finish();
