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
		/** @noinspection PhpUndefinedMethodInspection */
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
		$key = sprintf($this->lockKey, $resourceName);

		$script = '
			if redis.call("GET", KEYS[1]) == ARGV[1] then
				return redis.call("DEL", KEYS[1])
			else
				return 0
			end
		';

        return $this->redis->eval($script, 1, $key, $lockToken);
	}
}

class LockException extends \Exception {}