<?php declare(strict_types=1);

namespace Shef\Options\TraitList\Constants;

use Bitrix\Main\SiteTable;
use Bitrix\Main\EO_Site;

/**
 * Используется для получения данных текущего сайта
 */
trait Site
{
	public static function getBaseSiteId(): string
	{
		$siteLid = \Bitrix\Main\Context::getCurrent()->getSite();
		if($siteLid)
		{
			return $siteLid;
		}
		
		if (
			defined('SITE_ID')
			&& defined('LANGUAGE_ID')
		)
		{
			if(SITE_ID !== LANGUAGE_ID)
			{
				return SITE_ID;
			}
		}
		
		/** @var EO_Site $site */
		$site = SiteTable::getList([
			'order' => [
				'DEF'=> 'ASC',
				'SORT' => 'ASC'
			],
			'filter' => [
				'ACTIVE' => 'Y'
			],
			'select' => ['LID'],
			'limit' => 1,
			'cache' => ['ttl' => 86400],
		])->fetchObject();
		
		if($site)
		{
			return $site->getLid();
		}
		
		return 's1';
	}
}