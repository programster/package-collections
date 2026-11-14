<?php

/*
 * Thrown when an element is asked for by an index the collection doesn't have.
 */

namespace Programster\Collections\Exceptions;

class OutOfBoundsException extends \OutOfBoundsException implements CollectionException
{
}
