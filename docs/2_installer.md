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
|          Strategy\UserFieldConfig | Реализует установку UF через `Bitrix\Main\Controller\UserFieldConfig` |
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
|         Entity\Crm\PresetField | Описывает поле пресета реквизита                      |
|                  **Entity\UF** |                                                       |
|              Entity\UF\AEntity | Абстрацкия UF                                         |
|          Entity\UF\AEntityEnum | Абстрацкия UF типа перечисление                       |
|             Entity\UF\EnumItem | Реализация элемента перечисления UF типа перечисление |
|          Entity\UF\IEnumStatus | Интерфейс перечисления для статусов                   |
|          Entity\UF\EEnumStatus | Перечисление для статусов                             |
|            Entity\UF\EEntityId | Перечисление объектов к которым можно привязать UF    |
|         **Entity\UF\Strategy** | Стратегии получения настроек UF                       |
|                                | Под каждый тип UF своя стратегия                      |

[↑ Содержание](README.md) | [Опции настроек модуля →](docs/3_options.md)