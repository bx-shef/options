# Работа с пользователями

## [`\Shef\Options\Main\Context`] Контекст
> Клон класса `\Bitrix\Crm\Service\Context`.
 
Используется в для указания:

* пользователя
* контекста выполнения _{ manual | task | automation | rest }_

## [`\Shef\Options\Main\Security`] Пользователь

Класс позволяет определить текущего пользователя, его группы и права. Замена `\CCrmSecurityHelper`.


## [`\Shef\Options\TraitList\Security\FixUser`] Трейт для инициализации пользователя

Используется в агентах и тп для инициализации пользователя.

Через `TraitList\Security\FixUser::getInitedUserId` определяем какой пользователя нужен.

Через `TraitList\Security\FixUser::initUser` инициализируем пользователя. Запоминаем текущего пользователя.

Через `TraitList\Security\FixUser::closeUser` закрыаем соединения пользователя. Восстанавливаем прошлого пользователя.



[← Опции настроек модуля](3_options.md) | [↑ Содержание](../README.md) | [Утилиты →](5_utils.md)