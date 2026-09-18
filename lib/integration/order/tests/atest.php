<?php
namespace Shef\Options\Integration\Order\Tests;

use Bitrix\Main\Result;
use Bitrix\Main\Error;

abstract class ATest
	implements ITest
{
	/** @var \Bitrix\Crm\Order\Order $order*/
	protected $order;
	public function __construct(\Bitrix\Crm\Order\Order &$order)
	{
		$this->order = $order;
		return $this;
	}

	abstract public function check(): \Bitrix\Main\Result;
}