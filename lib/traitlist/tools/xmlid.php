<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Tools;

/**
 * Трейт для работы XmlId
 *
 * Генерирует уникальные номера и т.п
 */
trait XmlId
{
	protected static function getXmlIdIdempotence(): string
	{
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}
	
	protected static function getCodeIdempotence(string $code): string
	{
		return sprintf('%s-%s',
			$code,
			static::getXmlIdIdempotence()
		);
	}
}