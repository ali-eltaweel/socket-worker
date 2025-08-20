<?php

namespace SocketWorker\Commands;

use DTO\DataTransferObject;

/**
 * Socket Command.
 * 
 * @api
 * @since 1.0.0
 * @version 1.1.0
 * @package socket-worker
 * @author Ali M. Kamel <ali.kamel.dev@gmail.com>
 * 
 * @property-read string      $name      The command name.
 * @property-read mixed[]     $arguments The command arguments.
 * @property-read string|null $id        The command ID.
 */
class SocketCommand extends DataTransferObject {

    /**
     * Creates a new socket command.
     * 
     * @api
     * @override
     * @since 1.0.0
     * @version 1.1.0
     * 
     * @param string      $name      The command name.
     * @param mixed[]     $arguments The command arguments.
     * @param string|null $id        The command ID.
     */
    public function __construct(string $name, array $arguments = [], ?string $id = null) {

        parent::__construct(func_get_args());
    }
}
