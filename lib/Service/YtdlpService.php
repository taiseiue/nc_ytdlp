<?php

declare(strict_types=1);

namespace OCA\NcYtdlp\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class YtdlpService {
	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Run yt-dlp and save the result into the user's Nextcloud storage.
	 *
	 * @return string The title of the downloaded media
	 * @throws \RuntimeException on failure
	 */
	public function download(string $userId, string $url, string $format, string $destination, ?string $cookie = null, ?string $outputTemplate = null): string {
		$userFolder = $this->rootFolder->getUserFolder($userId);

		// Resolve destination folder, creating it if necessary
		$destFolder = $this->resolveDestinationFolder($userFolder, $destination);

		// Prepare a unique temp directory
		$tempDir = sys_get_temp_dir() . '/nc_ytdlp_' . bin2hex(random_bytes(8));
		if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
			throw new \RuntimeException("Failed to create temporary directory: $tempDir");
		}

		try {
			return $this->runYtdlp($url, $format, $tempDir, $destFolder, $cookie, $outputTemplate);
		} finally {
			$this->removeDirectory($tempDir);
		}
	}

	private function resolveDestinationFolder(Folder $userFolder, string $destination): Folder {
		// Normalize path
		$destination = '/' . ltrim($destination, '/');
		if ($destination === '/') {
			return $userFolder;
		}

		try {
			$node = $userFolder->get($destination);
			if (!($node instanceof Folder)) {
				throw new \RuntimeException("Destination path is not a folder: $destination");
			}
			return $node;
		} catch (NotFoundException) {
			// Create the folder hierarchy
			return $userFolder->newFolder($destination);
		}
	}

	private function runYtdlp(string $url, string $format, string $tempDir, Folder $destFolder, ?string $cookie = null, ?string $outputTemplate = null): string {
		$filenameTemplate = ($outputTemplate !== null && $outputTemplate !== '') ? $outputTemplate : '%(title)s.%(ext)s';
		$outputTemplate = $tempDir . '/' . $filenameTemplate;

		$commonArgs = ['yt-dlp', '--no-playlist', '--no-part'];

		if ($cookie !== null && $cookie !== '') {
			$commonArgs[] = '--add-header';
			$commonArgs[] = 'Cookie:' . $cookie;
		}

		if ($format === 'mp3') {
			$args = array_merge($commonArgs, [
				'-x',
				'--audio-format', 'mp3',
				'--audio-quality', '0',
				'-o', $outputTemplate,
				'--', $url,
			]);
		} else {
			$args = array_merge($commonArgs, [
				'-f', 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best',
				'--merge-output-format', 'mp4',
				'-o', $outputTemplate,
				'--', $url,
			]);
		}

		$this->logger->info('YtdlpService: Starting download', [
			'url' => $url,
			'format' => $format,
		]);

		[$stdout, $stderr, $returnCode] = $this->execProcess($args);

		if ($returnCode !== 0) {
			$errorOutput = trim($stderr ?: $stdout);
			$this->logger->error('YtdlpService: yt-dlp failed', [
				'url' => $url,
				'returnCode' => $returnCode,
				'output' => $errorOutput,
			]);
			throw new \RuntimeException(
				"yt-dlp exited with code $returnCode. " . mb_substr($errorOutput, -500)
			);
		}

		// Find files created in temp dir
		$files = array_values(array_filter(
			glob($tempDir . '/*') ?: [],
			fn ($f) => is_file($f)
		));

		if (empty($files)) {
			throw new \RuntimeException(
				"yt-dlp completed but no output file was found. Output: " . mb_substr($stdout, -500)
			);
		}

		// If multiple files exist (e.g., a video and its thumbnail), pick the main media file
		$downloadedFile = $this->pickMainFile($files, $format);
		$filename = $this->resolveFilename($destFolder, basename($downloadedFile));
		$title = pathinfo($filename, PATHINFO_FILENAME);

		$this->logger->info('YtdlpService: Writing file to Nextcloud', [
			'filename' => $filename,
		]);

		$stream = fopen($downloadedFile, 'r');
		if ($stream === false) {
			throw new \RuntimeException("Failed to open downloaded file: $downloadedFile");
		}

		$ncFile = null;
		try {
			$ncFile = $destFolder->newFile($filename);
			$ncFile->putContent($stream);
		} catch (\Throwable $e) {
			// Remove the empty file Nextcloud created before putContent failed
			if ($ncFile !== null) {
				try {
					$ncFile->delete();
				} catch (\Throwable) {
				}
			}
			throw $e;
		} finally {
			if (is_resource($stream)) {
				fclose($stream);
			}
		}

		return $title;
	}

	/**
	 * Execute an external process safely without a shell.
	 * Uses stream_select to read stdout and stderr concurrently, avoiding pipe-buffer deadlocks.
	 *
	 * @param string[] $args
	 * @return array{0: string, 1: string, 2: int} [stdout, stderr, exitCode]
	 */
	private function execProcess(array $args): array {
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		// Passing an array avoids shell interpretation (PHP 7.4+)
		$process = proc_open($args, $descriptors, $pipes);

		if (!is_resource($process)) {
			throw new \RuntimeException("Failed to start yt-dlp process");
		}

		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);

		$stdout = '';
		$stderr = '';

		// Read both pipes simultaneously to prevent deadlock when yt-dlp
		// fills the stderr buffer while we are blocking on stdout (or vice versa).
		while (!feof($pipes[1]) || !feof($pipes[2])) {
			$read = array_filter([$pipes[1], $pipes[2]], fn ($p) => is_resource($p) && !feof($p));
			if (empty($read)) {
				break;
			}
			$write = null;
			$except = null;

			if (stream_select($read, $write, $except, 5) === false) {
				break;
			}

			foreach ($read as $pipe) {
				$chunk = fread($pipe, 65536);
				if ($chunk !== false && $chunk !== '') {
					if ($pipe === $pipes[1]) {
						$stdout .= $chunk;
					} else {
						$stderr .= $chunk;
					}
				}
			}
		}

		fclose($pipes[1]);
		fclose($pipes[2]);
		$returnCode = proc_close($process);

		return [$stdout, $stderr, $returnCode];
	}

	/**
	 * Pick the main media file from a list (prefer mp4/mp3 over thumbnails etc.).
	 */
	private function pickMainFile(array $files, string $format): string {
		$preferred = $format === 'mp3' ? 'mp3' : 'mp4';
		foreach ($files as $file) {
			if (str_ends_with(strtolower($file), '.' . $preferred)) {
				return $file;
			}
		}
		// Fallback: return the largest file
		usort($files, fn ($a, $b) => filesize($b) - filesize($a));
		return $files[0];
	}

	/**
	 * Ensure the filename is unique within the folder by appending a counter if needed.
	 */
	private function resolveFilename(Folder $folder, string $filename): string {
		if (!$folder->nodeExists($filename)) {
			return $filename;
		}

		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		$base = pathinfo($filename, PATHINFO_FILENAME);
		$i = 1;

		do {
			$candidate = $ext ? "$base ($i).$ext" : "$base ($i)";
			$i++;
		} while ($folder->nodeExists($candidate));

		return $candidate;
	}

	private function removeDirectory(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($items as $item) {
			$realPath = $item->getRealPath();
			if ($realPath === false) {
				continue;
			}
			$item->isDir() ? rmdir($realPath) : unlink($realPath);
		}
		rmdir($dir);
	}
}
