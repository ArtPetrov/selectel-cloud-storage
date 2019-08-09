<?php

use PHPUnit\Framework\TestCase;
use ArtPetrov\Selectel\CloudStorage\CloudStorage;

class FilesTest extends TestCase
{
    protected function setUp(): void
    {
        Mockery::close();
    }

    /** @test */
    function file_can_be_retrieved_from_container()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $requests = [
                $this->listContainersRequest(),
                $this->listSingleFileRequest('container1', 'web/index.html', 'text/html'),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $file = $container->files()->find('/web/index.html');

        self::assertEquals('container1', $file->container());
        self::assertEquals('web/index.html', $file->path());
        self::assertEquals('web', $file->directory());
        self::assertEquals('index.html', $file->name());
        self::assertEquals(1024, $file->size());
        self::assertEquals('text/html', $file->contentType());
        self::assertEquals('2013-05-27T15:31:25.325041', $file->lastModifiedAt());
        self::assertEquals(md5('test'), $file->etag());
        $this->assertFalse($file->isDeleted());
    }

    /** @test */
    function file_contents_can_be_retrieved_as_string()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $requests = [
                $this->listContainersRequest(),
                $this->listSingleFileRequest('container1', 'web/index.html', 'text/html'),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }

            $readRequest = $this->readFileRequest('/container1/web/index.html');

            $api->shouldReceive('request')
                ->with($readRequest['method'], $readRequest['url'])
                ->andReturn($readRequest['response']);
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $file = $container->files()->find('/web/index.html');
        $expected = file_get_contents(__DIR__.'/fixtures/test.html');

        self::assertEquals($expected, $file->read());
    }

    /** @test */
    function file_contents_can_be_retrieved_as_stream()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $requests = [
                $this->listContainersRequest(),
                $this->listSingleFileRequest('container1', 'web/index.html', 'text/html'),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }

            $readRequest = $this->readFileRequest('/container1/web/index.html');

            $api->shouldReceive('request')
                ->with($readRequest['method'], $readRequest['url'])
                ->andReturn($readRequest['response']);
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $file = $container->files()->find('/web/index.html');
        $expected = file_get_contents(__DIR__.'/fixtures/test.html');
        $buffer = '';
        $stream = $file->readStream();

        self::assertIsResource($stream);


        while (!feof($stream)) {
            $buffer .= fread($stream, 1024);
        }

        fclose($stream);

        self::assertEquals($expected, $buffer);
    }

    /** @test */
    function file_can_be_renamed()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $requests = [
                $this->listContainersRequest(),
                $this->listSingleFileRequest('container1', 'web/index.html', 'text/html'),
                $this->renameFileRequest('/container1/web/index.html', '/container1/web/index2.html'),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }

            $deleteRequest = $this->deleteFileRequest('/container1/web/index.html');

            $api->shouldReceive('request')
                ->with($deleteRequest['method'], $deleteRequest['url'])
                ->andReturn($deleteRequest['response']);
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $file = $container->files()->find('/web/index.html');

        self::assertEquals('index.html', $file->name());

        $file->rename('index2.html');

        self::assertEquals('index2.html', $file->name());
        self::assertEquals('web/index2.html', $file->path());
    }

    /** @test */
    function file_can_be_copied()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $requests = [
                $this->listContainersRequest(),
                $this->listSingleFileRequest('container1', 'web/index.html', 'text/html'),
                $this->copyFileRequest('/container1/web/index.html', '/container1/web/index2.html'),
                $this->copyFileRequest('/container1/web/index.html', '/container2/web/index2.html'),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $file = $container->files()->find('/web/index.html');

        self::assertEquals('index.html', $file->name());

        $copyWithinContainerResult = $file->copy('web/index2.html');
        $copyToAnotherContainerResult = $file->copy('web/index2.html', 'container2');

        self::assertEquals('/container1/web/index2.html', $copyWithinContainerResult);
        self::assertEquals('/container2/web/index2.html', $copyToAnotherContainerResult);
    }

    /** @test */
    function file_can_be_deleted()
    {
        $api = TestHelpers::mockApi(function ($api) {
            $requests = [
                $this->listContainersRequest(),
                $this->listSingleFileRequest('container1', 'web/index.html', 'text/html'),
            ];

            foreach ($requests as $request) {
                $api->shouldReceive('request')
                    ->with($request['method'], $request['url'], $request['params'])
                    ->andReturn($request['response']);
            }

            $deleteRequest = $this->deleteFileRequest('/container1/web/index.html');

            $api->shouldReceive('request')
                ->with($deleteRequest['method'], $deleteRequest['url'])
                ->andReturn($deleteRequest['response']);
        });

        $storage = new CloudStorage($api);
        $containers = $storage->containers();
        $container = $containers->get('container1');

        $file = $container->files()->find('/web/index.html');

        $this->assertFalse($file->isDeleted());

        $file->delete();

        $this->assertTrue($file->isDeleted());
    }

    public function readFileRequest($path)
    {
        return [
            'method' => 'GET',
            'url' => $path,
            'response' => TestHelpers::toResponse(file_get_contents(__DIR__.'/fixtures/test.html')),
        ];
    }

    public function copyFileRequest($source, $destination)
    {
        return [
            'method' => 'COPY',
            'url' => $source,
            'params' => [
                'headers' => [
                    'Destination' => $destination,
                ],
            ],
            'response' => TestHelpers::toResponse([], 201, [
                'X-Copied-From' => $source,
            ]),
        ];
    }

    public function deleteFileRequest($path)
    {
        return [
            'method' => 'DELETE',
            'url' => $path,
            'response' => TestHelpers::toResponse([], 204, [])
        ];
    }

    public function renameFileRequest($source, $destination)
    {
        return [
            'method' => 'PUT',
            'url' => $destination,
            'params' => [
                'headers' => [
                    'X-Copy-From' => $source,
                    'Content-Length' => 0,
                ],
            ],
            'response' => TestHelpers::toResponse([], 201, [
                'X-Copied-From' => $source,
            ]),
        ];
    }

    public function listContainersRequest()
    {
        return [
            'method' => 'GET',
            'url' => '/',
            'params' => [
                'query' => [
                    'limit' => 10000,
                    'marker' => '',
                ],
            ],
            'response' => TestHelpers::toResponse([
                [
                    'bytes' => 1024,
                    'count' => 2,
                    'name' => 'container1',
                    'rx_bytes' => 2048,
                    'tx_bytes' => 1024,
                    'type' => 'public',
                ],
                [
                    'bytes' => 1024,
                    'count' => 2,
                    'name' => 'container2',
                    'rx_bytes' => 2048,
                    'tx_bytes' => 1024,
                    'type' => 'public',
                ],
                [
                    'bytes' => 1024,
                    'count' => 2,
                    'name' => 'container3',
                    'rx_bytes' => 2048,
                    'tx_bytes' => 1024,
                    'type' => 'public',
                ],
            ]),
        ];
    }

    public function listSingleFileRequest($container, $path, $contentType)
    {
        return [
            'method' => 'GET',
            'url' => '/'.$container,
            'params' => [
                'query' => [
                    'limit' => 1,
                    'marker' => '',
                    'path' => '',
                    'prefix' => $path,
                    'delimiter' => '',
                ],
            ],
            'response' => TestHelpers::toResponse([
                [
                    'bytes' => 1024,
                    'content_type' => $contentType,
                    'hash' => md5('test'),
                    'last_modified' => '2013-05-27T15:31:25.325041',
                    'name' => $path,
                ],
            ]),
        ];
    }
}
