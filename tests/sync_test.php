<?php declare(strict_types=1);

/**
 * Раскладка навыков: локальные навыки получателя переживают sync.sh --to.
 *
 * У получателя бывают навыки про него самого — источник о них не знает и
 * знать не должен. Раньше --to стирал всё, чего нет в источнике, а --check
 * краснел на любом лишнем файле: локальный навык либо не заводился, либо
 * исчезал при следующей раскладке молча.
 *
 * Теперь локальные навыки перечислены в LOCAL.MANIFEST получателя. Тест
 * гоняет настоящий sync.sh на песочнице-получателе:
 *
 * 1. разложили — копия цела и совпадает с источником;
 * 2. лишний файл без LOCAL.MANIFEST — --check краснеет, как и раньше;
 * 3. --local записал его — --check зелёный, сверка с источником тоже;
 * 4. повторная раскладка локальный навык не трогает;
 * 5. правка локального навыка без --local — --check краснеет;
 * 6. локальный навык с путём навыка линейки — --to отказывается.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$sync = $root.'/.claude/skills/sync.sh';
$target = sys_get_temp_dir().'/shef-options-sync-'.getmypid();
$targetSync = $target.'/.claude/skills/sync.sh';
$local = $target.'/.claude/skills/acme-local/SKILL.md';

/** Код возврата команды; вывод печатается при провале — чтобы было что читать. */
$run = static function(string $command) : int
{
	$output = [];
	$code = 0;
	exec($command.' 2>&1', $output, $code);

	return $code;
};

$remove = static function(string $dir) use (&$remove): void
{
	if(!is_dir($dir))
	{
		return;
	}

	foreach(scandir($dir) ?: [] as $entry)
	{
		if('.' === $entry || '..' === $entry)
		{
			continue;
		}

		$path = $dir.'/'.$entry;
		is_dir($path) && !is_link($path) ? $remove($path) : unlink($path);
	}

	rmdir($dir);
};

mkdir($target, 0777, true);

Check::group('раскладка');

Check::same('--to отработал', $run(escapeshellarg($sync).' --to '.escapeshellarg($target)), 0);
Check::same('копия цела', $run(escapeshellarg($targetSync).' --check'), 0);
Check::same('копия совпадает с источником', $run(escapeshellarg($targetSync).' --check '.escapeshellarg($root.'/.claude/skills/MANIFEST')), 0);

Check::group('локальный навык');

mkdir(dirname($local), 0777, true);
file_put_contents($local, "---\nname: acme-local\ndescription: навык получателя\n---\n");

Check::same('без LOCAL.MANIFEST — лишний файл ловится', $run(escapeshellarg($targetSync).' --check') !== 0, true);
Check::same('--local отработал', $run(escapeshellarg($targetSync).' --local'), 0);
Check::same('после --local копия цела', $run(escapeshellarg($targetSync).' --check'), 0);
Check::same('и совпадает с источником', $run(escapeshellarg($targetSync).' --check '.escapeshellarg($root.'/.claude/skills/MANIFEST')), 0);

Check::group('повторная раскладка');

Check::same('--to отработал', $run(escapeshellarg($sync).' --to '.escapeshellarg($target)), 0);
Check::same('локальный навык на месте', is_file($local), true);
Check::same('LOCAL.MANIFEST на месте', is_file($target.'/.claude/skills/LOCAL.MANIFEST'), true);
Check::same('копия цела', $run(escapeshellarg($targetSync).' --check'), 0);

Check::group('правка локального навыка');

file_put_contents($local, "\nправка", FILE_APPEND);
Check::same('без --local — ловится', $run(escapeshellarg($targetSync).' --check') !== 0, true);
$run(escapeshellarg($targetSync).' --local');
Check::same('с --local — цела', $run(escapeshellarg($targetSync).' --check'), 0);

Check::group('совпадение с навыком линейки');

file_put_contents(
	$target.'/.claude/skills/LOCAL.MANIFEST',
	"0000  shef-feedback/SKILL.md\n",
	FILE_APPEND
);
Check::same('--to отказывается', $run(escapeshellarg($sync).' --to '.escapeshellarg($target)) !== 0, true);

$remove($target);

Check::finish();
