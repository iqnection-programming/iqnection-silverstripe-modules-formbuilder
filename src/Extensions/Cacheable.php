<?php

namespace IQnection\FormBuilder\Extensions;

use SilverStripe\ORM\DataExtension;
use IQnection\FormBuilder\Cache\Cache;

class Cacheable extends DataExtension
{
	private static $cascade_caches = [];
	
	public function saveRecordCache($includeRelations = true)
	{
		$cache = $this->owner->configForCache($includeRelations);
		Cache::set(Cache::generateCacheKey($this->owner->getClassName(), $this->owner->ID, $this->owner->LastEdited), $cache);
		return $this->owner;
	}
	
	public function getRecordCache()
	{
		return Cache::get(Cache::generateCacheKey($this->owner->getClassName(), $this->owner->ID, $this->owner->LastEdited));
	}
	
	public function flushRecordCache()
	{
		Cache::delete(Cache::generateCacheKey($this->owner->getClassName(), $this->owner->ID, $this->owner->LastEdited));
		return $this->owner;
	}
	
	public function configForCache($includeRelations = true)
	{
		$cache = $this->owner->toMap();
		if ($includeRelations)
		{
			$this->owner->getRelationsCache($cache);
		}
		$this->extend('updateConfigForCache', $cache);
		return $cache;
	}
	
	public function getRelationsCache(&$cache)
    {
        // Get list of duplicable relation types
        $manyMany = $this->owner->manyMany();
        $hasMany = $this->owner->hasMany();
        $hasOne = $this->owner->hasOne();

        // Duplicate each relation based on type
        foreach($this->owner->Config()->get('cascade_caches') as $relation) {
            switch (true) {
                case array_key_exists($relation, $manyMany): 
					$cache[$relation] = $this->owner->getManyManyRelationCache($relation);
                    break;
                case array_key_exists($relation, $hasMany):
                    $cache[$relation] = $this->owner->getHasManyRelationCache($relation);
                    break;
                case array_key_exists($relation, $hasOne): 
                    $cache[$relation] = $this->owner->getHasOneRelationCache($relation);
                    break;
            }
        }
    }
	
	public function getManyManyRelationCache($relation)
	{
		$cache = [];
		$components = $this->owner->getComponents($relation);
		if ($components->Count())
		{
			foreach($components as $component)
			{
				if ($component->hasExtension(Cacheable::class))
				{
					$cache[] = $component->configForCache();
				}
				else
				{
					$cache[] = $component->toMap();
				}
			}
		}
		return $cache;
	}
	
	public function getHasManyRelationCache($relation)
	{
		$cache = [];
		$components = $this->owner->getComponents($relation);
		if ($components->Count())
		{
			foreach($components as $component)
			{
				if ($component->hasExtension(Cacheable::class))
				{
					$cache[] = $component->configForCache();
				}
				else
				{
					$cache[] = $component->toMap();
				}
			}
		}
		return $cache;
	}
	
	public function getHasOneRelationCache($relation)
	{
		$cache = null;
		$component = $this->owner->getComponent($relation);
		if ($component->Exists())
		{
			if ($component->hasExtension(Cacheable::class))
			{
				$cache = $component->configForCache();
			}
			else
			{
				$cache = $component->toMap();
			}
		}
		return $cache;
	}
}











