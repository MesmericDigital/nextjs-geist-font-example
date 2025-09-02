<?php

namespace BBPCore\Support;

/**
 * View Factory
 * 
 * Handles view rendering for the BBP Core plugin
 */
class ViewFactory
{
    /**
     * View paths
     */
    protected $paths = [];

    /**
     * Shared data
     */
    protected $shared = [];

    /**
     * View extensions
     */
    protected $extensions = ['php', 'html'];

    /**
     * Constructor
     */
    public function __construct($paths = [])
    {
        $this->paths = is_array($paths) ? $paths : [$paths];
    }

    /**
     * Add a view path
     */
    public function addPath($path)
    {
        $this->paths[] = rtrim($path, '/') . '/';
        return $this;
    }

    /**
     * Get all view paths
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Make a view
     */
    public function make($view, $data = [])
    {
        $path = $this->findView($view);
        
        if (!$path) {
            throw new \Exception("View [{$view}] not found.");
        }

        return new View($path, array_merge($this->shared, $data));
    }

    /**
     * Check if view exists
     */
    public function exists($view)
    {
        return $this->findView($view) !== null;
    }

    /**
     * Share data with all views
     */
    public function share($key, $value = null)
    {
        if (is_array($key)) {
            $this->shared = array_merge($this->shared, $key);
        } else {
            $this->shared[$key] = $value;
        }

        return $this;
    }

    /**
     * Get shared data
     */
    public function getShared()
    {
        return $this->shared;
    }

    /**
     * Find a view file
     */
    protected function findView($view)
    {
        $view = str_replace('.', '/', $view);

        foreach ($this->paths as $path) {
            foreach ($this->extensions as $extension) {
                $file = $path . $view . '.' . $extension;
                
                if (file_exists($file)) {
                    return $file;
                }
            }
        }

        // Check in theme directory
        $themeView = locate_template("bbp-core/{$view}.php");
        if ($themeView) {
            return $themeView;
        }

        return null;
    }

    /**
     * Render a view and return the output
     */
    public function render($view, $data = [])
    {
        return $this->make($view, $data)->render();
    }

    /**
     * Add a view extension
     */
    public function addExtension($extension)
    {
        if (!in_array($extension, $this->extensions)) {
            $this->extensions[] = $extension;
        }

        return $this;
    }
}

/**
 * View Class
 */
class View
{
    /**
     * View path
     */
    protected $path;

    /**
     * View data
     */
    protected $data;

    /**
     * Constructor
     */
    public function __construct($path, $data = [])
    {
        $this->path = $path;
        $this->data = $data;
    }

    /**
     * Render the view
     */
    public function render()
    {
        ob_start();
        
        extract($this->data);
        
        try {
            include $this->path;
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
        
        return ob_get_clean();
    }

    /**
     * Get view data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get view path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Add data to view
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Convert view to string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (\Exception $e) {
            return '';
        }
    }
}
