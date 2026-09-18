<?php declare(strict_types=1);

namespace Shef\Options\Installator;

use Bitrix\Main\Result;
use Bitrix\Main\Type\Dictionary;

/**
 * Интерфейс установщика
 */
interface IInstallator
{
	/**
	 * @param Dictionary[] $list
	 * @return Result
	 */
	public function build(Dictionary $list): Result;
}