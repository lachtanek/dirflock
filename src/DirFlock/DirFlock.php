<?php

class DirFlock
{

	const MAX_TRIES = 5;
	const DEAD_TIME = 20;

	/** @var bool */
	private static $handlerRegistered = false;

	/** @var array */
	private static $locks = [];

	private static function tryRegisterHandler()
	{
		if (self::$handlerRegistered)
			return;

		register_shutdown_function([__CLASS__, 'shutdownHandler']);
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private static function getLockPath($path)
	{
		return $path . '..lock';
	}

	/**
	 * @param string $path path to directory (parent must be writable)
	 * @param int $type same as flock
	 * @return boolean
	 */
	public static function lock($path, $type)
	{
		self::tryRegisterHandler();

		$path = self::getLockPath($path);
		// check if parent dir is writeable, so we can skip then timeout
		if (!is_writable(dirname($path)))
			return false;

		if ($type === LOCK_UN)
		{
			if (isset(self::$locks[$path]))
			{
				rmdir($path);
				unset(self::$locks[$path]);
			}

			return true;
		}
		elseif (!($type & LOCK_SH) && !($type & LOCK_EX))
			return false;

		// remove dead lock if there is one for some reason
		if (file_exists($path . '/.') && time() - filemtime($path . '/.') > self::DEAD_TIME)
			rmdir($path);

		for ($tries = 0; $tries <= self::MAX_TRIES; ++$tries)
		{
			if (@mkdir($path, 0777))
			{
				self::$locks[$path] = (bool) ($type & LOCK_SH);
				return true;
			}
			elseif ($type & LOCK_SH)
				return self::$locks[$path];
			elseif ($type & LOCK_NB)
				return false;

			sleep(1);
		}

		return false;
	}

	public static function shutdownHandler()
	{
		foreach (self::$locks as $lockPath => $_)
			rmdir(self::getLockPath($lockPath));
	}

}
