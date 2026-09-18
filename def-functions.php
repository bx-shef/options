<?php

if(file_exists(__DIR__.'/../../php_interface/def-functions.php'))
{
	require_once __DIR__.'/../../php_interface/def-functions.php';
}

if(!function_exists('_pr'))
{
	function _pr($o, bool $show = false, bool $die = false, bool $fullBackTrace = true): void
	{
		if(\Bitrix\Main\Engine\CurrentUser::get()->isAdmin() || $show)
		{
			$bt = debug_backtrace();

			$firstBt = $bt[0];
			$dRoot = $_SERVER["DOCUMENT_ROOT"];
			$dRoot = str_replace("/", "\\", $dRoot);
			$firstBt["file"] = str_replace($dRoot, "", $firstBt["file"]);
			$dRoot = str_replace("\\", "/", $dRoot);
			$firstBt["file"] = str_replace($dRoot, "", $firstBt["file"]);

			?>
			<div style="font-size:9pt; color:#000; background:#fff; border:1px dashed #000;">
			<div style="padding:3px 5px; background:#99CCFF;">
				<?php
				if($fullBackTrace == false): ?>
					File: <b><?=$firstBt["file"]?></b> [line: <?=$firstBt["line"]?>]
				<?php
				else: ?>
					<?php
					foreach($bt as $value): ?>
						<?php
						$dRoot = str_replace("/", "\\", $dRoot);
						$value["file"] = str_replace($dRoot, "", $value["file"]);
						$dRoot = str_replace("\\", "/", $dRoot);
						$value["file"] = str_replace($dRoot, "", $value["file"]);
						?>

						File: <b><?=$value["file"]?></b> [line: <?=$value["line"]?>]<br>
					<?php
					endforeach ?>
				<?php
				endif; ?>
			</div>
			<pre style="color:#000; padding:10px;"><?php
				is_array($o)
					? print_r($o)
					: print_r(htmlspecialcharsbx($o)) ?></pre>
			</div><?php
			if($die == true)
			{
				die();
			}
		}
	}
}

if(!function_exists('_log'))
{
	function _log(array $value = [], string $fileName = 'log'): void
	{
		$e = new \Exception();
		\Bitrix\Main\IO\File::putFileContents(
			$_SERVER["DOCUMENT_ROOT"]."/local/log/".$fileName."_".date('dmY').".log",
			implode("\n", [
				'',
				' >>> '.date('d.m.Y H:i:s').' >>>',
				print_r($value, true),
				' >>> trace >>>',
				print_r(str_replace($_SERVER["DOCUMENT_ROOT"], '', $e->getTraceAsString()), true),
				' >>> >>> >>>'
			])
			//,\Bitrix\Main\IO\File::REWRITE ////
			, \Bitrix\Main\IO\File::APPEND
		);
	}
}

if(!function_exists('_log1'))
{
	function _log1(array $value = [], string $fileName = 'log'): void
	{
		static $isFirstCall = false;
		$mode = FILE_APPEND;
		if(!$isFirstCall)
		{
			$isFirstCall = true;
			$mode = null; // REWRITE ////
		}

		$e = new \Exception();
		file_put_contents($_SERVER['DOCUMENT_ROOT']."/local/log/".$fileName."_".date('dmY').".log", implode("\n", [
			'',
			' >>> '.date('d.m.Y H:i:s').' >>>',
			print_r($value, true),
			' >>> trace >>>',
			print_r(str_replace($_SERVER["DOCUMENT_ROOT"], '', $e->getTraceAsString()), true),
			' >>> >>> >>>'
		]), $mode);
	}
}