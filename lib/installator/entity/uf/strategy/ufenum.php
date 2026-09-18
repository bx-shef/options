<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF\Strategy;

use Shef\Options\Installator\Entity\UF;

class UFEnum
	extends AStrategy
	implements IStrategy
{
	/**
	 * @inheritDoc
	 */
	public function getType(): UF\EType
	{
		return UF\EType::Enumeration;
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getSettings(): array
	{
		return [
			'display' => 'UI',
			'listHeight' => 1,
			'captionNoValue' => '',
			'defaultValue' => '',
			'showNoValue' => 'Y'
		];
	}
	
	/**
	 * @inheritDoc
	 */
	public function process(UF\AEntity|UF\AEntityEnum $field): array
	{
		$enumList = [];
		$index = 0;
		
		/** @var UF\EnumItem $enum */
		foreach($field->getEnums()->toArray() as $enum)
		{
			$keyEnum = null;
			if($enum->getId() > 0)
			{
				$keyEnum = $enum->getId();
			}
			else
			{
				$keyEnum = 'n'.$index;
				$index++;
			}
			
			$enumList[$keyEnum] = $enum->getInstallSettings();
		}
		
		return array_merge(
			parent::process($field),
			[
				'enum' => $enumList,
			]
		);
	}


}