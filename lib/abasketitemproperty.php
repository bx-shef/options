<?php

namespace Shef\Options;

use Bitrix\Sale\EntityPropertyValue;

abstract class ABasketItemProperty
{
    public const TITLE = 'NOTSET';
    public const PROPERTY_CODE = 'NOTSET';
    public const SORT = 0;

    public static function getTitle(): string
    {
        return static::TITLE;
    }

    public static function getCode(): string
    {
        return static::PROPERTY_CODE;
    }
    public static function getSort(): string
    {
        return static::SORT;
    }

    public static function getConfig(): array
    {
        return [
            'NAME' => static::getTitle()
            ,'CODE' => static::getCode()
            ,'SORT' => static::getSort()
        ];
    }
}
