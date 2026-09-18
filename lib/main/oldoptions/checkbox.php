<?php

namespace Shef\Options\Main\Options;

class Checkbox
	extends AOption
{
	// region Get/Set ////
	public function getInputValue(): string
	{
		return 'Y';
	}
	// endregion ////
}