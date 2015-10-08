<?php

namespace Metricso\Redis;

class Lock
{
	private $lockKey = 'lock:%s';

	private $redis;

	/**
	 * @param \Predis\Client | \PSRedis\HAClient $redis
	 */
	public function __construct($redis)
	{
		$this->redis = $redis;
	}

	/**
	 * @param string $resourceName
	 * @param int    $ttl - miliseconds
	 *
	 * @throws LockException
	 * @return string — lock tocken
	 */
	public function lockResource($resourceName, $ttl)
	{
		$token = uniqid("", true);

		$key = sprintf($this->lockKey, $resourceName);

		/** @noinspection PhpParamsInspection */
		$isSet = $this->redis->set($key, $token, 'PX', $ttl, 'NX');

		return $isSet ? $token : false;
	}

	/**
	 * @param $resourceName
	 * @param $lockToken
	 *
	 * @return bool
	 */
	public function unlock($resourceName, $lockToken)
	{
		$script = '
			if redis.call("GET", KEYS[1]) == ARGV[1] then
				return redis.call("DEL", KEYS[1])
			else
				return 0
			end
		';

		return !! $this->redis->eval($script, 1, $resourceName, $lockToken);
	}
}

class LockException extends \Exception {}