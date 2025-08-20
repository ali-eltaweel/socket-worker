<?php

namespace SocketWorker\Commands;

/**
 * Socket Command Shutdown Handler Signature.
 * 
 * @api
 * @abstract
 * @since 1.0.0
 * @version 1.0.0
 * @package socket-worker
 * @author Ali M. Kamel <ali.kamel.dev@gmail.com>
 */
interface ISocketCommandShutdown {

    /**
     * Performs cleanup for the specified command after the connection is closed.
     * 
     * @api
     * @abstract
     * @since 1.0.0
     * @version 1.0.0
     * 
     * @param SocketCommand $command
     * @param SocketResponse $response
     * @param callable(): void $workerShutdown
     * @return void
     */
    public function __invoke(SocketCommand $command, SocketResponse $response, callable $workerShutdown): void;
}
