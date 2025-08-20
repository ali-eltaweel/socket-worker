<?php

namespace SocketWorker\Commands;

use DTO\DataTransferObject;

/**
 * Socket Response.
 * 
 * @api
 * @since 1.0.0
 * @version 1.1.0
 * @package socket-worker
 * @author Ali M. Kamel <ali.kamel.dev@gmail.com>
 * 
 * @property-read bool        $status The response status.
 * @property-read mixed[]     $data   The response data.
 * @property-read string|null $id     The command ID.
 */
class SocketResponse extends DataTransferObject {

    /**
     * Creates a new socket response.
     * 
     * @api
     * @override
     * @since 1.0.0
     * @version 1.1.0
     * 
     * @param bool        $status The response status.
     * @param mixed[]     $data   The response data.
     * @param string|null $id     The command ID.
     */
    public function __construct(bool $status, array $data = [], ?string $id = null) {

        parent::__construct(func_get_args());
    }
}
