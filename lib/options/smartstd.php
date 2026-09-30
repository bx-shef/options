<?php declare(strict_types=1);

namespace Shef\Options\Options;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Type\Contract;

/**
 * Используется как расширение \stdClass
 *
 * Умеет красиво в Array конвертироваться
 *
 */
class SmartStd
	extends \stdClass
	implements Contract\Arrayable, \Stringable
{
	/**
	 * Преобразуетс в массив
	 * @return array
	 */
	public function toArray(): array
	{
		$value = json_decode(json_encode(static::toArrayInner($this)), true);
		
		if(!is_array($value))
		{
			return [];
		}
		
		return $value;
	}
	
	/**
	 * Преобразует к строке
	 *
	 * @return string
	 * @throws ArgumentException
	 */
	public function __toString(): string
	{
		return \Bitrix\Main\Web\Json::encode(
				$this->toArray(),
				JSON_UNESCAPED_SLASHES |
				JSON_UNESCAPED_UNICODE |
				JSON_PRETTY_PRINT |
				JSON_PARTIAL_OUTPUT_ON_ERROR |
				JSON_INVALID_UTF8_SUBSTITUTE
			);
	}
	
	public function __clone()
	{
		foreach(get_object_vars($this) as $name => $value)
		{
			if(is_object($value))
			{
				$this->{$name} = clone $value;
			}
		}
	}
	
	/**
	 * Создает объект из массива
	 *
	 * @memo Не учитывает интерфейсы массивов
	 *
	 * @param array $values
	 * @param int $level
	 * @return array|SmartStd
	 */
	public static function toObject(
		array $values,
		int $level = 0
	): array|self
	{
		$isObj = true;
		
		if($level === 0)
		{
			$object = new static();
		}
		elseif(array_is_list($values))
		{
			$object = [];
			$isObj = false;
		}
		else
		{
			$object = new static();
		}
		
		// stdClass object
		foreach($values as $key => $value)
		{
			if(is_array($value))
			{
				$value = static::toObject($value, ++$level);
			}
			
			if(!$isObj)
			{
				$object[$key] = $value;
			}
			else
			{
				$object->$key = $value;
			}
			
		}
		
		return $object;
	}
	
	/**
	 * Преобразует все в массив
	 * @param object|array $values
	 * @param int $level
	 * @return array
	 */
	protected static function toArrayInner(
		object|array $values,
		int $level = 0
	): array
	{
		$object = [];
		
		foreach($values as $key => $value)
		{
			if($value instanceof \Bitrix\Main\Type\Date)
			{
				$value = $value->toString();
			}
			elseif($value instanceof \Bitrix\Main\Type\Contract\Arrayable)
			{
				$value = $value->toArray();
			}
			elseif($value instanceof \Bitrix\Main\Type\Contract\Jsonable)
			{
				$value = $value->toJson();
			}
			elseif($value instanceof \JsonSerializable)
			{
				$value = $value->jsonSerialize();
			}
			elseif(
				is_object($value)
				|| is_array($value)
			)
			{
				$value = static::toArrayInner($value, ++$level);
			}
			
			$object[$key] = $value;
		}
		
		return $object;
	}
}