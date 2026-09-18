<?php declare(strict_types=1);

namespace Shef\Options\TraitList;

use Bitrix\Main;
use Bitrix\Main\EventResult;
use Bitrix\Main\Result;
use Bitrix\Main\Error;

/**
 * Используется в событиях возврата значений событий
 */
trait EventResponse
{
	protected static function getModuleId(): string
	{
		return '';
	}

	protected static function returnMainEventSuccess(
		Result $response,
		string $handler = ''
	): EventResult
	{
		return new EventResult(
			EventResult::SUCCESS,
			$response->getData(),
			static::getModuleId(),
			$handler
		);
	}

	protected static function returnMainEventUndefined(
		string $handler = ''
	): EventResult
	{
		return new EventResult(
			EventResult::UNDEFINED,
			null,
			static::getModuleId(),
			$handler
		);
	}

	protected static function returnMainEventError(
		Result $response,
		string $handler = '',
		bool $isForModuleSale = false
	): EventResult
	{
		if($response->isSuccess())
		{
			throw new Main\ArgumentException('response not has errors');
		}

		$errorMessage = implode('. ', $response->getErrorMessages());
		$errorCode = '';

		foreach($response->getErrors() as $error)
		{
			if(mb_strlen($error->getCode()) > 0)
			{
				$errorCode = $error->getCode();
				break;
			}
		}

		if($isForModuleSale)
		{
			return new EventResult(
				EventResult::ERROR,
				\Bitrix\Sale\ResultError::create(new Error($errorMessage, $errorCode)),
				static::getModuleId(),
				$handler
			);
		}

		return new EventResult(
			EventResult::ERROR,
			(new \Bitrix\Main\Entity\EntityError($errorMessage, $errorCode)),
			static::getModuleId(),
			$handler
		);
	}
}