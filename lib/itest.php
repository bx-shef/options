<?php
namespace Shef\Options;

use Bitrix\Main\Result;

interface ITest
{
	public function check(): Result;
}