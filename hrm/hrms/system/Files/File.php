<?php

/**
 * This file is part of the CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CodeIgniter\Files;

use CodeIgniter\Files\Exceptions\FileException;
use CodeIgniter\Files\Exceptions\FileNotFoundException;
use Config\Mimes;
use SplFileInfo;

/**
 * Wrapper for PHP's built-in SplFileInfo, with goodies.
 */
class File extends SplFileInfo
{
	/**
	 * The files size in bytes
	 *
	 * @var integer
	 */
	protected $size;

	/**
	 * Original MimeType
	 *
	 * @var null|string
	 */
	protected $originalMimeType = null;

	//--------------------------------------------------------------------

	/**
	 * Run our SplFileInfo constructor with an optional verification
	 * that the path is really a file.
	 *
	 * @param string  $path
	 * @param boolean $checkFile
	 */
	public function __construct(string $path, bool $checkFile = false)
	{
		if ($checkFile && ! is_file($path))
		{
			throw FileNotFoundException::forFileNotFound($path);
		}

		parent::__construct($path);
	}

	//--------------------------------------------------------------------

	/**
	 * Retrieve the file size.
	 *
	 * Implementations SHOULD return the value stored in the "size" key of
	 * the file in the $_FILES array if available, as PHP calculates this based
	 * on the actual size transmitted.
	 *
	 * @return integer The file size in bytes
	 */
	#[\ReturnTypeWillChange]
	public function getSize()
	{
		return $this->size ?? ($this->size = parent::getSize());
	}

	/**
	 * Retrieve the file size by unit.
	 *
	 * @param string $unit
	 *
	 * @return integer|string
	 */
	public function getSizeByUnit(string $unit = 'b')
	{
		switch (strtolower($unit))
		{
			case 'kb':
				return number_format($this->getSize() / 1024, 3);
			case 'mb':
				return number_format(($this->getSize() / 1024) / 1024, 3);
			default:
				return $this->getSize();
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Attempts to determine the file extension based on the trusted
	 * getType() method. If the mime type is unknown, will return null.
	 *
	 * @return string|null
	 */
	public function guessExtension(): ?string
	{
		return Mimes::guessExtensionFromType($this->getMimeType());
	}

	//--------------------------------------------------------------------

	/**
	 * Retrieve the media type of the file. SHOULD not use information from
	 * the $_FILES array, but should use other methods to more accurately
	 * determine the type of file, like finfo, or mime_content_type().
	 *
	 * @return string The media type we determined it to be.
	 */
	public function getMimeType(): string
	{
		if (! function_exists('finfo_open'))
		{
			// @codeCoverageIgnoreStart
			return $this->originalMimeType ?? 'application/octet-stream';
			// @codeCoverageIgnoreEnd
		}

		$finfo    = finfo_open(FILEINFO_MIME_TYPE);
		$mimeType = finfo_file($finfo, $this->getRealPath() ?: $this->__toString());
		finfo_close($finfo);
		return $mimeType;
	}

	//--------------------------------------------------------------------

	/**
	 * Generates a random names based on a simple hash and the time, with
	 * the correct file extension attached.
	 *
	 * @return string
	 */
	public function getRandomName(): string
	{
		$extension = $this->getExtension();
		$extension = empty($extension) ? '' : '.' . $extension;
		return time() . '_' . bin2hex(random_bytes(10)) . $extension;
	}

	//--------------------------------------------------------------------

	/**
	 * Moves a file to a new location.
	 *
	 * @param string      $targetPath
	 * @param string|null $name
	 * @param boolean     $overwrite
	 *
	 * @return File
	 */
	public function move(string $targetPath, string $name = null, bool $overwrite = false)
	{
		$targetPath  = rtrim($targetPath, '/') . '/';
		$name        = $name ?? $this->getBaseName();
		$destination = $overwrite ? $targetPath . $name : $this->getDestination($targetPath . $name);

		$oldName = $this->getRealPath() ?: $this->__toString();

		if (! @rename($oldName, $destination))
		{
			$error = error_get_last();
			throw FileException::forUnableToMove($this->getBasename(), $targetPath, strip_tags($error['message']));
		}

		@chmod($destination, 0777 & ~umask());

		return new File($destination);
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the destination path for the move operation where overwriting is not expected.
	 *
	 * First, it checks whether the delimiter is present in the filename, if it is, then it checks whether the
	 * last element is an integer as there may be cases that the delimiter may be present in the filename.
	 * For the all other cases, it appends an integer starting from zero before the file's extension.
	 *
	 * @param string  $destination
	 * @param string  $delimiter
	 * @param integer $i
	 *
	 * @return string
	 */
	public function getDestination(string $destination, string $delimiter = '_', int $i = 0): string
	{
		while (is_file($destination))
		{
			$info      = pathinfo($destination);
			$extension = isset($info['extension']) ? '.' . $info['extension'] : '';
			if (strpos($info['filename'], $delimiter) !== false)
			{
				$parts = explode($delimiter, $info['filename']);
				if (is_numeric(end($parts)))
				{
					$i = end($parts);
					array_pop($parts);
					$parts[]     = ++ $i;
					$destination = $info['dirname'] . '/' . implode($delimiter, $parts) . $extension;
				}
				else
				{
					$destination = $info['dirname'] . '/' . $info['filename'] . $delimiter . ++ $i . $extension;
				}
			}
			else
			{
				$destination = $info['dirname'] . '/' . $info['filename'] . $delimiter . ++ $i . $extension;
			}
		}
		return $destination;
	}

	//--------------------------------------------------------------------
}
