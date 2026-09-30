<?php

namespace Shef\Options\Integration\IBlock\Fields;

class Types
{
    public const STRING = 'string';
    public const TEXT = 'text';
    public const JSON = 'json';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const MONEY = 'money';
    public const ENUM = 'enum';
    public const DATE = 'date';
    public const DATE_TIME = 'datetime';
    public const LIST_LINK = 'list_link';
    public const CRM_LINK = 'crm_link';
    public const USER_LINK = 'user_link';

    public static function getOrderFieldCode()
    {
        return 'ORDER_ID';
    }

    public static function getOrderPaymentFieldCode()
    {
        return 'ORDER_PAYMENT_ID';
    }

    public static function getOrderCheckFieldCode()
    {
        return 'ORDER_CHECK_ID';
    }

    public static function getOrderShipmentFieldCode()
    {
        return 'ORDER_SHIPMENT_ID';
    }
}
