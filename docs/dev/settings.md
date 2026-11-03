# Страница настроек модуля линейки на shef.options

## Из чего состоит

| файл модуля | что |
|---|---|
| `options.php` | копия канона из shef.options, не править: рисует страницу, сохраняет значения |
| `options_conf.php` | описание вкладок и полей; возвращает `\Bitrix\Main\Result` |
| `lang/ru/options.php` | подписи |
| `.settings.php` → `requireModules`, `requirePhpExt` | из них сама собирается вкладка «Зависимости» |

## options_conf.php

```php
<?php

use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Options;

$response = ShOptionsConfig::getInstance(moduleId: 'acme.module');
if (!$response->isSuccess()) {
    return $response;
}

/** @var ShOptionsConfig $options */
$options = $response->getData()['OPTIONS'];

$options->addTab(
    (new Options\Tab('DEF'))
        ->setName(Loc::getMessage($options->moduleId.'_TAB_DEF_NAME'))
        ->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_TITLE'))
        ->addOption(
            (new Options\Checkbox('enabled'))
                ->setTitle(Loc::getMessage($options->moduleId.'_TAB_DEF_enabled'))
        )
);

return $response;
```

* `ShOptionsConfig::getInstance(string $moduleId): Result` — единственный
  параметр. Именованного аргумента, которого нет (например, `indexDoc` из 2.x),
  передавать нельзя: «Unknown named parameter», страница не открывается.
* `ShOptionsConfig::addTab(?Options\Tab $tab): static`, `getTab(string $code): ?Options\Tab`.
* `Options\Tab`: `__construct(string $code)`, `setName()`, `setTitle()`,
  `setIcon()`, `setDescription()`, `addOption(AOption $value)`.
* Типы полей — `\Shef\Options\Main\Options\*`, список — [../3_options.md](../3_options.md).
  Живой пример — `options_conf.php` в [bx-shef/problems](https://github.com/bx-shef/problems/blob/main/options_conf.php).

## Код опции и чтение значения

Код опции в `b_option` — `<код вкладки>_<код поля>`: вкладка `DEF`, поле
`enabled` → `DEF_enabled`. Читать — ядром:

```php
$enabled = \Bitrix\Main\Config\Option::get('acme.module', 'DEF_enabled', 'N') === 'Y';
```

Значения по умолчанию — `default_option.php` модуля. ID сотрудников из
настроек приводить строго (целое > 0), не `intval()` — как
`\Shef\Problems\Main\Constants::getIdOption()`.

## Вид страницы: карточки или вкладки

Канон `options.php` рисует страницу карточками (`\Shef\Options\Ui\SettingsForm`),
если форма умеет все поля страницы: `Checkbox`, `Text`, `NumberInt`,
`NumberFloat`, `TextArea`, `Enum*`, `RowInfo` — проверка
`SettingsForm::supports(iterable $tabs): bool`. Есть поле другого вида
(`Department`, `Users`…) — страница со вкладками. Имена полей формы —
`param_<ВКЛАДКА>_<код>`; сохраняются только пришедшие поля
(`SettingsForm::collect()`, флаг `SettingsForm::RENDERED`).

## Можно / нельзя

* Можно: свои вкладки и поля в `options_conf.php`, подписи в `lang/`.
* Нельзя: править свою копию `options.php` — правка делается в каноне
  shef.options и копируется. Кнопки «Вернуть значения по умолчанию» в каноне
  нет и не добавлять (решение владельца).
* Нельзя: класть секреты в `RowInfo` и описание поля открытым текстом. Для
  секретных полей у формы есть `SettingsForm::withSecrets()`, `mask()`,
  `keepSecrets()`.

## Как проверить

Страница `/bitrix/admin/settings.php?mid=<id модуля>&lang=ru` открывается,
поле сохраняется и читается через `Option::get()`. Без портала: тест
`options_conf.php` против заглушки API — как `tests/optionsconf_test.php` в
shef.problems. Шаг B [../portal-check.md](../portal-check.md).
