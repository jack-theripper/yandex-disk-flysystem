<?php

/**
 * Часть библиотеки для работы с сервисами Яндекса
 *
 * @package    Arhitector\Yandex\Disk\Adapter
 * @sins       2.0
 * @author     Arhitector
 * @license    MIT License
 * @copyright  2016 Arhitector
 * @link       https://github.com/jack-theripper
 */
namespace Arhitector\Yandex\Disk\Adapter;

use Arhitector\Yandex\Disk as Client;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Util;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Stream;

/**
 * Адаптер для Flysystem.
 * Adapter Flysystem.
 *
 * @package Arhitector\Yandex\Disk\Adapter
 */
class Flysystem extends AbstractAdapter
{

	/**
	 * @const   application folder
	 */
	const PREFIX_APP = 'app:/';

	/**
	 * @const   all drive
	 */
	const PREFIX_FULL = 'disk:/';

	/**
	 * @var Client
	 */
	protected $client;

	/**
	 * @deprecated
	 * @var Client\Resource\Closed
	 */
	protected $resource;

	/**
	 * @deprecated
	 * @var array
	 */
	protected $mapProperties = [
		'mime_type' => 'mimetype'
	];

	/**
	 * Конструктор.
	 * Constructor.
	 *
	 * @param Client $client
	 * @param string $prefix    Может быть "disk:/" или "app:/"
	 *                          Possible values "disk:/" or "app:/"
	 */
	public function __construct(Client $client, $prefix = self::PREFIX_FULL)
	{
		if ( ! $client->getAccessToken())
		{
			throw new \InvalidArgumentException('Установите Access Token.');
		}

		$this->client = $client;
		$this->setPathPrefix($prefix);
	}

	/**
	 * Write a new file.
	 *
	 * @param string                   $path
	 * @param string                   $contents
	 * @param \League\Flysystem\Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function write($path, $contents, \League\Flysystem\Config $config)
	{
		$stream = fopen('php://temp', 'r+');

		if (fwrite($stream, $contents) === false)
		{
			return false;
		}

		fseek($stream, 0);

		try
		{
			$resource = $this->client->getResource($this->applyPathPrefix($path), 0);

			if ( ! $resource->upload($stream, false))
			{
				return false;
			}

			$result = $this->normalizeResponse($resource);

			if ($visibility = $config->get('visibility'))
			{
				if ($this->setVisibility($path, $visibility))
				{
					$result['visibility'] = $visibility;
				}
			}

			return $result;
		}
		catch (\Exception $exc)
		{

		}

		return false;
	}

	/**
	 * Write a new file using a stream.
	 *
	 * @param string                   $path
	 * @param resource                 $resource
	 * @param \League\Flysystem\Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function writeStream($path, $resource, \League\Flysystem\Config $config)
	{
		// TODO: Implement writeStream() method.
	}
	
	/**
	 * Update a file.
	 *
	 * @param string                   $path
	 * @param string                   $contents
	 * @param \League\Flysystem\Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function update($path, $contents, \League\Flysystem\Config $config)
	{
		// TODO: Implement update() method.
	}

	/**
	 * Update a file using a stream.
	 *
	 * @param string                   $path
	 * @param resource                 $resource
	 * @param \League\Flysystem\Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function updateStream($path, $resource, \League\Flysystem\Config $config)
	{
		// TODO: Implement updateStream() method.
	}

	/**
	 * Rename a file.
	 *
	 * @param string $path
	 * @param string $newpath
	 *
	 * @return bool
	 * @throws FileExistsException
	 * @throws FileNotFoundException
	 */
	public function rename($path, $newpath)
	{
		try
		{
			return (bool) $this->client->getResource($this->applyPathPrefix($path), 0)
				->move($this->applyPathPrefix($newpath));
		}
		catch (\Arhitector\Yandex\Disk\Exception\AlreadyExistsException $exc)
		{
			throw new FileExistsException($newpath);
		}
		catch (\Arhitector\Yandex\Client\Exception\NotFoundException $exc)
		{
			throw new FileNotFoundException($path);
		}
		catch (\Exception $exc)
		{
			return false;
		}
	}

	/**
	 * Copy a file.
	 *
	 * @param string $path
	 * @param string $newpath
	 *
	 * @return bool
	 * @throws FileExistsException
	 * @throws FileNotFoundException
	 */
	public function copy($path, $newpath)
	{
		try
		{
			return (bool) $this->client->getResource($this->applyPathPrefix($path), 0)
				->copy($this->applyPathPrefix($newpath));
		}
		catch (\Arhitector\Yandex\Disk\Exception\AlreadyExistsException $exc)
		{
			throw new FileExistsException($newpath);
		}
		catch (\Arhitector\Yandex\Client\Exception\NotFoundException $exc)
		{
			throw new FileNotFoundException($path);
		}
		catch (\Exception $exc)
		{
			return false;
		}
	}

	/**
	 * Delete a file.
	 *
	 * @param string $path
	 *
	 * @return bool
	 * @throws FileNotFoundException
	 */
	public function delete($path)
	{
		try
		{
			return (bool) $this->client->getResource($this->applyPathPrefix($path), 0)
				->delete();
		}
		catch (\Arhitector\Yandex\Client\Exception\NotFoundException $exc)
		{
			throw new FileNotFoundException($path);
		}
		catch (\Exception $exc)
		{
			return false;
		}
	}

	/**
	 * Delete a directory.
	 *
	 * @param string $dirname
	 *
	 * @return bool
	 */
	public function deleteDir($dirname)
	{
		return $this->delete($dirname);
	}

	/**
	 * Create a directory.
	 *
	 * @param string                   $dirname directory name
	 * @param \League\Flysystem\Config $config
	 *
	 * @return array|false
	 */
	public function createDir($dirname, \League\Flysystem\Config $config)
	{
		try
		{
			$resource = $this->client->getResource($this->applyPathPrefix($dirname), 0)
				->create();

			if ($resource->has())
			{
				if ($config->has('visibility') && $config->get('visibility') == 'public')
				{
					$resource->setPublish(true);
				}

				return $this->normalizeResponse($resource);
			}
		}
		catch (\Exception $exc)
		{

		}

		return false;
	}

	/**
	 * Set the visibility for a file.
	 *
	 * @param string $path
	 * @param string $visibility
	 *
	 * @return array|false file meta data
	 */
	public function setVisibility($path, $visibility)
	{
		if ($visibility != 'public' && $visibility != 'private')
		{
			return false;	
		}
		
		try
		{
			$resource = $this->client->getResource($this->applyPathPrefix($path), 0);

			if (($visibility == 'public' && $resource->isPublish())
				|| ($visibility == 'private' && ! $resource->isPublish())
			)
			{
				return true;
			}

			return $resource->setPublish($visibility == 'public') instanceof Client\Resource\Opened;
		}
		catch (\Exception $exc)
		{

		}

		return false;
	}

	/**
	 * Проверить, существует ли файл.
	 * Check whether a file exists.
	 *
	 * @param string $path
	 *
	 * @return array|bool|null
	 */
	public function has($path)
	{
		return $this->client->getResource($this->applyPathPrefix($path), 0)->has();
	}

	/**
	 * Read a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function read($path)
	{
		try
		{
			$stream = new Stream('php://temp', 'r+');

			if ($this->client->getResource($this->applyPathPrefix($path), 0)
				->download($stream) !== false)
			{
				return [
					'path' => $path,
					'contents' => (string) $stream
				];
			}
		}
		catch (\Exception $exc)
		{

		}
		
		return false;
	}

	/**
	 * Read a file as a stream.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function readStream($path)
	{
		try
		{
			$stream = fopen('php://temp', 'r+');

			if ($this->client->getResource($this->applyPathPrefix($path), 0)
				->download($stream) !== false)
			{
				fseek($stream, 0);

				return [
					'path' => $path,
					'stream' => $stream
				];
			}
		}
		catch (\Exception $exc)
		{

		}

		return false;
	}

	/**
	 * Вывести содержимое каталога.
	 * List contents of a directory.
	 *
	 * @param string $directory
	 * @param bool   $recursive
	 *
	 * @return array
	 */
	public function listContents($directory = '', $recursive = false)
	{
		$listing = [];
		
		try
		{
			$resource = $this->client->getResource($this->applyPathPrefix(trim($directory, '/. ')), 100);

			if ( ! $resource->isDir())
			{
				return [];
			}

			$total_count = $resource->get('total', $resource->items->count());
			$iterations = ceil($total_count / 100);
			$iteration = 1;

			do
			{
				/**
				 * @var Client\Resource\Closed $item
				 */
				foreach ($resource->items as $item)
				{
					$listing[] = $this->normalizeResponse($item);

					if ($recursive && $item->isDir())
					{
						$listing = array_merge($listing,
							$this->listContents($this->removePathPrefix($item->getPath()), true));
					}
				}

				$resource->setOffset($resource->get('limit', 0) * $iteration);
				++$iteration;
			}
			while ($iteration <= $iterations);
		}
		catch (\Exception $exc)
		{
			return [];
		}

		return $listing;
	}

	/**
	 * Получить все метаданные файла или каталога.
	 * Get all the meta data of a file or directory.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getMetadata($path)
	{
		try
		{
			return $this->normalizeResponse($this->client->getResource($this->applyPathPrefix($path), 0));
		}
		catch (\Exception $exc)
		{
			return false;
		}
	}
	
	/**
	 * Get all the meta data of a file or directory.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getSize($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * Get the mimetype of a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getMimetype($path)
	{
		$metadata = $this->getMetadata($path);

		if (isset($metadata['mime_type']))
		{
			return [
				'mimetype' => $metadata['mime_type']
			];
		}

		return false;
	}

	/**
	 * Get the timestamp of a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getTimestamp($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * Get the visibility of a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getVisibility($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * Привести ответ от Яндекс.Диска в требуемый формат.
	 * Normalize a Yandex.Disk response.
	 *
	 * @param Client\AbstractResource $resource
	 *
	 * @return array
	 */
	protected function normalizeResponse(Client\AbstractResource $resource)
	{
		$normalized = [
			'type'       => $resource->isFile() ? 'file' : 'dir',
			'path'       => ltrim($this->removePathPrefix($resource->getPath()), '/ '),
			'timestamp'  => strtotime($resource->get('modified', '')),
			'visibility' => $resource->isPublish() ? 'public' : 'private'
		];

		$normalized = array_merge($resource->toArray(), $normalized, pathinfo($normalized['path']));

		if ($normalized['type'] == 'file')
		{
			$normalized['size'] = $resource->size;
		}

		$normalized['dirname'] = Util::normalizeDirname($normalized['dirname']);

		return $normalized;
	}

	/**
	 * Если ранее этот ресурс кэшировался, получить его.
	 * Get the resource from the cache.
	 *
	 * @deprecated  не используется.
	 *
	 * @param $path
	 *
	 * @return Client\Resource\Closed|bool
	 */
	protected function getCachedResource($path)
	{
		if ( ! empty($this->resource) && $this->resource->getPath() == $path)
		{
			return $this->resource;
		}

		return false;
	}
	
}