<?php

declare(strict_types=1);

namespace Shef\Options\Cli\Command\Modules;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;
use Shef\Options\Main\Modules\Planner;
use Shef\Options\Main\Modules\Sync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * php bitrix/bitrix.php shef:modules:sync [--dry-run] [--module=a,b] [--prefix=shef.]
 *
 * После выкладки файлов модулей (git, Composer): ставит неустановленные модули линейки и
 * доставляет обновление установленным, чьи файлы новее последней синхронизации. Порядок — по
 * зависимостям (Planner). Установка — в отдельном процессе (shef:modules:install-one): канонический
 * установщик при отказе делает die(). Код выхода 1 — хоть один модуль не встал или не обновился.
 */
class SyncCommand extends Command
{
    public const NAME = 'shef:modules:sync';

    protected function configure(): void
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Поставить и обновить модули линейки shef.* по файлам на диске')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Только план, ничего не менять')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Только эти модули (и их зависимости), через запятую')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Какие модули искать', 'shef.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = (string)Application::getDocumentRoot();
        $sync = new Sync($root, (string)$input->getOption('prefix'));
        $found = $sync->discover();
        $states = $sync->states($found);
        $only = $input->getOption('module') ? array_values(array_filter(array_map('trim', explode(',', (string)$input->getOption('module'))))) : null;
        $plan = Planner::plan($states, Sync::installedOutside($states, [ModuleManager::class, 'isModuleInstalled']), $only);

        if ($plan === []) {
            $output->writeln('Модулей не найдено.');
            return Command::SUCCESS;
        }

        $failed = 0;
        foreach ($plan as $step) {
            $line = sprintf('%-26s %-8s %-10s %s', $step['id'], $step['version'], $step['action'], $step['reason']);
            if ($input->getOption('dry-run') || in_array($step['action'], [Planner::SKIP, Planner::BLOCKED], true)) {
                $output->writeln($line);
                $failed += $step['action'] === Planner::BLOCKED ? 1 : 0;
                continue;
            }
            [$ok, $details] = $step['action'] === Planner::INSTALL
                ? $this->installOne($root, $step['id'])
                : $this->updateOne($step['id'], $found[$step['id']]);
            if ($ok) {
                Sync::markSynced($step['id'], $step['version']);
            }
            $output->writeln($line.' — '.($ok ? 'готово' : 'ОШИБКА').($details !== '' ? ': '.$details : ''));
            $failed += $ok ? 0 : 1;
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /** @return array{0: bool, 1: string} */
    private function installOne(string $root, string $id): array
    {
        $cmd = [PHP_BINARY, $root.'/bitrix/bitrix.php', InstallOneCommand::NAME, $id, '--no-interaction'];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root.'/bitrix');
        if (!is_resource($proc)) {
            return [false, 'не удалось запустить процесс установки'];
        }
        $out = trim((string)stream_get_contents($pipes[1]));
        $err = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return [$code === 0, trim($err !== '' ? $err : $out)];
    }

    /** @return array{0: bool, 1: string} */
    private function updateOne(string $id, string $dir): array
    {
        try {
            return [true, implode(', ', Sync::update($id, $dir))];
        } catch (\Throwable $e) {
            return [false, $e->getMessage()];
        }
    }
}
