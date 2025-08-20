# Socket Worker

**Socket Worker**

## Installation

```bash
composer require ali-eltaweel/socket-worker
```

## Basic Usage

### Worker

```php
$worker = new SocketWorker\SocketWorker(
    socketPath:     'path/to/socket/file',
    statusFilePath: 'path/to/socket/status/file',
    commandsCodec:   new class implements Codecs\ICodec { /**/ },
    commandHandler:  function(SocketWorker\Commands\SocketCommand $command): SocketWorker\Commands\SocketResponse {

        // ...

        return new SocketWorker\Commands\SocketResponse(status: true, data: []);
    }
);

while ($worker->status === SocketWorker\SocketWorkerStatus::Ready) {
    
    $worker->accept();
}
```

### Dispatcher

```php
$worker = new SocketWorker\SocketWorkerInterface(
    socketPath:     'path/to/socket/file',
    statusFilePath: 'path/to/socket/status/file',
    commandsCodec:   new class implements Codecs\ICodec { /**/ }
);

/**
 * @var SocketWorker\Commands\SocketResponse $response
 */
$response = $worker->execute(new SocketWorker\Commands\SocketCommand('cmd', arguments: []));
```
