<?php

namespace IQnection\FormBuilder\Cache;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Flushable;

class Cache implements Flushable
{
	use Configurable,
		Injectable;
	
	private static $cache_name = 'formbuilder';
	
	public static function generateCacheKey($arg1)
	{
		$args = func_get_args();
		return md5(json_encode($args));
	}
		
	public static function cacheInterface()
	{
		return Injector::inst()->get(CacheInterface::class . '.' . self::$cache_name);
	}
		
	public static function set($name, $data, $lifetime = 86400)
	{
		return self::cacheInterface()->set($name, $data, $lifetime);
	}
		
	public static function get($name)
	{
		return self::cacheInterface()->get($name);
	}
		
	public static function delete($name)
	{
		return self::cacheInterface()->delete($name);
	}
		
	public static function has($name)
	{
		return self::cacheInterface()->has($name);
	}
		
	public static function clear()
	{
		return self::cacheInterface()->clear();
	}
		
	public static function flush()
	{
		self::clear();
	}
}
