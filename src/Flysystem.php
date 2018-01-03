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
use League\Flysystem\Config;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Util;
use Zend\Diactoros\Stream;

/**
 * The flysystem adapter.
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
	 * Constructor.
	 *
	 * @param Client $client
	 * @param string $prefix Possible values "disk:/" or "app:/"
	 */
	public function __construct(Client $client, $prefix = self::PREFIX_FULL)
	{
		if ( ! $client->getAccessToken())
		{
			throw new \InvalidArgumentException('Set the access token.');
		}

		$this->client = $client;
		$this->setPathPrefix($prefix);
	}

	/**
	 * Write a new file.
	 *
	 * @param string $path
	 * @param string $contents
	 * @param Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function write($path, $contents, Config $config)
	{
		$stream = fopen('php://temp', 'r+');

		if (fwrite($stream, $contents) === false)
		{
			return false;
		}

		fseek($stream, 0);

		$resource = $this->client->getResource($this->applyPathPrefix($path), 0);
		$this->applyEvents($resource, $config);

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

	/**
	 * Write a new file using a stream.
	 *
	 * @param string   $path
	 * @param resource $handler
	 * @param Config   $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function writeStream($path, $handler, Config $config)
	{
		$resource = $this->client->getResource($this->applyPathPrefix($path), 0);
		$this->applyEvents($resource, $config);

		if ( ! $resource->upload($handler, false))
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

	/**
	 * Update a file.
	 *
	 * @param string $path
	 * @param string $contents
	 * @param Config $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function update($path, $contents, Config $config)
	{
		$stream = fopen('php://temp', 'r+');

		if (fwrite($stream, $contents) === false)
		{
			return false;
		}

		fseek($stream, 0);

		$resource = $this->client->getResource($this->applyPathPrefix($path), 0);
		$this->applyEvents($resource, $config);

		if ( ! $resource->upload($stream, true))
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

	/**
	 * Update a file using a stream.
	 *
	 * @param string   $path
	 * @param resource $handler
	 * @param Config   $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function updateStream($path, $handler, Config $config)
	{
		$resource = $this->client->getResource($this->applyPathPrefix($path), 0);
		$this->applyEvents($resource, $config);

		if ( ! $resource->upload($handler, true))
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
				->delete(true);
		}
		catch (\Arhitector\Yandex\Client\Exception\NotFoundException $exc)
		{
			throw new FileNotFoundException($path);
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
	 * @param string $dirname directory name
	 * @param Config $config
	 *
	 * @return array|false
	 */
	public function createDir($dirname, Config $config)
	{
		try
		{
			$directories = explode('/', $dirname);

			$results = [];
			$path = '';
			foreach ($directories as $directory)
			{
				$path.= $directory . '/';
				$fullPath = $this->applyPathPrefix($path);
				$resource = $this->client->getResource($fullPath, 0);

				if (!$resource->has())
				{
					$this->applyEvents($resource, $config);
					$resource->create();
				}

				if ($resource->has())
				{
					if ($config->has('visibility') && $config->get('visibility') == 'public')
					{
						$resource->setPublish(true);
					}

					$results[] = $this->normalizeResponse($resource);
				}
			}
			if (sizeof($results) > 0)
			{
				return $results;
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
	 * @return bool
	 */
	public function setVisibility($path, $visibility)
	{
		if ($visibility != 'public' && $visibility != 'private')
		{
			return false;
		}

		$resource = $this->client->getResource($this->applyPathPrefix($path), 0);

		if (($visibility == 'public' && $resource->isPublish()) || ($visibility == 'private' && ! $resource->isPublish()))
		{
			return true;
		}

		return $resource->setPublish($visibility == 'public') instanceof Client\Resource\Opened;
	}

	/**
	 * Проверить, существует ли файл.
	 * Check whether a file exists.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public function has($path)
	{
		return $this->client->getResource($this->applyPathPrefix($path), 0)
			->has();
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
		$stream = new Stream('php://temp', 'r+');

		if ($this->client->getResource($this->applyPathPrefix($path), 0)
				->download($stream) !== false
		)
		{
			return [
				'path'     => $path,
				'contents' => (string) $stream
			];
		}
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
		$stream = fopen('php://temp', 'r+');

		if ($this->client->getResource($this->applyPathPrefix($path), 0)
				->download($stream) !== false
		)
		{
			fseek($stream, 0);

			return [
				'path'   => $path,
				'stream' => $stream
			];
		}
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

			} while ($iteration <= $iterations);
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
	 * Get url of the published file.
	 *
	 * @param string $path
	 *
	 * @return string|false
	 */
	public function getPublicUrl($path)
	{
		$metadata = $this->getMetadata($path);

		if (isset($metadata['public_url']))
		{
			return $metadata['public_url'];
		}

		return false;
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
	 * Apply events.
	 *
	 * @param Client\AbstractResource $resource
	 * @param Config                  $config
	 *
	 * @return Client\AbstractResource
	 */
	protected function applyEvents(Client\AbstractResource $resource, Config $config)
	{
		if ($config->has('events'))
		{
			foreach ((array) $config->get('events') as $event => $listeners)
			{
				foreach ((array) $listeners as $listener)
				{
					$resource->addListener($event, $listener);
				}
			}
		}
		
		return $resource;
	}
	
}
