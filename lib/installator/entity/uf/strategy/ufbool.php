<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF\Strategy;

use Shef\Options\Installator\Entity\UF;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
class UFBool
	extends AStrategy
	implements IStrategy
{
	/**
	 * @inheritDoc
	 */
	public function getType(): UF\EType
	{
		return UF\EType::Boolean;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getSettings(): array
	{
		return [
			'display' => 'CHECKBOX',
			'label' => [
				0 => Loc::getMessage('shef.options_Installator_UF_strategy_UFBool_N'),
				1 => Loc::getMessage('shef.options_Installator_UF_strategy_UFBool_Y')
			],
			'defaultValue' => 1,
			'labelCheckbox' => Loc::getMessage('shef.options_Installator_UF_strategy_UFBool_Label')
		];
	}
}