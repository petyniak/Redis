<?php

namespace Metricso\Redis\Cache;

interface CacheControlInterface
{
	/**
	 * @param $cacheKey
	 *
	 * @return mixed
	 */
	public function flushLike($cacheKey);
}