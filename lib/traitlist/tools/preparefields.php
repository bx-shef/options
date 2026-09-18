<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Tools;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ObjectException;
use Bitrix\Main\Type\Date;

/**
 * Используется для приведения и проверок полей по типам
 */
trait PrepareFields
{
	/**
	 * @param string $value
	 * @return string
	 */
	public static function clearHtmlTags(string $value): string
	{
		$value = strip_tags($value);
		$value = str_replace(['&nbsp;'], [' '], $value);

		return static::parseString($value);
	}

	/**
	 * @param mixed $value
	 * @return string
	 */
	public static function parseString(mixed $value): string
	{
		if(empty($value))
		{
			return '';
		}

		$result = (string)$value;

		// $result = preg_replace('/^( |\t|-)+/'.BX_UTF_PCRE_MODIFIER, '', $result); ////
		$result = preg_replace('/^([ \t\-])+/'.BX_UTF_PCRE_MODIFIER, '', $result);
		$result = preg_replace('/ {2,}/'.BX_UTF_PCRE_MODIFIER, ' ', $result);

		return trim($result);
	}

	/**
	 * @param mixed $value
	 * @return int
	 */
	public static function parseInt(mixed $value): int
	{
		if(empty($value))
		{
			return 0;
		}

		$value = static::parseString((string)$value);
		$value = str_replace([' ','  ', chr(194).chr(160)], '', $value);

		return (int)(str_replace(',', '.', $value));
	}

	/**
	 * @param mixed $value
	 * @return float
	 */
	public static function parseFloat(mixed $value): float
	{
		if(empty($value))
		{
			return 0.0;
		}

		$value = static::parseString((string)$value);
		$value = str_replace([' ','  ', chr(194).chr(160)], '', $value);

		return (float)(str_replace(',', '.', $value));
	}

	/**
	 * @param string $title
	 * @param mixed|null $value
	 * @param bool $isRequired
	 * @param mixed|null $defValue
	 * @return mixed
	 * @throws ArgumentNullException
	 */
	protected static function checkField(
		string $title,
		mixed $value = null,
		bool $isRequired = false,
		mixed $defValue = null
	): mixed
	{
		if(
			is_null($value)
			&& $isRequired === true
		)
		{
			throw new ArgumentNullException($title);
		}

		if(is_null($value))
		{
			return $defValue;
		}

		return $value;
	}

	/**
	 * @param string $title
	 * @param mixed|null $value
	 * @param bool $isRequired
	 * @param array $defValue
	 * @return array
	 * @throws ArgumentNullException
	 */
	public static function checkFieldArray(
		string $title,
		mixed $value = null,
		bool $isRequired = false,
		array $defValue = []
	): array
	{
		$result = static::checkField($title, $value, $isRequired, $defValue);

		if(!is_array($result))
		{
			$result = $defValue;
		}

		return $result;
	}

	/**
	 * @param string $title
	 * @param mixed|null $value
	 * @param bool $isRequired
	 * @param float $defValue
	 * @return float
	 * @throws ArgumentNullException
	 */
	public static function checkFieldFloat(
		string $title,
		mixed $value = null,
		bool $isRequired = false,
		float $defValue = 0.0
	): float
	{
		$result = static::checkField($title, $value, $isRequired, $defValue);
		return static::parseFloat($result);
	}

	/**
	 * @param string $title
	 * @param mixed|null $value
	 * @param bool $isRequired
	 * @param int $defValue
	 * @return int
	 * @throws ArgumentNullException
	 */
	public static function checkFieldInt(
		string $title,
		mixed $value = null,
		bool $isRequired = false,
		int $defValue = 0
	): int
	{
		$result = static::checkField($title, $value, $isRequired, $defValue);
		return static::parseInt($result);
	}

	/**
	 * @param string $title
	 * @param mixed|null $value
	 * @param bool $isRequired
	 * @param string $defValue
	 * @return string
	 * @throws ArgumentNullException
	 */
	public static function checkFieldString(
		string $title,
		mixed $value = null,
		bool $isRequired = false,
		string $defValue = ''
	): string
	{
		$result = static::checkField($title, $value, $isRequired, $defValue);
		return static::parseString($result);
	}

	/**
	 * @param string $title
	 * @param mixed|null $value
	 * @param bool $isRequired
	 * @param \Bitrix\Main\Type\DateTime|null $defValue
	 * @param string $format
	 * @return \Bitrix\Main\Type\DateTime
	 * @throws ArgumentNullException
	 */
	public static function checkFieldDateTime(
		string $title,
		mixed $value = null,
		bool $isRequired = false,
		?\Bitrix\Main\Type\DateTime $defValue = null,
		string $format = 'd.m.Y H:i:s'
	): \Bitrix\Main\Type\DateTime
	{
		$result = static::checkField($title, $value, $isRequired, $defValue);

		try
		{
			$result = new \Bitrix\Main\Type\DateTime($result, $format);
		}
		catch(ObjectException $exception)
		{
			$result = $defValue ??  new \Bitrix\Main\Type\DateTime();
		}

		return $result;
	}

	/**
	 * @param string $title
	 * @param mixed|null $value
	 * @param bool $isRequired
	 * @param Date|null $defValue
	 * @param string $format
	 * @return Date
	 * @throws ArgumentNullException
	 */
	public static function checkFieldDate(
		string $title,
		mixed  $value = null,
		bool   $isRequired = false,
		?Date  $defValue = null,
		string $format = 'd.m.Y'
	): Date
	{
		$result = static::checkField($title, $value, $isRequired, $defValue);

		try
		{
			$result = new Date($result, $format);
		}
		catch (ObjectException $exception)
		{
			$result = $defValue ??  new Date();
		}

		return $result;
	}

	/**
	 * Гарантировано вернет из $row[$code] массив.
	 *
	 * @memo Актуально при разборе xml
	 * @example   $this->prepareRowList('Цвет', $element['ЦветаДляФильтра']);
	 *
	 * @param string $code
	 * @param array $row
	 * @return array|string[]
	 */
	protected function & prepareRowList(string $code, array &$row): array
	{
		if(!isset($row[$code]))
		{
			$list = [];
		}
		elseif(
			!is_string($row[$code])
			&&isset($row[$code][0])
		)
		{
			$list = &$row[$code];
		}
		else
		{
			$list = [
				0 => &$row[$code]
			];
		}

		if(!is_array($list))
		{
			$list = [];
		}

		return $list;
	}

	/**
	 * Транслитерация строки.
	 *
	 * @memo если использовать $options['str_replace'] = ['ё' => 'yo']; то будет до перевода сделана замена
	 * @memo указывает язык $options['lang']
	 *
	 * @param string $value
	 * @param array $options
	 * @return string
	 */
	protected function translit(string $value, array $options = []): string
	{
		$params = [
			'replace_space' => $options['replace_space'] ?? '-',
			'replace_other' => $options['replace_other'] ?? '-'
		];

		if(isset($options['str_replace']) && is_array($options['str_replace']))
		{
			$value = str_replace(array_keys($options['str_replace']), array_values($options['str_replace']), $value);
		}

		$value = str_replace(['ё', 'Ё'], ['yo', 'Yo'], $value);

		return \CUtil::translit(
			$value,
			$options['lang'] ?? 'ru',
			$params
		);
	}
}