<?php
declare(strict_types=1);

namespace Shef\Options\Components\Actions;

/**
 * Обычная проверка прав доступа: ровно умолчания ядра.
 *
 * Пустой список — не забывчивость и не заготовка. Ключ «prefilters» замещает
 * умолчания ядра ЦЕЛИКОМ (\Bitrix\Main\Engine\Controller::buildFilters), а
 * умолчания — это Authentication, HttpMethod и Csrf. Стоявший здесь раньше
 * список из одного HttpMethod снимал с действия аутентификацию и проверку
 * csrf: «обычная» проверка выходила слабее, чем если бы конфигурации не было
 * вовсе, и любое действие с такой настройкой звалось из браузера без входа на
 * портал.
 *
 * Поэтому: добавить фильтр — «+prefilters», убрать — «-prefilters». Обе формы
 * правят умолчания, а не заменяют их (см. Free). Голый «prefilters» пишем,
 * только когда набор фильтров задаётся целиком и осознанно.
 */
class Normal
	implements IActionsFilterList
{
	public static function get(): array
	{
		return [];
	}
}