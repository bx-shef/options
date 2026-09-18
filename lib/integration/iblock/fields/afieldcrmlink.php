<?php
namespace Shef\Options\Integration\IBlock\Fields;

use Bitrix\Main\Loader;
use Shef\IBlock\Lists\Base\Fields\Types;

abstract class AFieldCrmLink
	extends AField
{
	public function getType()
	{
		return Types::CRM_LINK;
	}

	protected function getCrmEntityType()
	{
		return \CCrmOwnerType::Undefined;
	}

	public function getCrmEntityTypeId(): int
	{
		return (int)\CCrmOwnerType::ResolveID($this->getCrmEntityType());
	}

	public function prepareValue($entityId, Array $options = [])
	{
		if($this->getCrmEntityType() === \CCrmOwnerType::Undefined)
		{
			return [];
		}

		if(
			!(
				Loader::includeModule('location')
				&& Loader::includeModule('crm')
			)
		)
		{
			return [];
		}

		$entityId = intval($entityId);
		$response = \CCrmEntitySelectorHelper::PrepareEntityInfo(
			isset($options['ENTITY_TYPE']) ? $options['ENTITY_TYPE'] : $this->getCrmEntityType()
			,$entityId
			,array(
				'ENTITY_EDITOR_FORMAT' => true,
				'IS_HIDDEN' => false,
				'USER_PERMISSIONS' => \CCrmPerms::GetCurrentUserPermissions(),
				'REQUIRE_REQUISITE_DATA' => true,
				'REQUIRE_EDIT_REQUISITE_DATA' => true,
				'REQUIRE_MULTIFIELDS' => true,
				'NORMALIZE_MULTIFIELDS' => true,
				'NAME_TEMPLATE' => \Bitrix\Crm\Format\PersonNameFormatter::getFormat(),
			)
		);

		$result = [
			'ID' => $response['id']
			,'TITLE' => (
				(int)$response['id'] > 0
				? $response['title']
				: ''
			)
		];

		// region Communication ////
		foreach($response['advancedInfo']['multiFields'] as $communication)
		{
			if(!isset($result[$communication['TYPE_ID']]))
			{
				$result[$communication['TYPE_ID']] = [];
			}
			$result[$communication['TYPE_ID']][] = $communication['VALUE'];
		}
		// endregion ////
		// region RQ ////
		foreach($response['advancedInfo']['requisiteData'] as &$value)
		{
			if(!isset($result['REQUISITE']))
			{
				$result['REQUISITE'] = [];
				$result['BANK'] = [];
			}

			// region Requisite ////
			if(strlen($value['requisiteData']) > 0)
			{
				$value['requisiteData'] = $this->decode($value['requisiteData']);
			}

			$rq = [];
			foreach($value['requisiteData']['viewData']['fields'] as $rqField)
			{
				$rq[$rqField['name']] = $rqField['textValue'];
			}

			//  region Address ////
			if(
				isset($value['requisiteData']['fields']['RQ_ADDR'])
				&& is_array($value['requisiteData']['fields']['RQ_ADDR'])
			)
			{
				foreach($value['requisiteData']['fields']['RQ_ADDR'] as $type => $address)
				{
					try {
						$address = \bitrix\Main\Web\Json::decode($address);
						$address = $address['fieldCollection'][\Bitrix\Location\Entity\Location\Type::ADDRESS_LINE_1];
						$address = htmlspecialcharsback($address);
					}
					catch (\Exception $e)
					{
						$address = [];
					}
					$rq['ADDRESS_'.\Bitrix\Crm\EntityAddressType::resolveName($type)] = $address;
				}
			}
			// endregion ////

			$title = '';
			if($this->getCrmEntityType() === \CCrmOwnerType::CompanyName)
			{

				if(strlen($rq['RQ_COMPANY_NAME']) > 0)
				{
					$title = $rq['RQ_COMPANY_NAME'];
				}
				elseif(strlen($rq['RQ_COMPANY_FULL_NAME']) > 0)
				{
					$title = $rq['RQ_COMPANY_FULL_NAME'];
				}
				elseif(strlen($rq['RQ_INN']) > 0)
				{
					$title = 'УНП '.$rq['RQ_INN'];
				}
				else
				{
					$title = $result['TITLE'].' #'.$value['requisiteId'];
				}
			}
			elseif($this->getCrmEntityType() === \CCrmOwnerType::ContactName)
			{
				$title = $result['TITLE'].' #'.$value['requisiteId'];
			}
			else
			{
				$title = $result['ID'].' #'.$value['requisiteId'];
			}

			$result['REQUISITE'][] = [
				'FIELDS' => $rq
				,'TITLE' => $title
				,'SELECTED' => false
				,'VALUE' => 'RQ_'.$value['requisiteId']
			];
			// endregion ////
			// region bank ////
			if(
				isset($value['requisiteData']['bankDetailFieldsList'])
				&& is_array($value['requisiteData']['bankDetailFieldsList'])
			)
			{
				$banks = array_values($value['requisiteData']['bankDetailFieldsList']);
				foreach($banks as $bank)
				{
					$result['BANK'][] = [
						'FIELDS' => [
							'RQ_BANK_NAME' => $bank['RQ_BANK_NAME']
							,'RQ_BANK_ADDR' => $bank['RQ_BANK_ADDR']
							,'RQ_BIK' => $bank['RQ_BIK']
							,'RQ_SWIFT' => $bank['RQ_SWIFT']
							,'RQ_ACC_NUM' => $bank['RQ_ACC_NUM']
							,'COMMENTS' => $bank['COMMENTS']
						]
						,'TITLE' => $bank['RQ_ACC_NUM'].' - '.$bank['RQ_BANK_NAME']
						,'SELECTED' => false
						,'VALUE' => 'RQB_'.$bank['ID']
					];
				}
			}
			// endregion ////
		}
		if(isset($value)){unset($value);}
		// endregion ////

		/*/
		_pr([
			$this->getCrmEntityType()
			,$entityId
			,$result
		]);
		//*/
		return $result;
	}
}