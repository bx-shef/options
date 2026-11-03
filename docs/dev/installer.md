# Установщик модуля линейки на каноне shef.options

## install/index.php

Установщик модуля — копия канона `install/index.php` shef.options (класс
`<vendor>_<module>`, наследник `CModule`). Что берётся из `.settings.php`
модуля: `installEvents` (обработчики событий), `installDir` (копии каталогов
`install/`). Зависимости объявляются свойствами класса:

```php
public $NEED_MODULES = ['crm'];
public $NEED_MODULES_BY_VERSION = ['shef.options' => '3.0.0'];
```

`DoInstall()` отказывает, если модуля из `NEED_MODULES` нет или версия ниже
указанной в `NEED_MODULES_BY_VERSION`. Те же свойства читает
`shef:modules:sync` (`\Shef\Options\Main\Modules\Sync::needs()`, рефлексией, без
конструктора): блокирует модуль, которому версии не хватает.

Сущности CRM и UF ставятся через `\Shef\Options\Installator\Manager` —
[../2_installer.md](../2_installer.md).

## install/layout.php — что модуль кладёт вне своего каталога

Для портала, который выкладывается из git: модуль описывает копии и файлы
вне своего каталога чистой функцией (без ядра):

```php
<?php

declare(strict_types=1);

return static fn (string $documentRoot, string $moduleDir): array => [
    'copy' => [['from' => '/install/js', 'to' => '/bitrix/js']],
    'files' => [],
    // 'blocks' => ['/.top.menu_ext.php' => ['begin', 'end', 'note', 'body']],
    // 'remove' => ['/bitrix/admin/old.php' => null],
];
```

| ключ | что |
|---|---|
| `copy` | каталоги `install/` → путь от корня сайта; должно совпадать с `installDir` |
| `files` | путь → текст файла (заглушки) |
| `blocks` | свой блок между метками в общем файле сайта; чужие блоки остаются (3.9.0+) |
| `remove` | что удалить: `null` — целиком, список md5 — только при совпадении (3.9.0+) |

Собирает и сверяет `\Shef\Options\Main\Modules\Layout::build(string $documentRoot, array $modules): array`;
обёртка — `php www/bitrix/modules/shef.options/tools/layout.php www [--write]`.
Ошибки раскладки — код 1, файлы не трогаются. Подробно и ограничения меток —
README, «Портал из git: раскладка без портала».

Можно / нельзя: в общих файлах сайта — только свой блок (`blocks`), не файл
целиком; ручных шагов выкладки не давать — всё через `install/layout.php`.

## Установка линейки одной командой

`php bitrix.php shef:modules:sync` — план (что поставит и обновит),
`--yes` — выполнить, `-m <id>` — только модуль и его зависимости. Порядок —
по `requireModules`, блокировка — по `NEED_MODULES_BY_VERSION`. README,
«Установка и обновление линейки одной командой».

## Как проверить

* Тест раскладки модуля: копии = `installDir`, файл подключается без ядра — как
  `tests/layout_test.php` в shef.options и shef.problems.
* `tools/layout.php www` без `--write` на копии портала: «+» нет, «~» другой, «-» удалить.
* На портале — [../portal-check.md](../portal-check.md).
