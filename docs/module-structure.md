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
| `install/css/`, `install/js/` | SHIP | фронт; установщик раскладывает его в `/bitrix/css` и `/bitrix/js` |
| `.settings.php` | SHIP | настройки модуля: ajax-контроллеры, карта раскладки `installDir` |
| `include.php` | SHIP | точка входа модуля: подключает `autoload.php` и `register-js.php` |
| `autoload.php` | SHIP | зависимости модуля и регистрация чужих namespace |
| `project-context.php` | SHIP | знает, есть ли на проекте Composer и где его `vendor` |
| `options.php`, `options_conf.php`, `optionsconfig.php` | SHIP | страница настроек модуля |
| `lib/` | SHIP | классы модуля, **имена файлов строго строчными** |
| `lang/ru/` | SHIP | языковые файлы, зеркалят структуру `lib/` |
| `vendor/Michelf/` | SHIP | вендорённый php-markdown, подключается через `registerAutoLoadClasses` |
| `README.md`, `CHANGELOG.md`, `CLAUDE.md`, `LICENSE` | SHIP | |
| `composer.json` | SHIP | манифест пакета |
| `docs/` | KEEP | вся документация, и модуля, и репозитория |
| `build.sh` | KEEP | сборка и проверки |
| `tests/` | KEEP | тесты |
| `.github/` | KEEP | CI и релиз |
| `CONTRIBUTING.md` | KEEP | |
| `.gitattributes`, `.gitignore` | KEEP | |

## Почему `docs/` не едет на портал

Документация сведена в репозиторий целиком. На портал уезжает только
`README.md` — его рендерит вкладка «Документация» в настройках модуля, — а все
ссылки из него ведут на GitHub.

Так документация на экране у пользователя всегда свежая, а не той версии, что
когда-то поставили. Цена — ссылки уводят из админки в браузер, и это осознанный
размен.

Внешние ссылки не должны попадать в ajax-загрузчик документов: он ищет файл в
каталоге модуля. Адрес документа на GitHub тоже кончается на `.md`, поэтому
`script.js` решает по схеме URL, а не по расширению. Проверяется в
`tests/markdown_links_test.mjs` — там же ловится расхождение `script.js` и
`script.min.js`, которые едут в поставку обе.

## Нижний регистр в `lib/` обязателен

`Bitrix\Main\Loader` отображает класс в путь **строчными**, разбирая первые два
сегмента namespace как id модуля: `Shef\Options\Main\Utils` ищется как
`bitrix/modules/shef.options/lib/main/utils.php`. Отсюда же пустой
`registerNamespace` в `.settings.php` — он нужен только для чужих namespace.

На macOS заглавная буква сходит с рук, на боевом Linux класс просто не найдётся.
Проверяется в `build.sh`, `check_lowercase`.

## Несимметричные имена каталогов фронта

```
install/css/shef.options/     -> /bitrix/css/shef.options/      (точка)
install/js/shef-options/      -> /bitrix/js/shef-options/       (дефис)
```

Точка — id модуля, дефис — требование имён расширений Битрикса:
`/bitrix/js/shef-options/options-markdown` грузится как
`shef-options.options-markdown`. Так и надо, «чинить» не нужно.

Оба пути выводятся из `MODULE_ID` в `Constants::getPublicCssDir()` и
`getPublicJsDir()` и больше нигде строкой не пишутся. Сходимость с раскладкой
установщика проверяет `tests/assets_test.php`.
