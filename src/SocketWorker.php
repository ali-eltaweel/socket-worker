<?php

namespace SocketWorker;

use Codecs\{ ICodec, SerialCodec };

use Files\{ EncodedFile, Handles\SocketHandle, Socket };

use Lang\{ Annotations\Computes, ComputedProperties };

use Closure;

/**
 * Socket Worker.
 * 
 * @api
 * @since 1.0.0
 * @version 1.0.0
 * @package socket-worker
 * @author Ali M. Kamel <ali.kamel.dev@gmail.com>
 * 
 * @property-read SocketWorkerStatus $status
 */
class SocketWorker {

    use ComputedProperties;

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
     * Retrieves the current status of the socket worker.
     * 
     * @api
     * @final
     * @since 1.0.0
     * @version 1.0.0
     * 
     * @return SocketWorkerStatus|null
     */
    #[Computes('status')]
    public final function getStatus(): ?SocketWorkerStatus {

        return $this->statusFile->path->exists() ? $this->statusFile->data : null;
    }

    /**
     * Accepts a new connection to the socket.
     * 
     * @api
     * @final
     * @since 1.0.0
     * @version 1.0.0
     * 
     * @return void
     */
    public final function accept(): void {

        $this->setStatus(SocketWorkerStatus::Waiting);
        
        $dispatcher = $this->socketHandle->accept();
        
        $this->setStatus(SocketWorkerStatus::Busy);

        $response = ($this->commandHandler)(
            $command = $this->commandsCodec->decode(
                $dispatcher->read()
            )
        );
        
        $dispatcher->write(
            $this->commandsCodec->encode($response)
        );

        $dispatcher->close();

        if (!is_null($commandShutdown = $this->commandShutdown)) {

            $commandShutdown($command, $response, $this->shutdown(...));
        }

        $this->setStatus(SocketWorkerStatus::Ready);
    }

    /**
     * Sets the current status of the socket worker.
     * 
     * @internal
     * @since 1.0.0
     * @version 1.0.0
     * 
     * @param SocketWorkerStatus $status
     * @return void
     */
    private function setStatus(SocketWorkerStatus $status): void {

        if ($this->statusFile->path->exists()) {

            $this->statusFile->data = $status;
        }
    }

    /**
     * Closes the socket and removes the files.
     * 
     * @internal
     * @since 1.0.0
     * @version 1.0.0
     * 
     * @return void
     */
    private function shutdown(): void {

        $this->statusFile->remove();

        $this->socketHandle->close();

        if (!$this->reuseSocketFile) {

            $this->socket->remove();
        }
    }
}
