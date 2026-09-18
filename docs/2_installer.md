# [`\Shef\Options\Installator`] Installer

Для облегчения установок.

## [`Installator`] Интерфейсы
|                 Название | Описание                                                                             |
|-------------------------:|:-------------------------------------------------------------------------------------|
| Installator\IInstallator | Интерфейс установщика                                                                |
|      Installator\IEntity | Описывает устанавливаемую сущность                                                   |
|    Installator\IEntityUf | Интерфейс для перечисления UF сущности. В Стратегии установки влияет на установку UF |
|  Installator\ISaveOption | Интерфейс указывает что ID новой сущности нужно сохранить в свойство после создания  |


## [`Installator\Manager`] Установщик
Получает на вход стратегию `Installator\Strategy\IStrategy` установки.

* `Installator\Manager::build` устанавливает коллекцию сущностей
* `Installator\Manager::process` устанавливает сущность

## [`Installator\Strategy\IStrategy`] Стратегии установки
|                          Название | Описание                                                              |
|----------------------------------:|:----------------------------------------------------------------------|
| Strategy\SmartProcessTypeStrategy | Реализует установку типа смарт-процесса                               |
|        Strategy\CrmPresetStrategy | Реализует установку пресета реквизитов                                |
|              Strategy\UfStrategy | Реализует установку UF через `Bitrix\Main\Controller\UserFieldConfig` |
|            Strategy\UfOldStrategy | Реализует установку UF через старые функции                           |

## [`Installator\Entity`] Сущности
Эти сущности будет установлены

|                       Название | Описание                                              |
|-------------------------------:|:------------------------------------------------------|
|                 **Entity\Crm** |                                                       |
|   Entity\Crm\ASmartProcessType | Абстракция для смартпроцессов                         |
| Entity\Crm\ASmartProcessTypeUf | Абстрацкия UF для смартпроцессов                      |
|             Entity\Crm\APreset | Абстракция для пресетов реквизитов                    |
|         Entity\Crm\PresetField | Описывает поле пресета реквизита                      |
|                  **Entity\UF** |                                                       |
|              Entity\UF\AEntity | Абстрацкия UF                                         |
|          Entity\UF\AEntityEnum | Абстрацкия UF типа перечисление                       |
|             Entity\UF\EnumItem | Реализация элемента перечисления UF типа перечисление |
|          Entity\UF\IEnumStatus | Интерфейс перечисления для статусов                   |
|          Entity\UF\EEnumStatus | Перечисление для статусов                             |
|            Entity\UF\EEntityId | Перечисление объектов к которым можно привязать UF    |
|               Entity\UF\EType | Перечисление типов UF                                |
|         **Entity\UF\Strategy** | Стратегии получения настроек UF                       |
|                                | Под каждый тип UF своя стратегия                      |

## [`Installator\Trait`] Трейты

|                Название | Описание                                                                 |
|------------------------:|:--------------------------------------------------------------------------|
| Trait\EntityUfTrait | Перечисление UF сущности для тех, кто реализует `Installator\IEntityUf` |

## Две ветки установки UF — и это не дубль

В модуле живут две независимые реализации, и путать их нельзя:

* `Installator\Entity\UF\*` со стратегиями `Installator\Entity\UF\Strategy\*` —
  та, что описана выше: сущность несёт своё описание, стратегия под каждый тип
  UF отдаёт настройки;
* `Installator\Uf\*` — своя ветка со своим `Installator\Uf\Manager`, типами
  `Installator\Uf\Type\*` и стратегиями `Installator\Uf\Type\Strategy\*`.

`Strategy\UfOldStrategy` — не «устаревшая копия» `UfStrategy`, а рабочая
стратегия установки UF через старые функции ядра. Она живёт в новой ветке как
запасной путь, когда `\Bitrix\Main\Controller\UserFieldConfig` неприменим.

[↑ Содержание](../README.md) | [Опции настроек модуля →](3_options.md)