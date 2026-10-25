<?php

declare(strict_types=1);

namespace Shef\Options\TraitList\Security;

/**
 * Подмена текущего пользователя в агенте — ТОЛЬКО в процессе командной строки (cron).
 *
 * Подмена пишет пользователя в сессию через CUser::Authorize(). На хите посетителя это его
 * сессия: сбой между initUser() и closeUser() оставлял посетителя (в том числе анонима) под
 * чужим именем — эскалация прав, замер в bx-shef/options#48. Поэтому трейт — только для API
 * ядра, которое смотрит строго на «текущего» и не принимает пользователя параметром (поиск
 * note — shef.toolsaieval). Во всех остальных случаях ID передаётся явно (дела — AUTHOR_ID и
 * setCheckPermissions(false), CRM — Service\Context::setUserId()). Трейт остаётся в модуле
 * (решение владельца 2026-10-25): в нынешнем виде он безопасен, удалять его не планируется.
 *
 * С 3.4.0 initUser() вне CLI бросает \LogicException и пользователя не трогает: ни один модуль
 * линейки не подменит пользователя на хите, даже по ошибке. Ядро запускает агент на cron
 * отдельным процессом PHP CLI (cron_events.php) — там сессии посетителя нет. «Выполнить» агент
 * руками из админки, на хите (без BX_CRONTAB_SUPPORT) — это веб-запрос: исключение, агент
 * ловит его в своём try/catch. Возврат прежнего пользователя ставится и в конец процесса
 * (register_shutdown_function): фатал между initUser() и closeUser() подмену не оставит.
 */
trait FixUser
{
    protected static bool $isTmpUserCreated = false;
    protected static null|int $tmpUserId = null;
    protected static bool $isShutdownRegistered = false;

    /**
     * Определяет какого юзера нужно подключить
     *
     * @return int
     */
    abstract protected static function getInitedUserId(): int;

    /** Можно ли подменять пользователя в этом процессе: только PHP CLI (cron). */
    public static function canSubstituteUser(string $sapi): bool
    {
        return $sapi === 'cli';
    }

    /** Интерфейс PHP процесса; переопределяется в тестах. */
    protected static function phpSapi(): string
    {
        return PHP_SAPI;
    }

    /**
     * Инициализирует юзера — только в CLI.
     *
     * @throws \LogicException вне CLI (веб-запрос): подмены нет
     */
    protected static function initUser(): void
    {
        if (!static::canSubstituteUser(static::phpSapi())) {
            throw new \LogicException('FixUser: подмена пользователя только в процессе cron (PHP CLI), а это веб-запрос ('.static::phpSapi().'). Переведите агенты на cron (BX_CRONTAB_SUPPORT) — bx-shef/options#48');
        }

        \Bitrix\Main\Application::getInstance()->getSession()->start();

        static::$isTmpUserCreated = true;
        global $USER;

        if (isset($USER) && is_object($USER)) {
            static::$tmpUserId = (int)$USER->GetID();
        }

        $newUserId = static::getInitedUserId();

        if ($newUserId === static::$tmpUserId) {
            static::$isTmpUserCreated = false;
            return;
        }

        if (!static::$isShutdownRegistered) {
            static::$isShutdownRegistered = true;
            register_shutdown_function(static fn () => static::closeUser());
        }

        $USER = new \CUser();
        $USER->Authorize(
            $newUserId,
            false,
            false
        );
    }

    /**
     * Сбрасывает инициализацию юзера: выход подменённого, возврат прежнего (если был).
     * Повторный вызов (из finally и в конце процесса) ничего не делает.
     *
     * @return void
     */
    public static function closeUser(): void
    {
        global $USER;

        if (!static::$isTmpUserCreated) {
            return;
        }
        static::$isTmpUserCreated = false;
        $previous = static::$tmpUserId;
        static::$tmpUserId = null;

        if (is_object($USER)) {
            $USER->Logout();
        }
        if ($previous !== null && $previous > 0) {
            $USER = new \CUser();
            $USER->Authorize(
                $previous,
                false,
                false
            );
        }
    }
}
