<?php
declare(strict_types=1);

namespace Shef\Options\Installator\UF\Type\Strategy;

use Shef\Options\Installator\UF\Type;

interface IStrategy
{
	public function process(Type\AUF $field): array;
}