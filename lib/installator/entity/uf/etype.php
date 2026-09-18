<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF;

/**
 * Типы UF
 *
 * @link https://dev.1c-bitrix.ru/api_d7/bitrix/main/userfield/settings/types.php
 */
enum EType: string
{
	case Empty = 'empty';
	case String = 'string';
	case Integer  = 'integer';
	case Double = 'double';
	case Date = 'date';
	case DateTime = 'datetime';
	case Boolean = 'boolean';
	case File = 'file';
	case Enumeration = 'enumeration';
	case Url = 'url';
	case Address = 'address';
	case IblockSection = 'iblock_section';
	case IblockElement = 'iblock_element';
	case Employee = 'employee';
	case Crm = 'crm';
	case CrmStatus = 'crm_status';
}