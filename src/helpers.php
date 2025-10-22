<?php

if (!function_exists('env')) {
    /**
     * Get the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Remove quotes if present
        if (is_string($value)) {
            $value = trim($value, '\'"');
        }

        return $value;
    }
}
