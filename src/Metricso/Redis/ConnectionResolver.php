<?php namespace Metricso\Redis;

use Metricso\Redis\BackoffStrategy\FiveTimes;
use Metricso\Redis\Exceptions\RedisSentinelConfigurationException;
use Predis\Client as PRedisClient;
use PSRedis\Client;
use PSRedis\Exception\ConnectionError;
use PSRedis\HAClient;
use PSRedis\MasterDiscovery;

class ConnectionResolver
{
	/**
	 * @var bool
	 */
	private $usesSentinel = false;
	/**
	 * @var array
	 */
	private $sentinels = [];
	/**
	 * @var
	 */
	private $connection;
	/**
	 * @var
	 */
	private $master;
	/**
	 * @var array
	 */
	private $parameters;

	const DEFAULT_MASTER_HOST = '127.0.0.1:6379';
	const DEFAULT_REDIS_HOSTNAME = 'redis';
	const DEFAULT_MASTER_PORT = 6379;
	const DEFAULT_MASTER_NAME = 'mymaster';

	/**
	 * @param array $parameters
	 */
	public function __construct(array $parameters = [])
	{
		$this->usesSentinel = (bool)getenv('REDIS_USE_SENTINEL');
		$this->parameters = $parameters;
	}

	/**
	 * @return PRedisClient|HAClient
	 */
	public function resolve()
	{
		if (!$this->usesSentinel)
		{
			$connection = $this->resolveSimpleConnection();
		}
		else
		{
			$connection = $this->resolveSentinelConnection();
		}

		return $connection;
	}

	/**
	 * @return HAClient
	 * @throws RedisSentinelConfigurationException
	 * @throws \PSRedis\Exception\ConfigurationError
	 * @throws \PSRedis\Exception\ConnectionError
	 */
	private function resolveSentinelConnection()
	{
		$this->setSentinels();

		$masterDiscovery = $this->masterDiscovery();

		$backoffStrategy = new FiveTimes();
		$masterDiscovery->setBackoffStrategy($backoffStrategy);

		try {
			$this->master = $masterDiscovery->getMaster();
		} catch(ConnectionError $e) {
			throw new ConnectionError(sprintf('%s - [%s attempts]', $e->getMessage(), $backoffStrategy->getAttempts()));
		}

		return new HAClient($masterDiscovery);
	}

	/**
	 * @return PRedisClient
	 */
	private function resolveSimpleConnection()
	{
		$template = ['host', 'port'];
		$redisHost = $this->getRedisMasterHost();

		$connectionDetails = array_combine($template, $redisHost);
		$connectionDetails = array_merge($connectionDetails, $this->parameters);

		return new PRedisClient($connectionDetails);
	}

	/**
	 * @throws RedisSentinelConfigurationException
	 */
	private function setSentinels()
	{
		$i = 0;

		do
		{
			$sentinelName = sprintf('REDIS_SENTINEL_HOST_%d', $i++);
			$sentinel = getenv($sentinelName);

			if (!$sentinel)
			{
				if (empty($this->sentinels))
				{
					throw new RedisSentinelConfigurationException('You must specify at least one sentinel using REDIS_SENTINEL_HOST_[0-9] ENV.');
				}

				break;
			}

			$this->sentinels[] = $this->getSentinelFromEnv($sentinel);
		}
		while (true);
	}

	/**
	 * @param $sentinel
	 *
	 * @return array
	 */
	private function getSentinelFromEnv($sentinel)
	{
		$sentinel = explode(':', $sentinel);

		return new Client($sentinel[0], $sentinel[1]);
	}

	/**
	 * @return array
	 */
	public function getSentinels()
	{
		return $this->sentinels;
	}

	/**
	 * @return string
	 */
	private function getMasterName()
	{
		return getenv('REDIS_MASTER_NAME') ?: self::DEFAULT_MASTER_NAME;
	}

	/**
	 * @return MasterDiscovery
	 */
	private function masterDiscovery()
	{
		$masterDiscovery = new MasterDiscovery($this->getMasterName());

		foreach ($this->getSentinels() as $sentinel)
		{
			$masterDiscovery->addSentinel($sentinel);
		}

		return $masterDiscovery;
	}

	/**
	 * @return mixed
	 */
	public function getConnection()
	{
		return $this->connection;
	}

	/**
	 * @return mixed
	 */
	public function getMaster()
	{
		return $this->master;
	}

	/**
	 * @return array
	 */
	public function getRedisMasterHost()
	{
		$masterHost = self::DEFAULT_MASTER_HOST;

		if (getenv('REDIS_HOST'))
		{
			$masterHost = getenv('REDIS_HOST');
		}
		elseif (gethostbyname(self::DEFAULT_REDIS_HOSTNAME) <> self::DEFAULT_REDIS_HOSTNAME)
		{
			$masterHost = gethostbyname(self::DEFAULT_REDIS_HOSTNAME);
		}

		$masterHost = explode(':', $masterHost);

		if (!isset($masterHost[1]))
		{
			$masterHost[1] = self::DEFAULT_MASTER_PORT;
		}

		return $masterHost;
	}

}
