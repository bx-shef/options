<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Tools;


use Bitrix\Main\Application;
use Bitrix\Main\Config\Configuration;

/**
 * Трейт для работы с кодировкой
 *
 * Преобразует в текущую кодировку проекта и обратно
 *
 */
trait Encoding
{
	/**
	 * @inheritDoc
	 */
	abstract public static function getEncodingFrom(): string;

	/**
	 * Текущая кодировка проекта
	 * @see \Bitrix\Main\Text\Encoding::getCurrentEncoding
	 * @return string
	 */
	protected static function getCurrentEncoding(): string
	{
		$currentCharset = null;

		$context = Application::getInstance()->getContext();
		if($context != null)
		{
			$culture = $context->getCulture();
			$currentCharset = $culture?->getCharset();
		}

		if($currentCharset == null)
		{
			$currentCharset = Configuration::getValue('default_charset');
		}

		if($currentCharset == null)
		{
			$currentCharset = 'Windows-1251';
		}

		return $currentCharset;
	}
	
	/**
	 * Конвертируем в кодировку проекта
	 * @param string|array|\SplFixedArray|bool $value
	 * @return string|array|\SplFixedArray|bool
	 */
	public static function convertEncoding(string|array|\SplFixedArray|bool $value): string|array|\SplFixedArray|bool
	{
		return \Bitrix\Main\Text\Encoding::convertEncoding(
			$value,
			static::getEncodingFrom(),
			static::getCurrentEncoding(),
		);
	}
	
	/**
	 * Конвертируем в исходную кодировку
	 * @param string|array|\SplFixedArray|bool $value
	 * @return string|array|\SplFixedArray|bool
	 */
	public static function unConvertEncoding(string|array|\SplFixedArray|bool $value): string|array|\SplFixedArray|bool
	{
		return \Bitrix\Main\Text\Encoding::convertEncoding(
			$value,
			static::getCurrentEncoding(),
			static::getEncodingFrom()
		);
	}
}