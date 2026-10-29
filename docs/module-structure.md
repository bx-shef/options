# Раскладка репозитория

Файл про устройство репозитория. Опорные точки модуля — в [CLAUDE.md](../CLAUDE.md),
процесс — в [CONTRIBUTING.md](../CONTRIBUTING.md), сборка — в
[build-and-install.md](build-and-install.md).

## Модуль лежит в корне, и это вынужденно

Composer разворачивает в целевой каталог **корень пакета целиком** и подкаталоги
выбирать не умеет. Поэтому `lib/`, `install/`, `lang/` лежат прямо в корне
репозитория, рядом с `build.sh` и `.github/`, а не в отдельном подкаталоге вроде
`src/`.

Плата за это — два списка в шапке `build.sh`:

* **SHIP** — уезжает на портал и в Composer-пакет;
* **KEEP** — остаётся в репозитории.

**Файл, не попавший ни в один список, роняет сборку.** Это единственная
страховка такой раскладки: без неё новый файл однажды уехал бы на портал молча.

Тот же список продублирован в `.gitattributes` через `export-ignore` — он решает,
что попадёт в Composer-пакет, потому что `git archive` его соблюдает. Списки
обязаны совпадать, иначе на портал уедет разное в зависимости от способа
установки. Сверяется автоматически и в обе стороны, см. `check_gitattributes`.

## Что где лежит

| путь | | что это |
|---|---|---|
| `install/index.php` | SHIP | установщик, класс `shef_options extends CModule` |
| `install/version.php` | SHIP | `VERSION` и `VERSION_DATE` — источник истины о версии |
| `install/css/` | SHIP | стили страницы настроек; установщик раскладывает их в `/bitrix/css` |
| `install/layout.php` | SHIP | раскладка модуля вне его каталога (то же, что `installDir`) — читает `tools/layout.php` без ядра |
| `.settings.php` | SHIP | настройки модуля: карта раскладки `installDir`, консольные команды (`console`); список ajax-контроллеров пуст |
| `default_option.php` | SHIP | значения опций по умолчанию |
| `register-js.php` | SHIP | регистрирует расширение `shef-options-admin` (один css); подключается из `include.php` |
| `include.php` | SHIP | точка входа модуля: подключает `autoload.php`, `def-functions.php` и `register-js.php` |
| `def-functions.php` | SHIP | глобальные `_log()` и `_pr()`, которые зовёт трейт `TraitList\Log` |
| `autoload.php` | SHIP | зависимости модуля и регистрация чужих namespace |
| `project-context.php` | SHIP | знает, есть ли на проекте Composer и где его `vendor` |
| `options.php`, `options_conf.php`, `optionsconfig.php` | SHIP | страница настроек модуля |
| `lib/` | SHIP | классы модуля, **имена файлов строго строчными**; ключевые узлы — ниже |
| `tools/layout.php` | SHIP | CLI без ядра: собирает `install/layout.php` модулей `shef.*` и сверяет или пишет (`--write`) их файлы в дереве портала |
| `lang/ru/` | SHIP | языковые файлы, зеркалят структуру `lib/` |
| `README.md`, `CHANGELOG.md`, `LICENSE` | SHIP | |
| `composer.json` | SHIP | манифест пакета |
| `docs/` | KEEP | вся документация, и модуля, и репозитория |
| `build.sh` | KEEP | сборка и проверки |
| `tests/` | KEEP | тесты |
| `examples/` | KEEP | запускаемые примеры к строительным блокам |
| `.check/` | — | в git нет (`.gitignore`): checkout'ы сверки навыков из `skills.yml` |
| `.github/` | KEEP | CI и релиз |
| `CONTRIBUTING.md` | KEEP | |
| `CLAUDE.md` | KEEP | памятка агенту: она про репозиторий, а не про модуль |
| `.gitattributes`, `.gitignore` | KEEP | |
| `.php-cs-fixer.dist.php` | KEEP | правила форматирования; гоняются `composer run lint`, не из `build.sh` |
| `composer.lock` | KEEP | версии инструментов разработчика; на зависимости пакета не влияет |

## Почему документация не едет на портал

Документация живёт в репозитории целиком. В поставке из неё остаётся только
`README.md` — как readme пакета, — и все ссылки из него ведут на GitHub.

Модуль не рендерит документацию в настройках: в поставке нет markdown-рендерера,
`install/js/` и ajax-контроллеров. Документация на GitHub всегда свежая, а не той
версии, что стоит на портале, — вторая копия внутри модуля только расходилась бы
с первой.

## Ключевые узлы `lib/`

| путь | что это |
|---|---|
| `lib/main/modules/` | `Sync`, `Planner`, `Layout` — установка и обновление модулей линейки после выкладки файлов и раскладка их файлов вне каталога модуля |
| `lib/cli/command/modules/` | команды `php bitrix/bitrix.php shef:modules:sync` и установки одного модуля; зарегистрированы в `.settings.php` → `console` |
| `lib/ui/settingsform.*` | форма настроек карточками: `options.php` рисует её, когда `supports()`, иначе — вкладки `CAdminTabControl` |
| `lib/main/options/` | типы опций страницы настроек |
| `lib/options/` | `Config`, `Singleton`, `SmartStd` — чтение настроек модулей |
| `lib/installator/` | строительные блоки установщика: UF, CRM (пресеты, смарт-процессы), заказ, ORM |
| `lib/components/` | `AComponent`, `AControllerable`, `AjaxProcessor`, фильтры `Actions\Normal` и `Actions\Free` |
| `lib/integration/` | базовые классы событий, инфоблоков и тестов заказа |
| `lib/main/tempfile/` | временные файлы и замок `Pid` от двойного запуска |
| `lib/traitlist/` | трейты, см. [9_traitlist.md](9_traitlist.md) |

## Нижний регистр в `lib/` обязателен

`Bitrix\Main\Loader` отображает класс в путь **строчными**, разбирая первые два
сегмента namespace как id модуля: `Shef\Options\Main\Utils` ищется как
`bitrix/modules/shef.options/lib/main/utils.php`. Отсюда же пустой
`registerNamespace` в `.settings.php` — он нужен только для чужих namespace, а
своих у модуля нет. Читает этот ключ `autoload.php`.

`project-context.php` выглядит частью той же механики, но ею не является:
файл объявляет глобальный `ShComposerContext` и едет в поставку, однако в самом
модуле его никто не подключает — `registerNamespace` задан пустым литералом.
Это заготовка для модулей линейки, чей `.settings.php` может собрать список
путей Composer через него.

На macOS заглавная буква сходит с рук, на боевом Linux класс просто не найдётся.
Проверяется в `build.sh`, `check_lowercase`.

## Несимметричные имена каталогов фронта

```
install/css/shef.options/     -> /bitrix/css/shef.options/      (точка)
     (js)  shef-options/      -> /bitrix/js/shef-options/       (дефис)
```

Точка — id модуля, дефис — требование имён расширений Битрикса: каталог
`/bitrix/js/shef-options/options-markdown` грузился бы как расширение
`shef-options.options-markdown`.

Своего JS модуль с 3.0.0 не раскладывает — `install/js/` в репозитории нет, —
но `getPublicJsDir()` остался: по нему установщик убирает каталог, оставшийся
на порталах от прежних версий.

Оба пути выводятся из `MODULE_ID` в `Constants::getPublicCssDir()` и
`getPublicJsDir()` и больше нигде строкой не пишутся. Сходимость с раскладкой
установщика проверяет `tests/assets_test.php`.
