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
	private $hosts = [];
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

	const DEFAULT_MASTER_NAME = 'mymaster';

	/**
	 * @param array $parameters
	 * @param array $hosts
	 * @param bool|true $useSentinel
	 * @throws RedisSentinelConfigurationException
	 */
	public function __construct(array $parameters = [], array $hosts = [], $useSentinel = true)
	{
		if (empty($hosts))
		{
			throw new RedisSentinelConfigurationException('You must specify at least one sentinel host.');
		}

		$this->usesSentinel = $useSentinel;
		$this->parameters = $parameters;

		$this->hosts = $hosts;
	}


	private function makeSentinels(array $hosts)
	{
		$sentinels = [];
		foreach($hosts as $host) {
			$sentinels[] = $this->makeHostFromString($host);
		}

		return $sentinels;
	}

	/**
	 * @param $sentinel
	 *
	 * @return array
	 */
	private function makeHostFromString($sentinel)
	{
		$sentinel = explode(':', $sentinel);

		return new Client($sentinel[0], $sentinel[1]);
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
	 * @throws ConnectionError
	 * @throws \PSRedis\Exception\ConfigurationError
	 */
	private function resolveSentinelConnection()
	{
		$sentinels = $this->makeSentinels($this->hosts);
		$masterDiscovery = $this->masterDiscovery($sentinels);

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
		$redisHost = reset($this->hosts);

		$connectionDetails = array_combine($template, $redisHost);
		$connectionDetails = array_merge($connectionDetails, $this->parameters);

		return new PRedisClient($connectionDetails);
	}

	/**
	 * @return array
	 */
	public function getSentinels()
	{
		return $this->hosts;
	}

	/**
	 * @return string
	 */
	private function getMasterName()
	{
		return self::DEFAULT_MASTER_NAME;
	}

	/**
	 * @param $sentinels
	 * @return MasterDiscovery
	 */
	private function masterDiscovery($sentinels)
	{
		$masterDiscovery = new MasterDiscovery($this->getMasterName());

		foreach ($sentinels as $sentinel)
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

}
