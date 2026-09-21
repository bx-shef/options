<?php declare(strict_types=1);

/**
 * Evals навыков: по каким фразам агент должен брать навык.
 *
 * У навыка может лежать evals/selection.json — список пар «фраза задачи →
 * ожидаемый навык». Их гоняет через модель `bitrixsh eval`: даёт модели
 * описания всех навыков и смотрит, какой она выбирает первым. Так ловится
 * главная поломка навыка — описание, по которому его не находят.
 *
 * Модель здесь не зовётся: это дорого и нестабильно для CI. Здесь только
 * оформление: файл разбирается, ожидаемый навык существует, у каждого
 * операционного навыка есть хотя бы одна фраза «на себя» и хотя бы одна
 * «на соседа» или «<none>» — без отрицательных примеров eval ничего не
 * ловит, модель просто всегда что-нибудь выбирает.
 */

$root = dirname(__DIR__);

require_once $root.'/tests/assert.php';

$skillsDir = is_dir($root.'/.agents/skills') ? $root.'/.agents/skills' : $root.'/.claude/skills';
$skills = glob($skillsDir.'/*/SKILL.md') ?: [];
sort($skills);

$names = array_map(static fn(string $p): string => basename(dirname($p)), $skills);

Check::group('состав');

Check::same('skills нашлись', count($names) > 0, true);

$evals = [];

foreach($names as $name)
{
	$file = $skillsDir.'/'.$name.'/evals/selection.json';

	if(is_file($file))
	{
		$evals[$name] = $file;
	}
}

// Операционные навыки («сделай») обязаны иметь evals: по ним агент
// принимает решение писать код. Справочные — по желанию.
$operational = array_values(array_filter($names, static fn(string $n): bool => str_contains($n, '-new-')));
$withoutEvals = array_values(array_diff($operational, array_keys($evals)));

Check::same('у каждого операционного навыка есть evals/selection.json', $withoutEvals, []);

Check::group('оформление');

$broken = [];
$allInputs = [];

foreach($evals as $name => $file)
{
	$cases = json_decode((string)file_get_contents($file), true);

	if(!is_array($cases) || array_is_list($cases) === false)
	{
		$broken[] = $name.': selection.json — не список';
		continue;
	}

	if(count($cases) < 3)
	{
		$broken[] = $name.': меньше трёх фраз';
	}

	$self = 0;
	$other = 0;

	foreach($cases as $i => $case)
	{
		$input = is_array($case) ? ($case['input'] ?? '') : '';
		$expected = is_array($case) ? ($case['expected'] ?? '') : '';

		if(!is_string($input) || mb_strlen(trim($input)) < 10)
		{
			$broken[] = sprintf('%s[%d]: input пустой или короче 10 символов', $name, $i);
			continue;
		}

		if(!is_string($expected) || $expected === '')
		{
			$broken[] = sprintf('%s[%d]: нет expected', $name, $i);
			continue;
		}

		if($expected !== '<none>' && !in_array($expected, $names, true))
		{
			$broken[] = sprintf('%s[%d]: expected «%s» — такого навыка нет', $name, $i, $expected);
			continue;
		}

		if($expected === $name)
		{
			$self++;
		}
		else
		{
			$other++;
		}

		$key = mb_strtolower(trim($input));

		if(isset($allInputs[$key]) && $allInputs[$key] !== $expected)
		{
			$broken[] = sprintf('%s[%d]: та же фраза в другом навыке ждёт «%s»', $name, $i, $allInputs[$key]);
		}

		$allInputs[$key] = $expected;
	}

	if($self === 0)
	{
		$broken[] = $name.': нет ни одной фразы, где ожидается сам навык';
	}

	if($other === 0)
	{
		$broken[] = $name.': нет ни одной фразы на соседа или <none>';
	}
}

Check::same('evals разбираются и ссылаются на существующие навыки', $broken, []);

Check::finish();
