<?php

declare(strict_types=1);

/**
 * TraitList\Security\FixUser (3.4.0, bx-shef/options#48): подмена пользователя — только в
 * PHP CLI (cron). Вне CLI initUser() бросает и не трогает ни $USER, ни сессию; в CLI —
 * авторизует нужного и closeUser() возвращает прежнего; повторный closeUser() — без действий;
 * тот же пользователь — без подмены. Ядро подменено своими классами в этом файле.
 */

namespace Bitrix\Main {
    class Application
    {
        public static int $sessions = 0;

        public static function getInstance(): static
        {
            return new static();
        }

        public function getSession(): object
        {
            return new class () {
                public function start(): void
                {
                    Application::$sessions++;
                }
            };
        }
    }
}

namespace {
    require_once __DIR__.'/assert.php';
    require_once dirname(__DIR__).'/lib/traitlist/security/fixuser.php';

    final class CUser
    {
        /** @var list<string> */
        public static array $log = [];

        public function __construct(public int $id = 0)
        {
        }

        public function GetID(): int
        {
            return $this->id;
        }

        public function Authorize(int $id, bool $save = false, bool $update = true): bool
        {
            $this->id = $id;
            static::$log[] = 'authorize '.$id;
            return true;
        }

        public function Logout(): void
        {
            static::$log[] = 'logout '.$this->id;
            $this->id = 0;
        }
    }

    final class FixUserAgent
    {
        use \Shef\Options\TraitList\Security\FixUser;

        public static string $sapi = 'cli';
        public static int $target = 9;
        public static int $asked = 0;

        protected static function phpSapi(): string
        {
            return static::$sapi;
        }

        protected static function getInitedUserId(): int
        {
            static::$asked++;
            return static::$target;
        }

        public static function run(): void
        {
            static::initUser();
        }
    }

    $reset = static function (int $current): void {
        global $USER;
        $USER = new CUser($current);
        CUser::$log = [];
        \Bitrix\Main\Application::$sessions = 0;
        FixUserAgent::$asked = 0;
        FixUserAgent::closeUser();
        CUser::$log = [];
    };

    Check::group('где можно подменять');
    Check::same('только cli', array_map([FixUserAgent::class, 'canSubstituteUser'], ['cli', 'fpm-fcgi', 'apache2handler', 'cgi-fcgi', 'cli-server']), [true, false, false, false, false]);

    Check::group('веб-запрос: исключение, ничего не тронуто');
    foreach (['fpm-fcgi', 'apache2handler'] as $sapi) {
        $reset(5);
        FixUserAgent::$sapi = $sapi;
        $thrown = '';
        try {
            FixUserAgent::run();
        } catch (\LogicException $e) {
            $thrown = $e->getMessage();
        }
        global $USER;
        Check::same($sapi.': исключение, пользователь 5, без сессии и авторизаций, ID не спрашивали', [str_contains($thrown, 'только в процессе cron'), $USER->GetID(), \Bitrix\Main\Application::$sessions, CUser::$log, FixUserAgent::$asked], [true, 5, 0, [], 0]);
        FixUserAgent::closeUser();
        Check::same($sapi.': closeUser после отказа — ничего', [CUser::$log, $USER->GetID()], [[], 5]);
    }

    Check::group('cron (cli): подмена и возврат');
    FixUserAgent::$sapi = 'cli';
    $reset(5);
    FixUserAgent::run();
    global $USER;
    Check::same('стал 9', [$USER->GetID(), CUser::$log], [9, ['authorize 9']]);
    FixUserAgent::closeUser();
    Check::same('вернулся 5', [$USER->GetID(), CUser::$log], [5, ['authorize 9', 'logout 9', 'authorize 5']]);
    FixUserAgent::closeUser();
    Check::same('повторный closeUser (finally + конец процесса) — без действий', count(CUser::$log), 3);

    $reset(0);
    FixUserAgent::run();
    FixUserAgent::closeUser();
    Check::same('без прежнего пользователя (cron): выход, никого не возвращаем', [$USER->GetID(), CUser::$log], [0, ['authorize 9', 'logout 9']]);

    $reset(9);
    FixUserAgent::run();
    FixUserAgent::closeUser();
    Check::same('тот же пользователь — без подмены', [$USER->GetID(), CUser::$log], [9, []]);

    Check::finish();
}
