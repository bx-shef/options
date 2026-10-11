<?php

declare(strict_types=1);

/**
 * Обвязка сама: Check::skip() не сходит за пройденное.
 *
 * Check::skip() заведён ровно против ловушки «пропущенная проверка выглядит
 * как пройденная» — и сам ничем не сторожился. Панель замерила: добавить в
 * skip() «$count++» или выкинуть из него печать — все тесты зелёные, код
 * возврата 0. Сторож без сторожа.
 *
 * Тест гоняет настоящую обвязку в отдельном процессе на крошечном сценарии
 * и сверяет её вывод и код возврата. В своём процессе этого не сделать:
 * Check::finish() завершает процесс.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$sandbox = sys_get_temp_dir().'/shef-options-assert-'.getmypid();

register_shutdown_function(static function () use ($sandbox): void {
    exec('rm -rf '.escapeshellarg($sandbox));
});

exec('rm -rf '.escapeshellarg($sandbox));
mkdir($sandbox, 0o777, true);

/** Сценарий в отдельном процессе: [вывод, код возврата]. */
$run = static function (string $body) use ($root, $sandbox): array {
    $script = $sandbox.'/scenario.php';
    file_put_contents($script, '<?php require '.var_export($root.'/tests/assert.php', true).";\n".$body);

    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output, $code);

    return [implode("\n", $output), $code];
};

Check::group('пропуск не засчитывается за пройденное');

[$out, $code] = $run("Check::same('x', 1, 1);\nCheck::skip('группа', 'причина', 3);\nCheck::finish();");

Check::same('код возврата ноль', $code, 0);
Check::same('пройдена ровно одна', str_contains($out, 'Проверок пройдено: 1'), true);
Check::same('пропуск назван', str_contains($out, 'SKIP группа'), true);

// Число проверок, а не групп: пропускают обычно группу целиком.
Check::same('пропущено считается проверками', str_contains($out, 'Пропущено проверок: 3'), true);

Check::group('пропуск виден и при провале');

[$out, $code] = $run("Check::same('x', 1, 2);\nCheck::skip('группа', 'причина', 2);\nCheck::finish();");

Check::same('код возврата ненулевой', $code, 1);
Check::same('пропуск в итоге', str_contains($out, 'Пропущено проверок: 2'), true);

Check::group('без пропусков строки нет');

[$out, $code] = $run("Check::same('x', 1, 1);\nCheck::finish();");

Check::same('код возврата ноль', $code, 0);
Check::same('строки «Пропущено» нет', str_contains($out, 'Пропущено'), false);

Check::finish();
