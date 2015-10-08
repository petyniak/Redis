<?php namespace Metricso\Redis\PRedis\Commands;

use Predis\Command\StringSetExpire;

class StringSetExpireSerialized extends StringSetExpire
{
	protected function filterArguments(array $arguments)
	{
		if (is_array($arguments[2]))
		{
			$arguments[2] = json_encode($arguments[2]);
		}

		return $arguments;
	}
}