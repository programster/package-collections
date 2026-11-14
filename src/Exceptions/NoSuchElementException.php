<?php

/*
 * Thrown when an element is asked for, such as the first, but the collection is empty.
 */

namespace Programster\Collections\Exceptions;

class NoSuchElementException extends \RuntimeException implements CollectionException
{
}
