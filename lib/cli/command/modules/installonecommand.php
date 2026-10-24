<?php

declare(strict_types=1);

namespace Shef\Options\Cli\Command\Modules;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;
use Shef\Options\Main\Modules\Sync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Служебная (скрытая) часть shef:modules:sync: поставить один модуль, файлы которого уже на диске.
 *
 * Зовёт DoInstall() установщика — со всеми его проверками (UTF-8, PHP, зависимости и версии) и
 * шагами, ровно как кнопка «Установить» в админке. Отказ канонический установщик показывает формой
 * и делает die() — поэтому команда живёт в отдельном процессе, а итог и текст отказа печатает
 * обработчик завершения; код выхода 0 — модуль зарегистрирован.
 */
class InstallOneCommand extends Command
{
    public const NAME = 'shef:modules:install-one';

    protected function configure(): void
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Поставить один модуль (служебная часть shef:modules:sync)')
            ->setHidden(true)
            ->addArgument('id', InputArgument::REQUIRED, 'ID модуля');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string)$input->getArgument('id');
        if (!preg_match('/^[a-z0-9]+\.[a-z0-9]+$/', $id)) {
            $output->writeln('Плохой ID модуля: '.$id);
            return Command::INVALID;
        }
        if (ModuleManager::isModuleInstalled($id)) {
            $output->writeln('уже установлен');
            return Command::SUCCESS;
        }
        $found = (new Sync((string)Application::getDocumentRoot(), $id))->discover();
        if (!isset($found[$id])) {
            $output->writeln('нет файлов модуля');
            return Command::FAILURE;
        }

        // DoInstall рисует страницу админки, а при отказе делает die(): вывод глушим, итог — здесь
        register_shutdown_function(static function () use ($id): void {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (ModuleManager::isModuleInstalled($id)) {
                echo 'установлен';
                exit(0);
            }
            $exception = isset($GLOBALS['APPLICATION']) && method_exists($GLOBALS['APPLICATION'], 'GetException') ? $GLOBALS['APPLICATION']->GetException() : null;
            fwrite(STDERR, 'не установлен'.($exception ? ': '.$exception->GetString() : ''));
            exit(1);
        });

        ob_start();
        $module = Sync::installer($id, $found[$id]);
        $module->DoInstall();

        return ModuleManager::isModuleInstalled($id) ? Command::SUCCESS : Command::FAILURE;
    }
}
