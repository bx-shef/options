<?php declare(strict_types=1);

namespace Shef\Options\Installator\Entity\UF;

/**
 * Перечисление привязки UF
 */
enum EEntityId: string
{
	case Empty = 'empty';
	case CrmLead = 'CRM_LEAD';
	case CrmDeal = 'CRM_DEAL';
	case CrmContact  = 'CRM_CONTACT';
	case CrmCompany = 'CRM_COMPANY';
	case CrmQuote = 'CRM_QUOTE';
	case CrmInvoice = 'CRM_INVOICE';
	case CrmSmartInvoice = 'CRM_SMART_INVOICE';
	case CrmSmartDocument = 'CRM_SMART_DOCUMENT';
	case CrmRequisite = 'CRM_REQUISITE';
	
	public static function generate(
		string $id
	): string
	{
		return sprintf(
			'CRM_%s',
			$id
		);
	}
	
	public static function generateUfCode(
		string $entityId,
		string $baseCode
	): string
	{
		return sprintf(
			'UF_%s_%s',
			$entityId,
			$baseCode
		);
	}
}