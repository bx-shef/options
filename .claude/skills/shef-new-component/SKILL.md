---
name: shef-new-component
description: Создать компонент Битрикс по канону линейки shef.* — на базе AComponent из shef.options, с готовым жизненным циклом, разбором параметров, проверкой обязательных и сбором ошибок в коллекцию вместо исключений. Брать на любую задачу «вывести на странице список/блок/форму/таблицу», «нужен компонент для …», а также когда существующий компонент надо привести к канону. Для Битрикс24 и БУС. Не для ajax-обработчиков — для них shef-new-ajax-action.
---

# Новый компонент

Операция: собрать компонент на `\Shef\Options\Components\AComponent` вместо
голого `\CBitrixComponent`. Разница не в удобстве: голый компонент оставляет
разработчику весь жизненный цикл, и он каждый раз пишется заново и каждый раз
чуть иначе.

Нужен ajax — берите `shef-new-ajax-action`, он про то же, но с контроллером.

## Раскладка

```
install/components/<вендор>/<имя.компонента>/
├── .description.php        подпись в визуальном редакторе
├── .parameters.php         параметры для редактора
├── class.php               ваш класс
├── lang/ru/...
└── templates/.default/
    ├── template.php
    ├── result_modifier.php     (по необходимости)
    └── component_epilog.php    (по необходимости)
```

Имя компонента в коде — `<вендор>:<имя.компонента>`, например
`shef.demo:order.list`.

## Класс

```php
use Shef\Options\Components\AComponent;

class ShefDemoOrderListComponent extends AComponent
{
    protected static function getModulesList(): array
    {
        return ['shef.options', 'sale'];
    }

    protected function initParams(): void
    {
        $this->arParams['COUNT'] = (int)($this->arParams['COUNT'] ?? 20);
    }

    protected function checkRequiredParams(): void
    {
        if($this->arParams['COUNT'] < 1)
        {
            $this->addError(new \Bitrix\Main\Error('COUNT должен быть больше нуля'));
        }
    }

    protected function setPageProperty(): void
    {
        \Shef\Options\Main\Utils::getCMainApplication()->SetTitle('Заказы');
    }

    protected function process(): void
    {
        $this->arResult['ITEMS'] = [];
    }
}
```

Обязателен только `process()` — остальное крючки с пустой реализацией.

## Порядок, который вы не пишете

`executeComponent()` уже написан и идёт по шагам:

1. `initErrorCollection()` — коллекция ошибок;
2. `initAutoloader()` — автозагрузка классов рядом с компонентом;
3. `includeModules()` по `getModulesList()` — **не загрузился, дальше не идём**;
4. `initParams()`;
5. `checkRequiredParams()` — **есть ошибки, дальше не идём**;
6. `setPageProperty()`;
7. `initResult()`;
8. `process()` — **есть ошибки, дальше не идём**;
9. `renderTemplate()`.

На каждом «дальше не идём» зовётся `printErrors()`, и шаблон **не
подключается**.

## Три вещи, на которых спотыкаются

**Ошибку кладут в коллекцию, а не бросают.** `$this->addError(new Error(...))`
и `return` из `process()`. Брошенное исключение выйдет за пределы компонента:
пользователь получит белый экран вместо текста, а на бою — ещё и без текста в
логе, если включён продакшен-режим.

**`initParams()` обязан отработать до `initResult()`.** Это порядок в базовом
классе, и в коде на нём стоит отдельная пометка. Разбирать `arParams` внутри
`initResult()` или `process()` — значит однажды собрать результат по
неразобранным параметрам.

**Приведение типом на параметрах врёт.** `(int)'1 200'` даст `1`,
`(float)'1 234,50'` даст `1.0`. Если параметр приходит от человека или из
внешней системы, берите трейт `\Shef\Options\TraitList\Tools\PrepareFields` —
он разбирает пробелы, неразрывные пробелы и запятую как разделитель, а на
отсутствующем обязательном поле бросает `ArgumentNullException` с именем поля.
См. skill `shef-options-traits`.

## Подключить из кода

Вместо `$APPLICATION->IncludeComponent()` с шестью аргументами:

```php
(new \Shef\Options\Components\Builder('shef.demo:order.list'))
    ->setTemplate('')
    ->addOptionCollection('COUNT', 50)
    ->setIsActive(true)
    ->include();
```

`buildClass()` вместо `include()` отдаст объект класса компонента без вывода —
когда из кода нужна логика, а не разметка.

## Вёрстка шаблона

В Битрикс24 у платформы **свой дизайн-язык**: классы ядра `ui-btn`,
`ui-alert`, `ui-form` и расширения модуля `ui`. Компонент, свёрстанный
бутстрапом, в портале выглядит чужим. Подключается расширение через
`\Bitrix\Main\UI\Extension::load('ui.buttons')` и подобные.

Для БУС такого ограничения нет — там вёрстка ваша.

## Проверка

Тесты берут только то, что ниже рантайма: раскладку файлов и соглашения имён.
Сам компонент проверяется на стенде:

1. поставить на страницу, открыть — выводится;
2. убрать обязательный параметр — на странице **текст ошибки**, а не пустота
   и не белый экран;
3. отключить модуль из `getModulesList()` — тот же понятный текст;
4. после правок шаблона — Ctrl+F5: путь `/bitrix/cache/js/s1/...` в консоли
   означает, что вы смотрите на кеш, а не на свою правку.
