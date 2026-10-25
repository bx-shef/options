# Работа с пользователями

## [`\Shef\Options\Main\Context`] Контекст
> Клон класса `\Bitrix\Crm\Service\Context`.
 
Используется в для указания:

* пользователя
* контекста выполнения _{ manual | task | automation | rest }_

## [`\Shef\Options\Main\Security`] Пользователь

Класс позволяет определить текущего пользователя, его группы и права. Замена `\CCrmSecurityHelper`.


## [`\Shef\Options\TraitList\Security\FixUser`] Трейт для инициализации пользователя

> **Только для API ядра без параметра пользователя** (поиск `note`). Во всех остальных случаях ID
> сотрудника передавайте явно (дела — `AUTHOR_ID` и `setCheckPermissions(false)`, CRM — `Service\Context::setUserId()`).

Подменяет текущего пользователя на время работы агента — **только в процессе cron (PHP CLI)**.
Подмена пишет пользователя в сессию (`CUser::Authorize()`). На хите посетителя это его сессия, и
сбой посреди работы оставлял посетителя, даже анонима, под чужим именем — замер в
[bx-shef/options#48](https://github.com/bx-shef/options/issues/48). С 3.4.0 в веб-запросе
`initUser()` бросает `\LogicException` и ничего не трогает: агент ловит его своим `try/catch`.

- `TraitList\Security\FixUser::getInitedUserId` — какой пользователь нужен.
- `TraitList\Security\FixUser::initUser` — подменить (только CLI), запомнив текущего. Возврат
  ставится и в конец процесса: фатал не оставит подмену.
- `TraitList\Security\FixUser::closeUser` — выход подменённого и возврат прежнего. Звать в
  `finally`; повторный вызов ничего не делает.
- `TraitList\Security\FixUser::canSubstituteUser` — можно ли подменять в этом процессе.



[← Опции настроек модуля](3_options.md) | [↑ Содержание](../README.md) | [Утилиты →](5_utils.md)