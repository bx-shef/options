<?php

namespace Shef\Options\Installator\Crm;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;

class Deal
	extends AInstaller
{
	protected static function getUFType(): string
	{
		return 'CRM_'.\CCrmOwnerType::DealName;
	}
}