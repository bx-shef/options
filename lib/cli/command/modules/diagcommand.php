<?php

declare(strict_types=1);

namespace Shef\Options\Cli\Command\Modules;

use Bitrix\Main\Application;
use Shef\Options\Tools\Diag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * php bitrix/bitrix.php shef:modules:diag [--json] [--no-network] [--mcp-dir=…] [--targets=…] [--allow-root]
 *
 * Диагностика портала по модулям линейки (issue #70): только чтение. Логика — в tools/diag.php,
 * тот же файл запускается отдельно на портале без shef.options.
 */
class DiagCommand extends Command
{
    public const NAME = 'shef:modules:diag';

    protected function configure(): void
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Диагностика портала по модулям shef.* (только чтение, без секретов)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Вывод JSON')
            ->addOption('no-network', null, InputOption::VALUE_NONE, 'Без запросов к /health и локальным моделям')
            ->addOption('mcp-dir', null, InputOption::VALUE_REQUIRED, 'Каталог сервера MCP (по умолчанию <родитель корня>/mcp)')
            ->addOption('targets', null, InputOption::VALUE_REQUIRED, 'Цели выкатки JSON: {"release": "…", "targets": {"shef.toolsai": "1.91.0"}}')
            ->addOption('allow-root', null, InputOption::VALUE_NONE, 'Разрешить запуск от root (права и владельцы покажутся для root)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        require_once dirname(__DIR__, 4).'/tools/diag.php';
        $root = rtrim((string)Application::getDocumentRoot(), '/');
        if ($root === '') {
            $output->writeln('<error>Не найден корень сайта (DOCUMENT_ROOT)</error>');
            return 2;
        }
        if (function_exists('posix_geteuid') && posix_geteuid() === 0 && !$input->getOption('allow-root')) {
            $output->writeln('<error>Запуск от root: права и владельцы файлов покажутся неверно. Запустите от пользователя веб-сервера (на BitrixVM — bitrix) или добавьте --allow-root.</error>');
            return 2;
        }
        $loaded = null;
        if ($input->getOption('targets')) {
            $loaded = Diag::loadTargets((string)$input->getOption('targets'));
            if ($loaded === null) {
                $output->writeln('<error>--targets: файл не читается или не в формате {"release": "…", "targets": {"shef.toolsai": "1.91.0"}}</error>');
                return 2;
            }
        }
        $diag = Diag::forPortal(
            $root,
            !$input->getOption('no-network'),
            (string)($input->getOption('mcp-dir') ?: dirname($root).'/mcp'),
            $loaded[0] ?? null,
            $loaded[1] ?? Diag::RELEASE
        )->collect();
        $output->write($input->getOption('json') ? $diag->json().PHP_EOL : $diag->text(), false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
