<?php

namespace InovCom\Kernel\Exceptions;

use RuntimeException;

/**
 * Thrown when a state-machine transition is not allowed from the current status.
 */
class InvalidWorkflowTransitionException extends RuntimeException {}
