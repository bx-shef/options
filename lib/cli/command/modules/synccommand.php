<?php

declare(strict_types=1);

namespace Shef\Options\Cli\Command\Modules;

use Bitrix\Main\Application;
use Bitrix\Main\ModuleManager;
use Shef\Options\Main\Modules\Layout;
use Shef\Options\Main\Modules\Planner;
use Shef\Options\Main\Modules\Sync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * php bitrix/bitrix.php shef:modules:sync [--yes] [-m shef.currency] [--allow-root]
 *
 * После выкладки файлов модулей (git, Composer): ставит неустановленные модули линейки shef.* и
 * доставляет обновление установленным, чьи файлы новее последней синхронизации. Порядок — по
 * зависимостям (Planner). Без --yes — только план: обновление раскладывает файлы установщика
 * поверх, правки на портале в них пропадут. Установка — в отдельном процессе
 * (shef:modules:install-one): канонический установщик при отказе делает die().
 * Код выхода 1 — хоть один модуль не встал, не обновился или заблокирован.
 */
class SyncCommand extends Command
{
    public const NAME = 'shef:modules:sync';

    protected function configure(): void
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Поставить и обновить модули линейки shef.* по файлам на диске')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Выполнить план (без ключа — только показать)')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Только эти модули (и их зависимости), через запятую')
            ->addOption('allow-root', null, InputOption::VALUE_NONE, 'Разрешить запуск от root (файлы окажутся у root)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = (string)Application::getDocumentRoot();
        $bitrixPhp = $root.'/bitrix/bitrix.php';
        if ($root === '' || !is_file($bitrixPhp)) {
            $output->writeln('Не найден корень сайта (DOCUMENT_ROOT): запускайте из каталога bitrix сайта — php bitrix.php '.self::NAME);
            return Command::FAILURE;
        }
        if (function_exists('posix_geteuid') && posix_geteuid() === 0 && !$input->getOption('allow-root')) {
            $output->writeln('Запуск от root: разложенные файлы окажутся у root, и веб-сервер не сможет их обновить. Запустите от пользователя веб-сервера (на BitrixVM — bitrix) или добавьте --allow-root.');
            return Command::FAILURE;
        }

        $sync = new Sync($root);
        $found = $sync->discover();
        $states = $sync->states($found);
        $only = $input->getOption('module') ? array_values(array_filter(array_map('trim', explode(',', (string)$input->getOption('module'))))) : null;
        $versions = Sync::outsideVersions($states, static fn (string $id): string|false => ModuleManager::isModuleInstalled($id) ? (string)ModuleManager::getVersion($id) : false);
        $plan = Planner::plan($states, Sync::installedOutside($states, [ModuleManager::class, 'isModuleInstalled']), $only, $versions);
        if ($plan === []) {
            $output->writeln('Модулей shef.* не найдено.');
            return Command::SUCCESS;
        }

        if (!$input->getOption('yes')) {
            foreach (Sync::run($plan, static fn (): array => [true, ''], static fn (): array => [true, ''], static fn (): bool => true)['lines'] as $line) {
                $output->writeln(preg_replace('/ — готово$/', '', $line));
            }
            $output->writeln('');
            $output->writeln('Это план. Выполнить — с ключом --yes. Обновление раскладывает файлы модулей поверх: правки на портале в них пропадут.');
            return Command::SUCCESS;
        }

        $result = Sync::run(
            $plan,
            fn (string $id): array => $this->installOne($root, $bitrixPhp, $id),
            static fn (string $id): array => [true, implode(', ', Sync::update($id, $found[$id]))],
            [Sync::class, 'markSynced']
        );
        foreach ($result['lines'] as $line) {
            $output->writeln($line);
        }
        $this->gitCheck($root, $output);

        return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Портал из git (issue #55): файлы, которые установщики положили или поменяли и которых нет в
     * git, — следующий деплой их остановит (изменённые) или затрёт (новые). Только сообщение:
     * ничего не откатывает и не коммитит. Портал без git — молча.
     */
    private function gitCheck(string $root, OutputInterface $output): void
    {
        $top = trim((string)shell_exec('git -C '.escapeshellarg($root).' rev-parse --show-toplevel 2>/dev/null'));
        if ($top === '') {
            return;
        }
        $prefix = str_starts_with($root, $top.'/') ? substr($root, strlen($top) + 1).'/' : '';
        exec('git -C '.escapeshellarg($top).' status --porcelain --untracked-files=all -- '.escapeshellarg($root).' 2>/dev/null', $rows);
        $drift = Layout::gitDrift($rows, $prefix);
        if ($drift === []) {
            return;
        }
        $output->writeln('');
        $output->writeln('Дерево git портала разошлось с файлами модулей — внесите их в git, иначе следующий деплой остановится или затрёт их (tools/layout.php shef.options собирает их без портала):');
        foreach ($drift as $line) {
            $output->writeln('  '.$line);
        }
    }

    /** @return array{0: bool, 1: string} */
    private function installOne(string $root, string $bitrixPhp, string $id): array
    {
        $proc = proc_open([PHP_BINARY, $bitrixPhp, InstallOneCommand::NAME, $id, '--no-interaction'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root.'/bitrix');
        if (!is_resource($proc)) {
            return [false, 'не удалось запустить процесс установки'];
        }
        $out = trim((string)stream_get_contents($pipes[1]));
        $err = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proc) === 0, $err !== '' ? $err : $out];
    }
}
