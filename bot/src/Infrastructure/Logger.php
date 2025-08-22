<?php

namespace App\Infrastructure;

class Logger
{
	private string $file;

	public function __construct(string $file)
	{
		$this->file = $file;
		$dir = dirname($file);
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}
	}

	public function info(string $message, array $context = []): void
	{
		$this->write('INFO', $message, $context);
	}

	public function error(string $message, array $context = []): void
	{
		$this->write('ERROR', $message, $context);
	}

	private function write(string $level, string $message, array $context): void
	{
		$line = sprintf("%s [%s] %s %s\n", date('c'), $level, $message, $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '');
		file_put_contents($this->file, $line, FILE_APPEND);
	}
}