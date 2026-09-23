---
name: shef-use-component
description: Подключить готовый компонент из PHP-кода в любом модуле на базе shef.options — через Builder вместо $APPLICATION->IncludeComponent() с шестью аргументами, в том числе без вывода (только данные) и без ob_start. Брать на любую задачу «подключи компонент из кода», «вызови компонент из другого компонента», «получить данные компонента без HTML», «подключить в слайдере». Не для создания компонента (shef-new-component) и не для ajax (shef-new-ajax-action).
---

# Подключение компонента из кода

Операция: вызвать существующий компонент из PHP — из другого компонента,
из агента, из обработчика — правильно и без обходных путей.

## Правило номер один

**Данные и вывод — разные вещи.** Если из кода нужны данные, а не HTML,
их источник — не компонент, а сервисный класс в `lib/`, который компонент
сам и вызывает в `process()`. Второй компонент зовёт тот же сервис напрямую.
Компонент «без вывода» — признак того, что логика лежит не там.

Это первый вариант ответа на задачу «получить данные компонента». Если
логика уже в компоненте и вынести её сейчас нельзя — второй вариант ниже.

## Вариант 1 — сервис (канон)

```php
// lib/service/orderlist.php
namespace Acme\Demo\Service;

final class OrderList
{
    /** @return array<int, array<string, mixed>> */
    public static function forUser(int $userId, int $limit): array { /* … */ }
}

// components/acme.demo/order.list/class.php — process()
$this->arResult['ITEMS'] = OrderList::forUser($this->getUserId(), $this->arParams['COUNT']);

// другой компонент, агент, что угодно
$items = OrderList::forUser($userId, 20);
```

Ни `IncludeComponent`, ни буферов, ни зависимости от шаблона.

## Вариант 2 — Builder

`\Shef\Options\Components\Builder` — обёртка над подключением компонента:

```php
use Shef\Options\Components\Builder;

$builder = (new Builder('acme.demo:order.list'))
    ->setTemplate('')
    ->addOptionCollection('COUNT', 20)
    ->setParent($this);          // если зовёте из другого компонента

$builder->include();             // обычный вывод на странице
$builder->includeSlider();       // в слайдере
$builder->includeSmart();        // сам решит, слайдер или страница
$component = $builder->buildClass();   // объект класса компонента, без выполнения
```

`buildClass()` создаёт объект, инициализирует имя, шаблон и автозагрузку —
и **не** выполняет `executeComponent()`. Вывода нет. Дальше зовёте у объекта
его публичный метод, который отдаёт данные. Если такого метода у компонента
нет — добавьте его в компонент (публичный, без вывода), а не оборачивайте
`executeComponent()` буфером.

## Чего не делать

- `ob_start(); $APPLICATION->IncludeComponent(...); ob_end_clean();` — шаблон
  всё равно выполняется, css/js регистрируются, заголовки страницы меняются.
  Это не «без вывода», это «вывод выброшен».
- `IncludeComponent` с параметром «вернуть результат» на компонентах
  AComponent — базовый класс ничего не возвращает из `executeComponent()`.
- Класс компонента `new`-ом мимо `Builder` — не будет `initComponent()`,
  автозагрузки и родителя.

## Где лежит компонент

Компонент виден порталу только из `/local/components/<vendor>/<name>/` или
`/bitrix/components/<vendor>/<name>/`. Каталог внутри модуля
(`install/components/`) — это поставка, её копирует установщик
(`InstallFiles()`), см. shef-new-component. Если `Builder` бросает
«is not a component» — компонент не разложен, а не неправильно назван.

## Проверка

- `php -l` на файле с вызовом;
- страница с вызывающим кодом не содержит HTML компонента (`curl` и
  поиск по классу-обёртке шаблона);
- в коде нет `ob_start` вокруг подключения компонента.

## В конце

Оставьте отзыв о навыке — shef-feedback.
