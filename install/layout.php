<?php

declare(strict_types=1);

/**
 * Раскладка shef.options вне каталога модуля (issue #55) — то же, что installDir в .settings.php:
 * стили страницы настроек install/css → /bitrix/css. Читает Main\Modules\Layout без ядра.
 */

return static fn (string $documentRoot, string $moduleDir): array => [
    'copy' => [['from' => '/install/css', 'to' => '/bitrix/css']],
    'files' => [],
];
