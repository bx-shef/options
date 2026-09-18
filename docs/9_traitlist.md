# [`\Shef\Options\TraitList`] Набор трейтов

|                            Класс | Описание                                                                                                    |
|---------------------------------:|-------------------------------------------------------------------------------------------------------------|
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
|    TraitList\Tools\PrepareFields | Используется для приведения и проверок полей по типам                                                       |
|        TraitList\Tools\SelfClass | Трейт для работы названиями классов                                                                         |
|            TraitList\Tools\XmlId | Трейт для работы XmlId.<br/>Генерирует уникальные номера и т.п                                              |
|           **TraitList\Security** | **Работа с пользователями**                                                                                 |
|       TraitList\Security\FixUser | Используется в агентах и тп для инициализации юзера                                                         |

[← Тестирование](8_tests.md) | [↑ Содержание](../README.md)