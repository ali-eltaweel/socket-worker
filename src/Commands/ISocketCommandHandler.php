<?php

namespace SocketWorker\Commands;

/**
 * Socket Command Handler Signature.
 * 
 * @api
 * @abstract
 * @since 1.0.0
 * @version 1.0.0
 * @package socket-worker
 * @author Ali M. Kamel <ali.kamel.dev@gmail.com>
 */
interface ISocketCommandHandler {

    /**
     * Executes the specified command.
     * 
     * @api
     * @abstract
     * @since 1.0.0
     * @version 1.0.0
     * 
     * @param SocketCommand $command
     * @return SocketResponse
     */
    public function __invoke(SocketCommand $command): SocketResponse;
}
