<?php

namespace SocketWorker;

/**
 * Socket Worker Status.
 * 
 * @api
 * @since 1.0.0
 * @version 1.0.0
 * @package socket-worker
 * @author Ali M. Kamel <ali.kamel.dev@gmail.com>
 */
enum SocketWorkerStatus {

    /**
     * @since 1.0.0
     */
    case Starting;
    
    /**
     * @since 1.0.0
     */
    case Ready;

    /**
     * @since 1.0.0
     */
    case Waiting;
    
    /**
     * @since 1.0.0
     */
    case Busy;
}
