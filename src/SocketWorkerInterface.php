<?php

namespace SocketWorker;

use Codecs\{ ICodec, SerialCodec };

use Files\{ EncodedFile, Socket };

use Lang\{ Annotations\Computes, ComputedProperties };

/**
 * Socket Worker Interface.
 * 
 * @api
 * @since 1.0.0
 * @version 1.0.0
 * @package socket-worker
 * @author Ali M. Kamel <ali.kamel.dev@gmail.com>
 * 
 * @property-read SocketWorkerStatus $status
 */
class SocketWorkerInterface {

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
     * The worker's status file.
     * 
     * @internal
     * @since 1.0.0
     * 
     * @var EncodedFile $statusFile
     */
    private EncodedFile $statusFile;

    /**
     * Creates a new socket worker interface.
     * 
     * @api
     * @since 1.0.0
     * @version 1.0.0
     * 
     * @param string $socketPath     The path to the socket file.
     * @param string $statusFilePath The path to the status file.
     * @param ICodec $commandsCodec  The codec used to encode/decode commands/responses.
     * @param int    $socketDomain   The protocol family to be used by the socket. 
     * @param int    $socketType     The type of communication to be used by the socket. 
     * @param int    $socketProtocol The specific protocol within the specified domain to be used when communicating on the socket.
     */
    public function __construct(
        
        string $socketPath,
        string $statusFilePath,
        private readonly ICodec $commandsCodec,
        private readonly int    $socketDomain    = AF_UNIX,
        private readonly int    $socketType      = SOCK_STREAM,
        private readonly int    $socketProtocol  = 0
    ) {

        $this->statusFile = new EncodedFile($statusFilePath, new SerialCodec([SocketWorkerStatus::class], 1));
        $this->socket     = new Socket($socketPath);
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
     * Executes the specified command.
     * 
     * @api
     * @final
     * @since 1.0.0
     * @version 1.0.0
     * 
     * @param Commands\SocketCommand $command The command to be executed.
     * @param bool $blocking                  Whether to block calls to this function if the worker is not in a waiting state.
     * @return Commands\SocketResponse|null   The worker's response, or null if the worker is not in a waiting state and blocking is disabled.
     */
    public final function execute(Commands\SocketCommand $command, bool $blocking = true): ?Commands\SocketResponse {

        if ($this->status !== SocketWorkerStatus::Waiting && !$blocking) {

            return null;
        }

        $handle = $this->socket->open($this->socketDomain, $this->socketType, $this->socketProtocol);

        $handle->connect();

        $handle->write($this->commandsCodec->encode($command));
        
        $response = $this->commandsCodec->decode($handle->read());

        $handle->close();

        return $response;
    }
}
