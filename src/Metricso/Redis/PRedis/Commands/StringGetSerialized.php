<?php namespace Metricso\Redis\PRedis\Commands;

use Predis\Command\StringGet;

class StringGetSerialized extends StringGet
{
	public function parseResponse($data)
	{
		$json = json_decode($data, true);

		if (json_last_error() === JSON_ERROR_NONE)
		{
			return $json;
		}

		return $data;
	}
}