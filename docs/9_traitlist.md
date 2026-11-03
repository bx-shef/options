# [`\Shef\Options\TraitList`] Набор трейтов

|                            Класс | Описание                                                                                                    |
|---------------------------------:|-------------------------------------------------------------------------------------------------------------|
|                    TraitList\Log | Запись в лог через `_log()`; каталог — `shef_options_log_dir()` (вне корня сайта) |
|                TraitList\Modules | Используется для подключения модулей                                                                        |
|                 TraitList\Events | Используется в событиях для блокировок от повторных вызовов                                                 |
|          TraitList\EventResponse | Используется в событиях для возврата значений событий                                                       |
|          **TraitList\Constants** | **Стоит использовать при определении констант**                                                             |
|      TraitList\Constants\Catalog | Используется для получения данных каталога                                                                  |
|        TraitList\Constants\Price | Используется для получения данных цен и валют                                                               |
|         TraitList\Constants\Site | Используется для получения данных текущего сайта                                                            |
|         TraitList\Constants\User | Используется для получения данных о сотрудниках                                                             |
|              **TraitList\Tools** | **Полезные расширения**                                                                                     |
|         TraitList\Tools\DateTime | Трейт для работы с текущей ДатойВремя.<br/>Хранит форматы Дата и ДатаВремя.<br/>Инициализирует текущую дату |
|         TraitList\Tools\Encoding | Трейт для работы с кодировкой.<br/>Преобразует в текущую кодировку проекта и обратно                        |
|  TraitList\Tools\ErrorCollection | Трейт для работы с ошибками.<br/>implements `Bitrix\Main\Errorable`                                         |
|          TraitList\Tools\IsDebug | Трейт для работы с режимом отладки/разработки                                                               |
| TraitList\Tools\OptionCollection | Трейт для работы с опциями                                                                                  |
|    TraitList\Tools\LogCollection | Трейт для накопления записей лога: `setLogCollection()`, `addLogCollection()`, `getLogCollection()` |
|    TraitList\Tools\PrepareFields | Используется для приведения и проверок полей по типам                                                       |
|        TraitList\Tools\SelfClass | Трейт для работы названиями классов                                                                         |
|            TraitList\Tools\XmlId | Трейт для работы XmlId.<br/>Генерирует уникальные номера и т.п                                              |
|                 **TraitList\Uf** | **Пользовательские поля сущности** |
|             TraitList\Uf\IEntity | Интерфейс: `getUserFields()`, `getUserFieldByName()` |
|              TraitList\Uf\Entity | Трейт — реализация `TraitList\Uf\IEntity` |
|           **TraitList\Security** | **Работа с пользователями**                                                                                 |
|       TraitList\Security\FixUser | подмена пользователя в агенте — только в cron (PHP CLI), в веб-запросе — исключение (3.4.0); только для API ядра без параметра пользователя |

[← Тестирование](8_tests.md) | [↑ Содержание](../README.md)