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
    implements Contract\Arrayable
{
    /**
     * Преобразуетс в массив
     * @return array
     * @throws ArgumentException значение не переводится в json
     */
    public function toArray(): array
    {
        // json_encode() возвращает false, если данные ему не по зубам: строка
        // не в UTF-8, INF, NAN. Дальше json_decode(false) под strict_types —
        // это TypeError про аргумент json_decode(), по которому не понять
        // ничего. Поэтому отказ ловится здесь и называет причину.
        //
        // Подменять испорченные символы (JSON_INVALID_UTF8_SUBSTITUTE) нельзя,
        // хотя падать после этого было бы нечему: два разных ключа в cp1251
        // дают одну и ту же строку из U+FFFD, схлопываются в один, и значение
        // пропадает молча. Проверено: на входе три элемента, на выходе два.
        // Молча отдать правдоподобный мусор хуже, чем отказаться, — тот же
        // довод, что и у отказа ставиться на портал в CP1251.
        $json = json_encode(static::toArrayInner($this));

        if(!is_string($json))
        {
            throw new ArgumentException(sprintf(
                'SmartStd: значение не переводится в json (%s)',
                json_last_error_msg()
            ));
        }

        $value = json_decode($json, true);

        if(!is_array($value))
        {
            return [];
        }

        return $value;
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