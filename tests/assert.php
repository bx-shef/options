<?php

declare(strict_types=1);

/**
 * Крошечная обвязка для тестов: ни зависимостей, ни фреймворка.
 *
 * Тесты гоняются как обычные скрипты — `php tests/<имя>_test.php`, — и их же
 * зовёт build.sh, а значит и CI. Ненулевой код возврата означает провал.
 *
 * Warning и notice превращаются в провал. PHP 8 их не роняет, а печатает и
 * идёт дальше: обращение к несуществующему ключу массива, деление на ноль,
 * неявное приведение — всё это прошло бы мимо теста, хотя на портале
 * выглядело бы мусором в логе и неверным поведением.
 */

final class Check
{
    /** @var string[] */
    private static array $errors = [];
    /** @var string[] */
    private static array $skipped = [];
    private static int $skippedChecks = 0;
    private static int $count = 0;

    public static function boot(): void
    {
        // Обработчик пропускает уровни, которых нет в error_reporting(), —
        // так отличается заглушённое «@» (см. ниже). Но маску задаёт php.ini,
        // и на CI это E_ALL & ~E_DEPRECATED: deprecation ядра молча проходила
        // мимо. Измерено: file_put_contents($f, 'x', null) — deprecated с
        // 8.1 — тест не ронял. Поэтому маску задаём сами, а не берём у
        // окружения: иначе обещание «warning и notice — это провал» зависит
        // от того, на какой машине запустили.
        error_reporting(E_ALL);

        set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
            // Заглушённое «@» — не провал: обработчик зовётся и для него, а
            // error_reporting() в этот момент не содержит уровня ошибки. Так
            // делает и ядро. Нашлось в shef.problems: Monolog штатно глушит
            // @fileinode() на файле, который только что переименовали.
            if (!(error_reporting() & $level)) {
                return false;
            }

            throw new ErrorException($message, 0, $level, $file, $line);
        });
    }

    public static function group(string $title): void
    {
        echo PHP_EOL, $title, PHP_EOL;
    }

    /**
     * Строгое сравнение, без приведения типов: '0' и 0 — разные вещи, и
     * именно на таком приведении обычно и разъезжается разбор настроек.
     */
    public static function same(string $what, mixed $actual, mixed $expected): void
    {
        static::$count++;

        if ($actual === $expected) {
            printf("  OK   %s = %s%s", $what, static::show($actual), PHP_EOL);
            return;
        }

        static::fail(sprintf(
            '%s: получено %s, ожидалось %s',
            $what,
            static::show($actual),
            static::show($expected)
        ));
    }

    public static function throws(string $what, string $exceptionClass, callable $call): void
    {
        static::$count++;

        try {
            $call();
        } catch (Throwable $throwable) {
            if ($throwable instanceof $exceptionClass) {
                printf("  OK   %s бросает %s%s", $what, $exceptionClass, PHP_EOL);
                return;
            }

            static::fail(sprintf(
                '%s: брошено %s, ожидалось %s',
                $what,
                get_class($throwable),
                $exceptionClass
            ));

            return;
        }

        static::fail(sprintf('%s: исключение %s не брошено', $what, $exceptionClass));
    }

    /**
     * Проверка не выполнялась — и это НЕ «пройдено».
     *
     * Нужна там, где проверка зависит от окружения и под ним бессмысленна:
     * права файловой системы под root не работают — каталог без прав на
     * чтение читается, — и проверка молча зеленела бы. CI гоняет от обычного
     * пользователя, значит там она выполняется; локально под root
     * пропускается ВСЛУХ.
     *
     * Пропущенное считается отдельно и печатается в итоге. Пропущенная
     * проверка, засчитанная за пройденную, — отдельная ловушка, за которую в
     * этом репозитории уже заплачено дважды: защитой ветки и build.sh.
     *
     * $checks — сколько проверок НЕ выполнилось. Пропускают обычно группу
     * целиком, и без числа итог врал бы в другую сторону: «пропущено 3», а
     * на деле молча исчезли 7 проверок. Сторожит tests/assert_test.php.
     */
    public static function skip(string $what, string $why, int $checks = 1): void
    {
        static::$skipped[] = sprintf('%s — %s', $what, $why);
        static::$skippedChecks += $checks;

        printf("  SKIP %s — %s (проверок: %d)%s", $what, $why, $checks, PHP_EOL);
    }

    private static function fail(string $message): void
    {
        static::$errors[] = $message;
        printf("  FAIL %s%s", $message, PHP_EOL);
    }

    private static function show(mixed $value): string
    {
        if (is_object($value)) {
            return get_class($value).' '.json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return var_export($value, true);
    }

    public static function finish(): never
    {
        echo PHP_EOL;

        if (!empty(static::$errors)) {
            printf('Провалено %d из %d:%s', count(static::$errors), static::$count, PHP_EOL);
            foreach (static::$errors as $error) {
                echo '  * ', $error, PHP_EOL;
            }

            if (!empty(static::$skipped)) {
                printf('Пропущено проверок: %d%s', static::$skippedChecks, PHP_EOL);
            }

            exit(1);
        }

        printf('Проверок пройдено: %d%s', static::$count, PHP_EOL);

        if (!empty(static::$skipped)) {
            printf('Пропущено проверок: %d%s', static::$skippedChecks, PHP_EOL);
            foreach (static::$skipped as $skipped) {
                echo '  * ', $skipped, PHP_EOL;
            }
        }

        exit(0);
    }
}

Check::boot();
