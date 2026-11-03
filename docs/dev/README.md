# shef.options для разработчиков модулей линейки

Вход для программиста и его AI-агента: что shef.options даёт соседям и где это
описано. Подробности — в тематических файлах `docs/`, здесь они не
копируются. Карта всей линейки — [toolsai/docs/dev/llms.txt](https://github.com/bx-shef/toolsai/blob/main/docs/dev/llms.txt).

shef.options — базовый модуль: его ставят первым, остальные модули `shef.*`
требуют его в `requireModules` и в `NEED_MODULES_BY_VERSION` установщика.

## Что модуль даёт соседям

| что | точка входа | подробно |
|---|---|---|
| Страница настроек модуля из описания | `options_conf.php` модуля → `ShOptionsConfig`, `\Shef\Options\Main\Options\*`, канон `options.php`, `\Shef\Options\Ui\SettingsForm` | [settings.md](settings.md), [../3_options.md](../3_options.md) |
| Канон установщика, зависимости, раскладка вне каталога модуля, установка линейки одной командой | `install/index.php` (копия канона), `install/layout.php`, `\Shef\Options\Main\Modules\Layout`, `php bitrix.php shef:modules:sync` | [installer.md](installer.md), [../2_installer.md](../2_installer.md) |
| Установка сущностей CRM и UF | `\Shef\Options\Installator\Manager` и стратегии | [../2_installer.md](../2_installer.md) |
| Трейты: модули, события, константы, инструменты, лог | `\Shef\Options\TraitList\*` | [../9_traitlist.md](../9_traitlist.md) |
| Компоненты и ajax | `\Shef\Options\Components\*` | [../6_components.md](../6_components.md) |
| Пользователь, контекст, подмена в cron | `\Shef\Options\Main\Context`, `Main\Security`, `TraitList\Security\FixUser` | [../4_security.md](../4_security.md) |
| Утилиты | `\Shef\Options\Main\Utils` | [../5_utils.md](../5_utils.md) |
| Singleton, реестр, SmartStd | `\Shef\Options\Options\*` | [../7_pattern.md](../7_pattern.md) |
| Служебный пользователь | `\Shef\Options\Main\Constants::getSystemUserId()` | CHANGELOG 3.5.0 |
| Отладка `_pr`, `_log`, `_log1`, каталог логов `shef_options_log_dir()` | `def-functions.php` | CHANGELOG 3.7.0 |
| Диагностика портала по модулям линейки | `php bitrix.php shef:modules:diag`, `tools/diag.php` | [README](../../README.md), «Диагностика портала по модулям линейки» |
| Устройство модуля, сборка, проверка на портале | — | [../module-structure.md](../module-structure.md), [../build-and-install.md](../build-and-install.md), [../portal-check.md](../portal-check.md) |

## Файлы docs/dev

| файл | о чём |
|---|---|
| [settings.md](settings.md) | как модуль линейки строит страницу настроек на shef.options и читает значения |
| [installer.md](installer.md) | канон установщика, зависимости по версиям, `install/layout.php`, `shef:modules:sync` |

## Навыки для AI-агентов

Пошаговые рецепты — навыки `shef-new-option`, `shef-options-settings`,
`shef-options-traits`, `shef-options-components`, `shef-options-installer`,
`shef-options-blocks` в [bx-shef/skills](https://github.com/bx-shef/skills)
(копия — `.claude/skills/`).

## Ограничения

* PHP 8.2+, портал в UTF-8 (README, «Что нужно для установки»).
* REST-методов модуль не даёт.
* Внутреннее — `lib/cli/command/*`, `Main\Modules\Planner`, `Main\Modules\Sync`
  (работают через консольные команды), `Ui\Menu`, `Ui\Page`: вызывать из своих
  модулей не нужно, сигнатуры меняются без мажорной версии.
