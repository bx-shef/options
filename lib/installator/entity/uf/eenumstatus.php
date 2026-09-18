<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
/**
 * Перечисление для статусов
 *
 * @see IEnumStatus
 */
enum EEnumStatus
{
	/** Не определен */
	case Undefined;
	/** Не обработано */
	case New;
	/** Обработка */
	case Process;
	/** Всё хорошо */
	case Success;
	/** Проблема */
	case Fail;
	
	public function getTitle(array $customTitle = []): string
	{
		return $customTitle[$this->name] ?: Loc::getMessage('shef.options_Installator_UF_EnumStatus_'.$this->name);
	}
	
	public function getValue(): string
	{
		return mb_strtoupper($this->name);
	}
}