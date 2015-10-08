<?php

namespace Metricso\Redis\PRedis\Commands;

use Predis\Command\StringSet;

class StringSetSerialized extends StringSet
{
	protected function filterArguments(array $arguments)
	{
		if (is_array($arguments[1]))
		{
			$arguments[1] = json_encode($arguments[1]);
		}

		return $arguments;
	}
}