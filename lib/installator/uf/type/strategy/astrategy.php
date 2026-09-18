<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type\Strategy;

use Shef\Options\Installator\UF\Type;

abstract class AStrategy
	implements IStrategy
{
	public static function getSettings(): array
	{
		return [];
	}

	public function process(Type\AUF $field): array
	{
		return [
			'ENTITY_ID' => $field->getEntityType(),
			'FIELD_NAME' => $field->getName(),
			'XML_ID' => $field->getName(),
			'EDIT_FORM_LABEL' => $field->getEditFormInfo(),
			'LIST_COLUMN_LABEL' => $field->getListColumnInfo(),
			'LIST_FILTER_LABEL' => $field->getListFilterInfo(),
			'ERROR_MESSAGE' => $field->getErrorInfo(),
			'HELP_MESSAGE' => $field->getHelpInfo(),
			'USER_TYPE_ID' => static::Type,
			'SORT' => $field->getSort(),
			'MULTIPLE' => $field->isMultiple() ? 'Y' : 'N',
			'MANDATORY' => $field->isRequired() ? 'Y' : 'N',
			'SHOW_FILTER' => $field->isShowFilter() ? 'Y' : 'N',
			'SHOW_IN_LIST' => $field->isShowInList() ? 'Y' : 'N',
			'EDIT_IN_LIST' => $field->isEditable() ? 'Y' : 'N',
			'IS_SEARCHABLE' => $field->isSearchable() ? 'Y' : 'N',
			'SETTINGS' => static::getSettings()
		];
	}
}