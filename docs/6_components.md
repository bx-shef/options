# [`\Shef\Options\Components`] Работа с компонентами

## Соглашение о наименовании
1. компоненты складываем в папку `/local/vendor.modulename/custom.name`
   1. namespace `Local\Component\Vendor\ModuleName`
   2. для компонента class `CustomNameComponent`
   3. для ajax class `CustomNameAjaxController`
2. используем для .js
   1. namespace `BX.namespace('BX.VendorModuleName');`
   2. class `BX.VendorModuleName.CustomNameController`
   3. объект `BX.VendorModuleName.CustomName`

## Классы

|                                 Класс | Описание                                                                                                                      |
|--------------------------------------:|-------------------------------------------------------------------------------------------------------------------------------|
|                    Components\Builder | Класс для работы с компонентами<br/>Умеет подключать (просто, через слайдер, автоматически)<br/>Умеет создавать объект класса |
|                 Components\AComponent | Абстракция для компонента                                                                                                     |
|            Components\AControllerable | Абстракция для компонента с поддержкой ajax                                                                                   |
|              Components\AjaxProcessor | Абстракция для обработки ajax запросов вне компонента                                                                         |
|                **Components\Actions** | **Набор проверок для ajax запросов**                                                                                          |
| Components\Actions\IActionsFilterList | Интерфейс для получения действий                                                                                              |
|               Components\Actions\Free | Без проверки прав доступа                                                                                                     |
|             Components\Actions\Normal | Обычная проверка прав доступа                                                                                                 |
|                     **Дополнительно** |                                                                                                                               |
|                Components\IAutoloader | Интерфейс для подключения в компоненте механизма автозагрузки                                                                 |
|                     Components\IClass | Интерфейс для указания что компонент содержит файл class.php                                                                  |
|                      Components\IAjax | Интерфейс для указания что компонент содержит файл ajax.php                                                                   |
|                  **Components\Trait** | **Набор трейтов**                                                                                                             |
|   Components\Trait\ComponentNameTrait | Trait для обработки названий компонента                                                                                       |
|      Components\Trait\AutoloaderTrait | Trait для подключения в компоненте механизма автозагрузки                                                                     |

## Использование механизма автозагрузчика в компоненте
Объявляем интерфейс `Components\IAutoloader` и реализуем его через трейты `Components\Trait\ComponentNameTrait` и `Components\Trait\AutoloaderTrait` .
> Отдельно предусматриваем механизм подключения класса из файла `ajax.php`

[← Утилиты](5_utils.md) | [↑ Содержание](../README.md) | [Паттерны →](7_pattern.md)