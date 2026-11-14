<?php

/*
 * Thrown when an operation can't be done with the collection's type of element, such as reading a property of a string.
 */

namespace Programster\Collections\Exceptions;

class UnsupportedOperationException extends \RuntimeException implements CollectionException
{
}
