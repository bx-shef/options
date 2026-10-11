<?php

declare(strict_types=1);

namespace Shef\Options\Main\TempFile;

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
    ): string {
        return sprintf(
            '%s/%s/%s',
            Manager::getInstance()->getAbsoluteRoot(),
            Constants::getModuleId(),
            $group
        );
    }

    /**
     * Снимает pid-файлы группы и останавливает их процессы.
     *
     * Ошибки складываются в Result и НЕ бросаются. Метод зовут из
     * DoUninstall() модуля-потребителя, а исключение оттуда обрывает удаление
     * модуля на середине: файлы уже сняты, движки и регистрация остались.
     * Отказ на одном замке не отменяет остановку остальных.
     *
     * @param string $group имя группы. Каталог обязан лежать ВНУТРИ каталога
     *                      библиотеки: пустое имя и «..» отклоняются
     * @param int|null $stopSignal номер сигнала; null — SIGTERM (15),
     *                             0 — не посылать ничего
     *
     * @return Result в данных filePathList — пути СНЯТЫХ файлов. Снятый —
     *                не значит остановленный: замок снимается и тогда, когда
     *                сигнал не послан (номер не прочитан, жив ли процесс —
     *                выяснить нечем), — причина в этом случае лежит в ошибках
     */
    public static function removeByGroup(
        string $group,
        null|int $stopSignal = null
    ): Result {
        $result = new Result();

        // Список кладётся в Result в конце, а не ссылкой заранее: ссылка
        // внутри массива держится, пока ядро кладёт данные как есть, и
        // молча оборвётся, стоит setData() начать их нормализовать.
        $list = [];
        $result->setData(['filePathList' => $list]);

        if (null === $stopSignal) {
            // SIGTERM ////
            $stopSignal = 15;
        }

        $basePath = static::resolveGroupPath($group, $result);
        if (null === $basePath) {
            return $result;
        }

        try {
            // Обход ПЛОСКИЙ и без перехода по ссылкам.
            //
            // getFilePath() кладёт замки в каталог группы плоско, так что
            // спуск вглубь не даёт ничего, а покупает выход за пределы
            // группы: одной символической ссылки хватало, чтобы метод снял
            // замки соседнего модуля и разослал SIGTERM его процессам —
            // замерено. Сосед clearDir() обходит каталог так же — с 3.0.0,
            // когда его оживили; до того он был таким же рекурсивным.
            $iterator = new \FilesystemIterator(
                $basePath,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO
            );

            foreach ($iterator as $item) {
                // isLink() спрашивается ПЕРВЫМ: isFile() идёт по ссылке и
                // отвечает про цель.
                if ($item->isLink() || !$item->isFile()) {
                    continue;
                }

                try {
                    static::stopByLockFile(
                        new IO\File($item->getPathname()),
                        $item->getFilename(),
                        $stopSignal,
                        $list,
                        $result
                    );
                } catch (\Throwable $exception) {
                    // Один испорченный замок не отменяет остановку группы.
                    // Исчезнуть файл может и штатно: процесс группы
                    // завершился сам и снял свой замок между листингом и
                    // чтением.
                    $result->addError(new Error($exception->getMessage()));
                }
            }
        } catch (\Throwable $exception) {
            // Каталог есть, а заглянуть в него не удалось: нет прав на
            // чтение (замки создал cron под другим пользователем, а удаляют
            // модуль из админки по HTTP), каталог исчез между проверкой и
            // обходом, по пути оказался не каталог.
            //
            // Это НЕ то же, что «каталога нет»: там останавливать нечего,
            // здесь мы не смогли посмотреть. Первое — пустой успешный
            // Result, второе — ошибка в Result.
            $result->addError(new Error($exception->getMessage()));
        }

        $result->setData(['filePathList' => $list]);

        return $result;
    }

    /**
     * Каталог группы, если он существует и лежит внутри каталога библиотеки.
     *
     * null — обходить нечего. Два разных случая: каталога нет (обычное дело,
     * он создаётся первым запуском) и путь выводит за пределы библиотеки —
     * во втором в Result лежит ошибка.
     *
     * Путь сверяется ПОСЛЕ разрешения, а не до: realpath() раскрывает и
     * «..», и символические ссылки, поэтому проверять надо результат, а не
     * исходную строку. Измерено, чего стоило отсутствие проверки: пустое имя
     * группы давало сам каталог библиотеки и метод снимал замки ВСЕХ групп
     * ВСЕХ модулей линейки, рассылая им SIGTERM; «..» уводило ещё выше.
     * Правдоподобный путь к этому — не атака, а незаполненная настройка, из
     * которой потребитель берёт имя группы.
     *
     * Разделитель на конце префикса обязателен: без него каталогу
     * shef.options подошёл бы сосед shef.options-backup.
     */
    protected static function resolveGroupPath(
        string $group,
        Result $result
    ): null|string {
        $basePath = static::getBasePath($group);

        if (!is_dir($basePath)) {
            return null;
        }

        $real = realpath($basePath);
        $root = realpath(sprintf(
            '%s/%s',
            Manager::getInstance()->getAbsoluteRoot(),
            Constants::getModuleId()
        ));

        if (
            false === $real
            || false === $root
            || !str_starts_with($real, $root.DIRECTORY_SEPARATOR)
        ) {
            $result->addError(new Error(sprintf(
                'Group "%s" is outside of %s',
                $group,
                Constants::getModuleId()
            )));

            return null;
        }

        return $real;
    }

    /**
     * Останавливает процесс одного замка и снимает файл.
     *
     * @param string[] $list пути снятых файлов, пополняется
     */
    protected static function stopByLockFile(
        IO\File $file,
        string $fileName,
        int $stopSignal,
        array &$list,
        Result $result
    ): void {
        if ($file->getExtension() !== 'lock') {
            return;
        }

        $pid = static::readPid($file, $fileName, $result);

        // Сигнал шлётся, только когда ТОЧНО известно, что процесс жив.
        //
        // «Не знаю» (нет ни /proc, ни ext-posix) здесь значит «не стрелять»,
        // и это то же безопасное направление, что у clearDir(), хотя слова
        // там обратные: clearDir() «не знаю» считает за «жив», потому что для
        // него «жив» — это «не трогать». Здесь «жив» — это «выстрелить», и
        // стрелять по номеру, про который ничего не известно, нельзя: номер
        // мог достаться постороннему процессу. Замерено — посторонний
        // процесс получал SIGTERM, а Result оставался чистым.
        if (null !== $pid && 0 !== $stopSignal) {
            $alive = static::isProcessAlive($pid);

            if (true === $alive) {
                static::sendSignal($pid, $stopSignal, $result);
            } elseif (null === $alive) {
                $result->addError(new Error(sprintf(
                    'Can not tell whether %d is alive, signal not sent',
                    $pid
                )));
            }
        }

        if ($file->delete()) {
            $list[] = $file->getPath();

            return;
        }

        // Раньше здесь стоял return из всего метода, и остаток группы
        // оставался неостановленным — а какие замки уцелеют, зависело от
        // порядка readdir.
        $result->addError(new Error(sprintf(
            'Problem delete file %s',
            $file->getPath()
        )));
    }

    /**
     * Номер процесса из замка; null — посылать сигнал некому.
     *
     * Содержимое обязано быть одними цифрами. Приведения (int) мало: оно
     * пропускает «1 ; touch /tmp/x» — число получается, — а в ветке без
     * ext-posix строка уходила в шелл, и вторая команда исполнялась от
     * пользователя веб-сервера. Замерено, файл создавался.
     *
     * Номер сверяется с номером в ИМЕНИ файла: getFilePath() пишет
     * «<префикс>_<pid>.lock» ВСЕГДА. Расхождение — испорченный или чужой
     * замок, и сигнал по такому номеру ушёл бы постороннему процессу.
     * Имя без номера — тоже отказ, а не «сверять не с чем»: раньше сверка
     * так и пропускалась, и замок «agent.lock» с номером постороннего
     * процесса доводил до SIGTERM при чистом Result — замерено.
     */
    protected static function readPid(
        IO\File $file,
        string $fileName,
        Result $result
    ): null|int {
        $content = trim($file->getContents());

        if (preg_match('/^[0-9]+$/', $content) !== 1) {
            $result->addError(new Error(sprintf(
                'Lock file %s holds no pid',
                $file->getPath()
            )));

            return null;
        }

        $pid = (int)$content;
        if ($pid <= 0) {
            return null;
        }

        if (preg_match('/(?:^|_)([0-9]+)\.lock$/', $fileName, $match) !== 1) {
            $result->addError(new Error(sprintf(
                'Lock file %s: no pid in name',
                $file->getPath()
            )));

            return null;
        }

        if ((int)$match[1] !== $pid) {
            $result->addError(new Error(sprintf(
                'Lock file %s: pid in name and in content differ',
                $file->getPath()
            )));

            return null;
        }

        return $pid;
    }

    /**
     * Посылает процессу сигнал остановки. Не бросает: отказ — ошибка в
     * Result, и замок после этого всё равно снимается.
     *
     * Ветка без ext-posix собирает команду для шелла, поэтому номера уходят
     * в неё числами и через escapeshellarg(). Нет и exec (disable_functions)
     * — вызов бросает Error, который «@» не глушит; раньше он улетал наружу
     * и замок оставался на месте.
     *
     * «Процесса уже нет» ошибкой не считается ни в одной ветке: для
     * «остановить группу» это результат. kill в шелле кодом возврата его от
     * «нет прав» не отличает, поэтому после любого отказа живость
     * спрашивается ещё раз.
     */
    protected static function sendSignal(
        int $pid,
        int $stopSignal,
        Result $result
    ): void {
        try {
            if (
                extension_loaded('posix')
                && function_exists('posix_kill')
            ) {
                if (posix_kill($pid, $stopSignal)) {
                    return;
                }

                // posix_get_last_error() после УСПЕШНОГО вызова не
                // сбрасывается, поэтому читается только здесь, сразу после
                // отказа posix_kill(), который его всегда выставляет.
                $reason = posix_strerror(posix_get_last_error());
            } else {
                $output = [];
                $code = 0;

                @exec(
                    sprintf(
                        'kill -%s %s 2>/dev/null',
                        escapeshellarg((string)$stopSignal),
                        escapeshellarg((string)$pid)
                    ),
                    $output,
                    $code
                );

                if ($code === 0) {
                    return;
                }

                $reason = sprintf('kill exited with %d', $code);
            }
        } catch (\Throwable $exception) {
            $reason = $exception->getMessage();
        }

        if (false === static::isProcessAlive($pid)) {
            return;
        }

        $result->addError(new Error(sprintf(
            'Can not signal %d: %s',
            $pid,
            $reason
        )));
    }

    // endregion ////

    public function __construct(
        protected readonly string $group,
        protected readonly null|string $fileNamePrefix = null
    ) {
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
        if ($pid <= 0) {
            return false;
        }

        // Linux: самый прямой ответ, без прав и сигналов.
        if (is_dir('/proc')) {
            return is_dir('/proc/'.$pid);
        }

        if (
            extension_loaded('posix')
            && function_exists('posix_kill')
        ) {
            // Сигнал 0 ничего не делает, но проверяет доставимость.
            if (posix_kill($pid, 0)) {
                return true;
            }

            return match(posix_get_last_error()) {
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

        if (!is_dir($directory)) {
            return $removed;
        }

        // Обход в try: каталог есть, а прав на чтение может не быть — замки
        // создаёт первый запуск, и его umask методу не подчиняется. Исключение
        // отсюда роняло бы add(), то есть агент не стартовал бы вовсе, а
        // clearDir() зовётся из add() на КАЖДОМ прогоне.
        try {
            $iterator = new \DirectoryIterator($directory);
        } catch (\Throwable) {
            return $removed;
        }

        foreach ($iterator as $item) {
            if (
                $item->isDot()
                || $item->isLink()
                || !$item->isFile()
            ) {
                continue;
            }

            $file = new IO\File($item->getPathname());

            if ($file->getName() === $fileOri->getName()) {
                continue;
            }

            if ($file->getExtension() !== 'lock') {
                continue;
            }

            try {
                // Удаляем, только когда процесса ТОЧНО нет: true и null
                // оставляем.
                if (false !== static::isProcessAlive((int)trim($file->getContents()))) {
                    continue;
                }

                if ($file->delete()) {
                    $removed[] = $file->getPath();
                }
            } catch (\Throwable) {
                // Замок мог исчезнуть между листингом и чтением: процесс
                // группы завершился сам и снял его. Чистку остальных это не
                // отменяет.
                continue;
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

        if ($file->isExists()) {
            return true;
        }

        $response = $file->putContents($this->pid);
        if ($response === false) {
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

        if (!$file->isExists()) {
            return $result;
        }

        $response = $file->delete();
        if ($response === false) {
            return $result->addError(new Error(sprintf(
                'Problem delete file %s',
                $file->getPath()
            )));
        }

        return $result;
    }
    // endregion ////
}
