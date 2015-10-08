<?php
namespace Metricso\Redis\BackoffStrategy;


use PSRedis\MasterDiscovery\BackoffStrategy;

class FiveTimes implements BackoffStrategy
{
	private $incrementalStrategy;

	private $attempts = 0;

	public function __construct()
	{
		$this->incrementalStrategy = new BackoffStrategy\Incremental(500, 2);
		$this->incrementalStrategy->setMaxAttempts(5);
	}

	public function reset()
	{
		$this->incrementalStrategy->reset();
	}

	public function getBackoffInMicroSeconds()
	{
		return $this->incrementalStrategy->getBackoffInMicroSeconds();
	}

	public function shouldWeTryAgain()
	{
		$result = $this->incrementalStrategy->shouldWeTryAgain();

		if (true === $result)
		{
			$this->attempts++;
		}

		return $result;
	}

	public function getAttempts()
	{
		return $this->attempts;
	}
}