<?php
declare(strict_types=1);

namespace Shef\Options\Components\Actions;

use Bitrix\Main\Engine\ActionFilter;

/*/
[
	'prefilters' => [
		new \Bitrix\Main\Engine\ActionFilter\HttpMethod([
			\Bitrix\Main\Engine\ActionFilter\HttpMethod::METHOD_GET
		]),
	],
	'-prefilters' => [
		Bitrix\Main\Engine\ActionFilter\Csrf::class,
	],
]
//*/

/**
 * Без проверки прав доступа
 */
class Free
	implements IActionsFilterList
{
	public static function get(): array
	{
		return [
			'-prefilters' => [
				ActionFilter\Csrf::class,
				ActionFilter\Authentication::class,
			],
			'+prefilters' => [
				new ActionFilter\CloseSession()
			]
		];
	}
}