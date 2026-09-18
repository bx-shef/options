<?php

namespace Shef\Options\Integration\IBlock;

use Bitrix\Main\Result;
use Bitrix\Main\Error;

abstract class AElement
{
	/** @var \CIBlockElement */
	protected $obj = null;

	public function __construct()
	{
		\Bitrix\Main\Loader::includeModule('iblock');

		$this->obj = new \CIBlockElement();
	}

	public function finde(int $iblockId = 0, $value = '', array $params = [])
	{
		$conf = [
			"IBLOCK_ID" => $iblockId
		];

		if(is_array($value))
		{
			if(count($value) < 1)
			{
				return null;
			}
		}
		else
		{
			if(strlen($value) < 1)
			{
				return null;
			}
		}

		if(!isset($params['parse']))
		{
			$params['parse'] = 'by-xmlId';
		}
		switch($params['parse'])
		{
			case 'by-name':
				$conf["NAME"] = $value;
			break;
			case 'by-id':
				$conf["ID"] = $value;
			break;
			case 'by-article':
				$conf["PROPERTY_ARTICLE"] = $value;
			break;
			case 'by-xmlId':
			default:
				$conf["XML_ID"] = $value;
			break;
		}

		$cursor = $this->obj::GetList(
			[]
			, $conf
			, false, false
			, [
				'ID',
				'IBLOCK_ID',
				'ACTIVE'
			]
		);
		if(is_array($value))
		{
			$items = [];
			while($element = $cursor->GetNext())
			{
				$items[] = (int)$element['ID'];
			}

			return $items;
		}
		else
		{
			if($element = $cursor->GetNext())
			{
				$element['ID'] = (int)$element['ID'];
			}
			else
			{
				$element = ['ID' => 0];
			}

			return $element['ID'];
		}
	}

	private function makeCodeUniq(string &$code, int $iblockId): void
	{
		$conf = [
			"IBLOCK_ID" => $iblockId,
			"CODE" => $code
		];

		$cursor = $this->obj->GetList(
			[]
			, $conf
			, false, false, ['ID']
		);

		if($element = $cursor->GetNext())
		{
			$code = $code.'-'.time();
		}
	}

	public function insert(array $item, array $params = []): array
	{
		$result = [];
		$conf = [
			"IBLOCK_ID" => $item['IBLOCK_ID']
		];

		if(!isset($params['parse']))
		{
			$params['parse'] = 'by-xmlId';
		}
		switch($params['parse'])
		{
			case 'by-name':
				$conf["NAME"] = $item['NAME'];
			break;
			case 'by-id':
				$conf["ID"] = $item['ID'];
			break;
			case 'by-xmlId':
			default:
				$conf["XML_ID"] = $item['XML_ID'];
			break;
		}

		$cursor = $this->obj->GetList(
			[]
			, $conf
			, false, false, ['ID']
		);

		if($element = $cursor->GetNext())
		{
			$element['ID'] = intval($element['ID']);
		}
		else
		{
			$element['ID'] = null;
		}
		if(isset($item['CODE']))
		{
			$this->makeCodeUniq($item['CODE'], $item['IBLOCK_ID']);
		}

		if(null === $element['ID'])
		{
			$result['operation'] = 'Add';
			$result['id'] = $this->add($item);
		}
		else
		{
			$result['operation'] = 'Update';
			if($params['parse'] == 'by-name')
			{
				unset($item['XML_ID']);
			}
			$result['id'] = $this->update($element['ID'], $item);
		}

		return $result;
	}

	public function getInfoById(int $elementId = 0)
	{
		$cursor = $this->obj->GetList(
			[]
			, ['ID' => $elementId]
			, false, false, [
				'ID',
				'IBLOCK_ID',
				'DETAIL_PAGE_URL',
				'XML_ID',
				'IBLOCK_TYPE_ID',
				'CREATED_BY',
				'NAME'
				,
				'DETAIL_PICTURE',
				'PREVIEW_PICTURE',
				'ACTIVE'
			]
		);

		$element = $cursor->GetNext();

		return $element;
	}

	public function getListInner(
		int $iblockId
		, array $filter
		, array $order = ['ID' => 'DESC']
		, bool $isActive = true
		, array $select = []
		, int $limit = 0
	): array
	{
		$result = [];
		if($isActive === true)
		{
			$filter['ACTIVE'] = 'Y';
		}
		else
		{
			$filter['SHOW_NEW'] = 'Y';
		}

		if(intval($limit) > 0)
		{
			$nav = [
				'nPageSize' => intval($limit)
			];
		}
		else
		{
			$nav = false;
		}


		if(count($select) > 0)
		{
			$selectGetlist = $select;
		}
		else
		{
			$selectGetlist = [
				'ID',
				'IBLOCK_ID',
				'IBLOCK_TYPE_ID',
				'XML_ID',
				'NAME',
				'DETAIL_TEXT',
				'IBLOCK_SECTION_ID',
				'ACTIVE',
				'TIMESTAMP_X',
				'SORT'
			];
		}
		$filter['IBLOCK_ID'] = intval($iblockId);
		$res = $this->obj::GetList(
			$order
			, $filter
			, false, $nav
			, $select
		);

		while($ob = $res->GetNextElement())
		{
			$data = $ob->GetFields();
			if(!is_array($data))
			{
				continue;
			}

			if(count($select) > 0)
			{
				$item = $data;
			}
			else
			{
				$item = [
					'ID' => $data['ID'],
					'TIMESTAMP_X' => $data['TIMESTAMP_X'],
					'IBLOCK_ID' => $data['IBLOCK_ID'],
					'ACTIVE' => $data['ACTIVE'],
					'XML_ID' => $data['XML_ID'],
					'IBLOCK_TYPE_ID' => $data['IBLOCK_TYPE_ID'],
					'NAME' => $data['NAME'],
					'DETAIL_TEXT' => $data['DETAIL_TEXT'],
					'IBLOCK_SECTION_ID' => $data['IBLOCK_SECTION_ID']
				];
			}

			$props = $ob->GetProperties();
			foreach($props as $value)
			{
				$value['CODE'] = strlen($value['CODE']) > 0
					? $value['CODE']
					: $value['ID'];

				$item['PROPERTY_'.$value['CODE']] = $value['~VALUE'];

				if($value['USER_TYPE'] == 'directory')
				{
					$item['PROPERTY_'.$value['CODE'].'_HL'] = $value['USER_TYPE_SETTINGS']['TABLE_NAME'];
				}

				if($value['WITH_DESCRIPTION'] == 'Y')
				{
					$item['PROPERTY_'.$value['CODE'].'_DESCRIPTION'] = $value['DESCRIPTION'];
				}

				if(strlen($value['VALUE_XML_ID']) > 0)
				{
					$item['PROPERTY_'.$value['CODE'].'_XML_ID'] = $value['VALUE_XML_ID'];
				}

				if(strlen($value['VALUE_ENUM_ID']) > 0)
				{
					$item['PROPERTY_'.$value['CODE'].'_ENUM_ID'] = $value['VALUE_ENUM_ID'];
				}

				// $item['~PROPERTY_'.$value['CODE']] = $value; ////
			}

			$result[] = $item;
		}
		return $result;
	}

	public function updatePropsEx(int $iblockId = 0, int $elementId = 0, array $props = []): \Bitrix\Main\Result
	{
		$result = new \Bitrix\Main\Result();

		$this->obj->SetPropertyValuesEx(
			$elementId,
            $iblockId,
            $props
		);

		$result->setData([
			'entityId' => $elementId,
			'props' => $props
		]);

		\CIBlock::clearIblockTagCache($iblockId);

		\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(
			$iblockId, $elementId
		);

		return $result;
	}

	public function updateProps($iblockId = 0, $elementId = 0, array $props = []): bool
	{

		$this->obj->SetPropertyValuesEx(
			$elementId
			, $iblockId
			, $props
		);

		return true;
	}

	public function delete(int $itemId = 0): \Bitrix\Main\Result
	{
		$result = new \Bitrix\Main\Result();
		$res = $this->obj->Delete($itemId);
		if(!$res)
		{
			return $result->addError(new \Bitrix\Main\Error('Error delete element: '.$this->obj->LAST_ERROR));
		}

		return $result;
	}

	public function add(array $params = []): int
	{
		$itemId = $this->obj->Add($params, false, true, true);
		$res = (int)$itemId > 0;

		if(!$res)
		{
			throw new \Exception('Error add element: '.$this->obj->LAST_ERROR);
		}
		return (int)$itemId;
	}

	public function update(int $itemId, array $params = []): int
	{
		if(isset($params['ID']))
		{
			unset($params['ID']);
		}

		if(
		isset($params['PROPERTY_VALUES']['MORE_PHOTO'])
		)
		{
			$this->removeAllFilesFromProperty($itemId, $params['IBLOCK_ID'], 'MORE_PHOTO');
		}

		if(
		isset($params['PROPERTY_VALUES']['INSTRUCTIONS'])
		)
		{
			$this->removeAllFilesFromProperty($itemId, $params['IBLOCK_ID'], 'INSTRUCTIONS');
		}

		if(
		isset($params['PROPERTY_VALUES']['I_REEL'])
		)
		{
			$this->removeAllFilesFromProperty($itemId, $params['IBLOCK_ID'], 'I_REEL');
		}
		$res = $this->obj->Update($itemId, $params, false, true, true, false);

		if(!$res)
		{
			throw new \Exception('Error update element ['.$itemId.']: '.$this->obj->LAST_ERROR);
		}

		return (int)$itemId;
	}

	public function updateForEvent($elementId = 0, $iblockId = 0): int
	{
		return $this->update($elementId, []);
	}

	private function removeAllFilesFromProperty(int $elementId = 0, int $iblockId = 0, string $propertyCode = ''): void
	{
		$cursor = $this->obj::GetProperty($iblockId, $elementId, 'sort', 'asc', ['CODE' => $propertyCode]);

		$arr = [];
		while($arItem = $cursor->Fetch())
		{
			if($arItem['VALUE'])
			{
				$arr[$arItem['PROPERTY_VALUE_ID']] = [
					'VALUE' => ['del' => 'Y']
				];

				\CFile::Delete($arItem['VALUE']);
			}
		}

		$this->obj->SetPropertyValueCode(
			$elementId
			, $propertyCode
			, $arr
		);
	}

	// @deprecate /////
	public function resizePreviewAndDetailPictures(int $elementId, array $params = [])
	{
		$item = $this->getInfoById($elementId);
		$itemDetailPicture = (int)$item['DETAIL_PICTURE'];

		if($itemDetailPicture < 1)
		{
			throw new \Exception('empty detail picture for element = '.$elementId);
		}


		$detailFileOrig = \CFile::MakeFileArray($itemDetailPicture);

		$from = $detailFileOrig['tmp_name'];
		$to = \Bitrix\Main\Application::getDocumentRoot().'/upload/tmp/img-ori-'.$elementId.$detailFileOrig['name'];

		return true;


		$max_w = $params['maxW'] ?? 200;
		$max_h = $params['maxH'] ?? 200;

		$max_wD = $params['maxWD'] ?? 600;
		$max_hD = $params['maxWH'] ?? 600;

		///////////////////////////////
		$confUpdate = [];
		$img = \CFile::ResizeImageGet(
			$itemDetailPicture
			, [
				'width' => $max_w,
				'height' => $max_h
			]
			, BX_RESIZE_IMAGE_PROPORTIONAL_ALT
			, false
		);
		$confUpdate['PREVIEW_PICTURE'] = \CFile::MakeFileArray(
			$img['src']
		);
		$updateResult = $this->obj->Update($item['ID'], $confUpdate);

		///////////////////////////////
		$img = \CFile::ResizeImageGet(
			$itemDetailPicture
			, [
				'width' => 1,
				'height' => 1
			]
			, BX_RESIZE_IMAGE_PROPORTIONAL_ALT
			, false
		);

		$confUpdate = [];
		$img = \CFile::ResizeImageGet(
			$itemDetailPicture
			, [
				'width' => $max_wD,
				'height' => $max_hD
			]
			, BX_RESIZE_IMAGE_PROPORTIONAL_ALT
			, false
		);
		$confUpdate['DETAIL_PICTURE'] = \CFile::MakeFileArray(
			$img['src']
		);

		///////////////////////////////

		$updateResult = $this->obj->Update($item['ID'], $confUpdate);
		//_pr([$itemDetailPicture, $confUpdate, $img]);

		if($updateResult)
		{
			return true;
		}
		else
		{
			throw new \Exception('error update picture '.$this->obj->LAST_ERROR.' for '.$elementId);
		}

	}
}