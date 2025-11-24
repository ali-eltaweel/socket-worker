<?php

namespace SocketWorker;

use Codecs\{ ICodec, SerialCodec };

use Files\{ EncodedFile, Socket };

use Lang\{ Annotations\Computes, ComputedProperties };

use Logger\{ EmitsLogs, IHasLogger, Logger };

/**
 * Socket Worker Interface.
 * 
 * @api
 * @since 1.0.0
 * @version 1.1.1
 * @package socket-worker
 * @author Ali M. Kamel <ali.kamel.dev@gmail.com>
 * 
 * @property-read SocketWorkerStatus $status
 */
class SocketWorkerInterface implements IHasLogger {

    use ComputedProperties;
    use EmitsLogs;

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
     * The logger instance.
     * 
     * @internal
     * @since 1.1.0
     * 
     * @var Logger|null $logger
     */
    protected ?Logger $logger = null;

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
        $this->statusFile->setLogger($logger);
    }

    /**
     * Retrieves the current status of the socket worker.
     * 
     * @api
     * @final
     * @since 1.0.0
     * @version 1.1.1
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
            'Getting status' => [ 'statusFilePath' => $path, 'status' => $status?->name ]
        ], $logUnit);

        return $status;
    }

    /**
     * Executes the specified command.
     * 
     * @api
     * @final
     * @since 1.0.0
     * @version 1.1.0
     * 
     * @param Commands\SocketCommand $command The command to be executed.
     * @param bool $blocking                  Whether to block calls to this function if the worker is not in a waiting state.
     * @return Commands\SocketResponse|null   The worker's response, or null if the worker is not in a waiting state and blocking is disabled.
     */
    public final function execute(Commands\SocketCommand $command, bool $blocking = true): ?Commands\SocketResponse {

        $logUnit = static::class . '::' . __FUNCTION__;

        $this->infoLog(fn () => [
            'Executing command' => [ 'command' => $command->toArray(), 'blocking' => $blocking ]
        ], $logUnit);

        if ($this->status !== SocketWorkerStatus::Waiting && !$blocking) {

            $this->warningLog(fn () => [
                'Worker busy, skipping' => [ 'command' => $command->toArray(), 'blocking' => $blocking ]
            ], $logUnit);

            return null;
        }

        $this->infoLog(fn () => [
            'Connecting to worker' => [ 'command' => $command->toArray(), 'blocking' => $blocking ]
        ], $logUnit);

        $handle = $this->socket->open($this->socketDomain, $this->socketType, $this->socketProtocol);
        $handle->setLogger($this->logger);

        if (!@$handle->connect()) {

            $this->errorLog(fn () => [
                'Failed to connect to worker' => [ 'command' => $command->toArray(), 'blocking' => $blocking, 'worker' => [ 'type' => $handle::class, 'id' => spl_object_id($handle) ] ]
            ], $logUnit);

            return null;
        }

        $this->debugLog(fn () => [
            'Connected to worker' => [ 'command' => $command->toArray(), 'blocking' => $blocking, 'worker' => [ 'type' => $handle::class, 'id' => spl_object_id($handle) ] ]
        ], $logUnit);

        $this->infoLog(fn () => [
            'Writing command' => [ 'command' => $command->toArray(), 'blocking' => $blocking, 'worker' => [ 'type' => $handle::class, 'id' => spl_object_id($handle) ] ]
        ], $logUnit);

        $handle->write($this->commandsCodec->encode($command));

        $this->infoLog(fn () => [
            'Receiving response' => [ 'worker' => [ 'type' => $handle::class, 'id' => spl_object_id($handle) ] ]
        ], $logUnit);
        
        if (is_null($response = $handle->read())) {

            $this->warningLog(fn () => [
                'Empty response' => [ 'worker' => [ 'type' => $handle::class, 'id' => spl_object_id($handle) ] ]
            ], $logUnit);

            $handle->close();

            return null;
        }
        
        $response = $this->commandsCodec->decode($response);

        $handle->close();

        $this->debugLog(fn () => [
            'Command executed' => [ 'command' => $command->toArray(), 'blocking' => $blocking ]
        ], $logUnit);

        return $response;
    }
}
