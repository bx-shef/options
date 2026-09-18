<?php

namespace Shef\Options;


class Config
	extends Singleton
{
	protected array $hashmap = [];

	public function getValue(string $key): string
	{
		return $this->hashmap[$key];
	}

	public function setValue(string $key, string $value): self
	{
		$this->hashmap[$key] = $value;
		return $this;
	}
	
	public function toArray(): array
	{
		return $this->hashmap;
	}
}