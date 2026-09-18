<?php declare(strict_types=1);

namespace Shef\Options\Installator;

use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Type\Dictionary;

/**
 * Установщик
 */
class Manager
	implements IInstallator
{
	// region Construct ////
	public function __construct(
		private readonly Strategy\IStrategy $strategy
	)
	{
	}
	// endregion ////
	
	/**
	 * По списку добавляет сущность
	 *
	 * @param Dictionary $list
	 * @return Result
	 */
	public function build(Dictionary $list): Result
	{
		/** @var IEntity $entity */
		
		$result = new Result();
		foreach($list as $entity)
		{
			$response = $this->process($entity);
			if(!$response->isSuccess())
			{
				$result->addErrors($response->getErrors());
			}
		}
		
		return $result;
	}

	/**
	 * Обрабатывает сущность
	 * 
	 * @param IEntity $entity
	 * @return Result
	 */
	public function process(IEntity $entity): Result
	{
		$result = new Result();

		$response = $this->strategy->process($entity);
		if(!$response->isSuccess())
		{
			return $result->addErrors($response->getErrors());
		}

		return $result->setData($response->getData());
	}
}