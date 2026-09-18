<?php
declare(strict_types=1);

namespace Shef\Options\Components\Actions;

use Bitrix\Main\Engine\ActionFilter;

/**
 * Обычная проверка прав доступа
 */
class Normal
	implements IActionsFilterList
{
	public static function get(): array
	{
		return [
			'prefilters' => [
				new ActionFilter\HttpMethod([
					ActionFilter\HttpMethod::METHOD_GET,
					ActionFilter\HttpMethod::METHOD_POST
				])
			],
		];
	}
}