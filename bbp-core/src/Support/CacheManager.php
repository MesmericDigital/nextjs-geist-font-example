<?php

namespace BBPCore\Support;

/**
 * Cache Manager
 * 
 * Handles caching for the BBP Core plugin using WordPress transients
 */
class CacheManager
{
    /**
     * Cache prefix
     */
    protected $prefix = 'bbp_core_';

    /**
     * Default TTL (Time To Live) in seconds
     */
    protected $defaultTtl = 3600; // 1 hour

    /**
     * Constructor
     */
    public function __construct($prefix = null, $defaultTtl = null)
    {
        if ($prefix) {
            $this->prefix = $prefix;
        }

        if ($defaultTtl) {
            $this->defaultTtl = $defaultTtl;
        }
    }

    /**
     * Get a cached value
     */
    public function get($key, $default = null)
    {
        $value = get_transient($this->getKey($key));
        
        return $value !== false ? $value : $default;
    }

    /**
     * Store a value in cache
     */
    public function put($key, $value, $ttl = null)
    {
        $ttl = $ttl ?: $this->defaultTtl;
        
        return set_transient($this->getKey($key), $value, $ttl);
    }

    /**
     * Store a value in cache (alias for put)
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->put($key, $value, $ttl);
    }

    /**
     * Store a value in cache forever (until manually deleted)
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, YEAR_IN_SECONDS * 10); // 10 years
    }

    /**
     * Get a value from cache or store it if it doesn't exist
     */
    public function remember($key, $ttl, $callback)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = is_callable($callback) ? $callback() : $callback;
        
        $this->put($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Get a value from cache or store it forever if it doesn't exist
     */
    public function rememberForever($key, $callback)
    {
        return $this->remember($key, YEAR_IN_SECONDS * 10, $callback);
    }

    /**
     * Remove a value from cache
     */
    public function forget($key)
    {
        return delete_transient($this->getKey($key));
    }

    /**
     * Remove a value from cache (alias for forget)
     */
    public function delete($key)
    {
        return $this->forget($key);
    }

    /**
     * Check if a key exists in cache
     */
    public function has($key)
    {
        return get_transient($this->getKey($key)) !== false;
    }

    /**
     * Increment a cached value
     */
    public function increment($key, $value = 1)
    {
        $current = $this->get($key, 0);
        $new = $current + $value;
        
        $this->put($key, $new);
        
        return $new;
    }

    /**
     * Decrement a cached value
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, -$value);
    }

    /**
     * Clear all cache with the current prefix
     */
    public function flush()
    {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $this->prefix . '%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . $this->prefix . '%'
            )
        );
        
        return true;
    }

    /**
     * Get cache statistics
     */
    public function getStats()
    {
        global $wpdb;
        
        $transients = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $this->prefix . '%'
            )
        );
        
        $size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $this->prefix . '%'
            )
        );
        
        return [
            'count' => (int) $transients,
            'size' => (int) $size,
            'prefix' => $this->prefix,
            'default_ttl' => $this->defaultTtl
        ];
    }

    /**
     * Get all cached keys with the current prefix
     */
    public function getKeys()
    {
        global $wpdb;
        
        $keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $this->prefix . '%'
            )
        );
        
        return array_map(function($key) {
            return str_replace('_transient_' . $this->prefix, '', $key);
        }, $keys);
    }

    /**
     * Cache data with tags for group invalidation
     */
    public function tags($tags)
    {
        return new TaggedCache($this, $tags);
    }

    /**
     * Invalidate cache by tags
     */
    public function invalidateTags($tags)
    {
        $tags = is_array($tags) ? $tags : [$tags];
        
        foreach ($tags as $tag) {
            $tagKey = "tag:{$tag}";
            $keys = $this->get($tagKey, []);
            
            foreach ($keys as $key) {
                $this->forget($key);
            }
            
            $this->forget($tagKey);
        }
    }

    /**
     * Get the full cache key with prefix
     */
    protected function getKey($key)
    {
        return $this->prefix . $key;
    }

    /**
     * Set cache prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Get cache prefix
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set default TTL
     */
    public function setDefaultTtl($ttl)
    {
        $this->defaultTtl = $ttl;
        return $this;
    }

    /**
     * Get default TTL
     */
    public function getDefaultTtl()
    {
        return $this->defaultTtl;
    }
}

/**
 * Tagged Cache Class
 */
class TaggedCache
{
    /**
     * Cache manager instance
     */
    protected $cache;

    /**
     * Cache tags
     */
    protected $tags;

    /**
     * Constructor
     */
    public function __construct(CacheManager $cache, $tags)
    {
        $this->cache = $cache;
        $this->tags = is_array($tags) ? $tags : [$tags];
    }

    /**
     * Store a value with tags
     */
    public function put($key, $value, $ttl = null)
    {
        // Store the actual value
        $result = $this->cache->put($key, $value, $ttl);
        
        // Associate key with tags
        foreach ($this->tags as $tag) {
            $tagKey = "tag:{$tag}";
            $keys = $this->cache->get($tagKey, []);
            
            if (!in_array($key, $keys)) {
                $keys[] = $key;
                $this->cache->put($tagKey, $keys, YEAR_IN_SECONDS);
            }
        }
        
        return $result;
    }

    /**
     * Get a value (proxy to cache manager)
     */
    public function get($key, $default = null)
    {
        return $this->cache->get($key, $default);
    }

    /**
     * Remember a value with tags
     */
    public function remember($key, $ttl, $callback)
    {
        $value = $this->cache->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = is_callable($callback) ? $callback() : $callback;
        
        $this->put($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Flush all tagged cache
     */
    public function flush()
    {
        $this->cache->invalidateTags($this->tags);
    }
}
