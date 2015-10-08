<?php

namespace Metricso\Redis\Cache;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\CacheProvider;
use Metricso\Redis\PRedis\Commands\DeleteMatching;
use Metricso\Redis\PRedis\Commands\StringGetSerialized;
use Metricso\Redis\PRedis\Commands\StringSetExpireSerialized;
use Metricso\Redis\PRedis\Commands\StringSetSerialized;

class PRedisCache extends CacheProvider implements CacheControlInterface
{
	private $client;

	/**
	 * @param \Predis\Client | \PSRedis\HAClient $client
	 */
	public function __construct($client)
	{
		$this->client = $client;
		$this->registerCustomCommands();
	}

	public function flushLike($match)
	{
		return $this->client->deleteMatching("*{$match}*");
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		$result = $this->client->getSerialized($id);
		if (null === $result)
		{
			return false;
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		return $this->client->exists($id);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifeTime = 0)
	{
		if ($lifeTime > 0)
		{
			$response = $this->client->setExSerialized($id, $lifeTime, $data);
		}
		else
		{
			$response = $this->client->setSerialized($id, $data);
		}

		return $response === true || $response == 'OK';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		return $this->client->del($id) > 0;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		$response = $this->client->flushdb();

		return $response === true || $response == 'OK';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats()
	{
		$info = $this->client->info();

		return [
			Cache::STATS_HITS => false,
			Cache::STATS_MISSES => false,
			Cache::STATS_UPTIME => $info['Server']['uptime_in_seconds'],
			Cache::STATS_MEMORY_USAGE => $info['Memory']['used_memory'],
			Cache::STATS_MEMORY_AVAILABLE => false
		];
	}

	private function registerCustomCommands()
	{
		$this->client->getProfile()->defineCommand('setExSerialized', StringSetExpireSerialized::class);
		$this->client->getProfile()->defineCommand('setSerialized', StringSetSerialized::class);
		$this->client->getProfile()->defineCommand('getSerialized', StringGetSerialized::class);
		$this->client->getProfile()->defineCommand('deleteMatching', DeleteMatching::class);
	}
}
