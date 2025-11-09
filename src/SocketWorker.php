<?php

namespace SocketWorker;

use Codecs\{ ICodec, SerialCodec };

use Files\{ EncodedFile, Handles\SocketHandle, Socket };

use Lang\{ Annotations\Computes, ComputedProperties };

use Logger\{ EmitsLogs, IHasLogger, Logger };

use Closure;

/**
 * Socket Worker.
 * 
 * @api
 * @since 1.0.0
 * @version 1.1.0
 * @package socket-worker
 * @author Ali M. Kamel <ali.kamel.dev@gmail.com>
 * 
 * @property-read SocketWorkerStatus $status
 */
class SocketWorker implements IHasLogger {

    use ComputedProperties;
    use EmitsLogs;

    /**
     * The logger instance.
     * 
     * @internal
     * @since 1.1.0
     * 
     * @var Logger|null $logger
     */
    protected ?Logger $logger = null;

    /**
     * The socket instance.
     * 
     * @internal
     * @since 1.0.0
     * 
     * @var Socket $socket
     */
    private Socket $socket;
    
    /**
     * The opened socket handle.
     * 
     * @internal
     * @since 1.0.0
     * 
     * @var SocketHandle $socketHandle
     */
    private SocketHandle $socketHandle;

    /**
     * The worker's status file.
     * 
     * @internal
     * @since 1.0.0
     * 
     * @var EncodedFile $statusFile
     */
    private EncodedFile $statusFile;

    /**
     * Creates a new socket worker instance.
     * 
     * @api
     * @since 1.0.0
     * @version 1.0.0
     * 
     * @param string $socketPath                                                The path to the socket file.
     * @param string $statusFilePath                                            The path to the status file.
     * @param ICodec $commandsCodec                                             The codec used to encode/decode commands/responses.
     * @param Commands\ISocketCommandHandler|Closure $commandHandler            The command handler callback.
     * @param Commands\ISocketCommandShutdown|Closure|null $commandShutdown     The command shutdown handler.
     * @param bool $reuseSocketFile                                             Whether to reuse the socket file.
     * @param int $socketDomain                                                 The protocol family to be used by the socket. 
     * @param int $socketType                                                   The type of communication to be used by the socket. 
     * @param int $socketProtocol                                               The specific protocol within the specified domain to be used when communicating on the socket.
     */
    public function __construct(
        
                         string                                       $socketPath,
                         string                                       $statusFilePath,
        private readonly ICodec                                       $commandsCodec,
        private readonly Commands\ISocketCommandHandler|Closure       $commandHandler,
        private readonly Commands\ISocketCommandShutdown|Closure|null $commandShutdown = null,
        private readonly bool                                         $reuseSocketFile = false,
                         int                                          $socketDomain    = AF_UNIX,
                         int                                          $socketType      = SOCK_STREAM,
                         int                                          $socketProtocol  = 0
    ) {

        $this->statusFile       = new EncodedFile($statusFilePath, new SerialCodec([SocketWorkerStatus::class], 1));
        $this->statusFile->data = SocketWorkerStatus::Starting;

        $this->socket       = new Socket($socketPath);
        $this->socketHandle = $this->socket->open($socketDomain, $socketType, $socketProtocol);

        if (!$reuseSocketFile && $this->socket->path->exists()) {

            $this->socket->remove();
            $this->socketHandle->bind();
        }

        if (!$this->socket->path->exists()) {
            
            $this->socketHandle->bind();
        }

        $this->socketHandle->listen();

        $this->statusFile->data = SocketWorkerStatus::Ready;
    }

    /**
     * Sets the logger instance.
     * 
     * @api
     * @since 1.1.0
     * @version 1.0.0
     * 
     * @param Logger|null $logger
     * @return void
     */
    public function setLogger(?Logger $logger): void {

        $this->logger = $logger;
        $this->socket->setLogger($logger);
        $this->socketHandle->setLogger($logger);
        $this->statusFile->setLogger($logger);
    }

    /**
     * Retrieves the current status of the socket worker.
     * 
     * @api
     * @final
     * @since 1.0.0
     * @version 1.1.0
     * 
     * @return SocketWorkerStatus|null
     */
    #[Computes('status')]
    public final function getStatus(): ?SocketWorkerStatus {

        $logUnit = static::class . '::' . __FUNCTION__;

        $path = $this->statusFile->path;

        $this->infoLog(fn () => [
            'Getting status' => [ 'statusFilePath' => $path ]
        ], $logUnit);

        if (!$path->exists()) {
            
            $this->warningLog(fn () => [
                'Status file not found' => [ 'statusFilePath' => $path ]
            ], $logUnit);

            return null;
        }

        $status = $this->statusFile->data;

        $this->debugLog(fn () => [
            'Getting status' => [ 'statusFilePath' => $path, 'status' => $status->name ]
        ], $logUnit);

        return $status;
    }

    /**
     * Accepts a new connection to the socket.
     * 
     * @api
     * @final
     * @since 1.0.0
     * @version 1.1.0
     * 
     * @return void
     */
    public final function accept(): void {

        $logUnit = static::class . '::' . __FUNCTION__;

        $this->setStatus(SocketWorkerStatus::Waiting);
        
        $this->infoLog(fn () => [
            'Accepting new connection' => [ 'socket' => [ 'type' => $this->socketHandle::class, 'id' => spl_object_id($this->socketHandle) ] ]
        ], $logUnit);

        $dispatcher = $this->socketHandle->accept();
        $dispatcher->setLogger($this->logger);

        $this->debugLog(fn () => [
            'Connection accepted' => [ 'dispatcher' => [ 'type' => $dispatcher::class, 'id' => spl_object_id($dispatcher) ] ]
        ], $logUnit);
        
        $this->setStatus(SocketWorkerStatus::Busy);

        $this->infoLog(fn () => [
            'Reading command' => [ 'dispatcher' => [ 'type' => $dispatcher::class, 'id' => spl_object_id($dispatcher) ] ]
        ], $logUnit);

        $command = $this->commandsCodec->decode($dispatcher->read());

        $this->infoLog(fn () => [
            'Executing command' => [ 'command' => $command->toArray() ]
        ], $logUnit);

        $response = ($this->commandHandler)($command);

        $this->infoLog(fn () => [
            'Command executed' => [ 'command' => $command->toArray() ]
        ], $logUnit);

        $this->debugLog(fn () => [
            'Command executed' => [ 'command' => $command->toArray(), 'response' => $response->toArray() ]
        ], $logUnit);

        $this->infoLog(fn () => [
            'Writing response' => [ 'dispatcher' => [ 'type' => $dispatcher::class, 'id' => spl_object_id($dispatcher) ] ]
        ], $logUnit);

        $dispatcher->write($this->commandsCodec->encode($response));

        $dispatcher->close();

        if (!is_null($commandShutdown = $this->commandShutdown)) {

            $this->infoLog(fn () => [
                'Terminating command' => [ 'command' => $command->toArray(), 'response' => $response->toArray() ]
            ], $logUnit);

            $commandShutdown($command, $response, $this->shutdown(...));
        }

        $this->setStatus(SocketWorkerStatus::Ready);
    }

    /**
     * Sets the current status of the socket worker.
     * 
     * @internal
     * @since 1.0.0
     * @version 1.1.0
     * 
     * @param SocketWorkerStatus $status
     * @return void
     */
    private function setStatus(SocketWorkerStatus $status): void {

        $logUnit = static::class . '::' . __FUNCTION__;

        $this->infoLog(fn () => [
            'Setting status' => [ 'status' => $status->name ]
        ], $logUnit);

        if ($this->statusFile->path->exists()) {

            $this->statusFile->data = $status;

            $this->debugLog(fn () => [
                'Status set' => [ 'status' => $status->name ]
            ], $logUnit);
        }

        $this->warningLog(fn () => [ 'Status file is not found' ], $logUnit);
    }

    /**
     * Closes the socket and removes the files.
     * 
     * @internal
     * @since 1.0.0
     * @version 1.1.0
     * 
     * @return void
     */
    private function shutdown(): void {

        $logUnit = static::class . '::' . __FUNCTION__;

        $this->infoLog(fn () => [ 'Shutting down' ], $logUnit);

        $this->statusFile->remove();

        $this->socketHandle->close();

        if (!$this->reuseSocketFile) {

            $this->socket->remove();
        }

        $this->infoLog(fn () => [ 'Wroker terminated' ], $logUnit);
    }
}
