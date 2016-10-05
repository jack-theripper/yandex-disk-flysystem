## Адаптер thephpleague/flysystem для Яндекс.Диск REST API (Flysystem Adapter for Yandex.Disk REST API).

**В разработке**

Посетите [https://oauth.yandex.ru](<https://oauth.yandex.ru>), создайте приложение и получите отладочный OAuth-токен.

Visit [https://oauth.yandex.com](<https://oauth.yandex.com>) and create application. Get your OAuth token.

## Установка (Installation)

```
$ composer require arhitector/yandex-disk-flysystem dev-master
```

## Использование (Usage)

Вы можете использовать папку приложения в качестве корневого пути, для этого используйте `Arhitector\Yandex\Disk\Adapter::PREFIX_APP` (значение "`app:/`") вторым параметром `$prefix`. По умолчанию используется `Arhitector\Yandex\Disk\Adapter::PREFIX_FULL`, что эквивалентно "`disk:/`" - доступ ко всему диску.

```php
public __construct(Disk $client [, $prefix = 'disk:/'])
```

**$client** экземпляр объекта `Arhitector\Yandex\Disk` **с уже установленным** OAuth-токеном.

**$prefix** чтобы использовать папку приложения передайте `Arhitector\Yandex\Disk\Adapter::PREFIX_APP`


`Arhitector\Yandex\Disk\Adapter::PREFIX_FULL` access to the entire disc (**default**).

`Arhitector\Yandex\Disk\Adapter::PREFIX_APP` see more info <https://tech.yandex.com/disk/api/concepts/app-folders-docpage/>

```php
// set a token before creation of the adapter
$client = new Arhitector\Yandex\Disk([string $accessToken]);

// or
$client->setAccessToken(string $accessToken);

// create adapter
$adapter = new Arhitector\Yandex\Disk\Adapter\Flysystem($client);

// or with app folder
$adapter = new Arhitector\Yandex\Disk\Adapter\Flysystem($client, Arhitector\Yandex\Disk\Adapter\Flysystem::PREFIX_APP);

// create Filesystem
$filesystem = new League\Flysystem\Filesystem($adapter);

// and use
$contents = $filesystem->listContents();

var_dump($contents);
```

Регистрация слушателей событий

```php
$filesystem->write('path', 'contents', [
    'events' => [
        'event-name 1' => 'listener', /* function, etc. */
        'event-name 2' => 'other listener'
    ]
]);
```

Регистрация более чем одного слушателя

```php
$filesystem->write('path', 'contents', [
    'events' => [
        'event-name' => [
            'listener 1' /* function, etc. */,
            'listener 2' /* function, etc. */,
            'listener 3' /* function, etc. */
        ]
    ]
]);
```

## Лицензия (License)

MIT License (MIT)