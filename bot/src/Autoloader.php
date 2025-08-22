<?php

namespace App;

class Autoloader
{
	private string $baseNamespace = 'App\\';
	private string $baseDir;

	public function __construct(string $baseDir)
	{
		$this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}

	public function register(): void
	{
		spl_autoload_register([$this, 'loadClass']);
	}

	public function loadClass(string $class): void
	{
		if (strpos($class, $this->baseNamespace) !== 0) {
			return;
		}

		$relative = substr($class, strlen($this->baseNamespace));
		$relativePath = str_replace('\\\
', DIRECTORY_SEPARATOR, $relative) . '.php';
		$file = $this->baseDir . $relativePath;
		if (is_file($file)) {
			require_once $file;
		}
	}
}