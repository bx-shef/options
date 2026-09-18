<?php declare(strict_types=1);

namespace Shef\Options\Options;

class Config
	extends Singleton
{
	protected array $hashmap = [];
	protected array $cache = [];

	public function getValue(string $key): string
	{
		return $this->hashmap[$key];
	}

	public function setValue(string $key, string $value): static
	{
		$this->hashmap[$key] = $value;
		return $this;
	}
	
	public function toArray(): array
	{
		return $this->hashmap;
	}

	public function push(): void
	{
		$this->cache[] = $this->hashmap;
	}

	public function restore(): void
	{
		if(!empty($this->cache))
		{
			$this->hashmap = array_pop($this->cache);
		}
	}
}