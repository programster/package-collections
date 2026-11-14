<?php

/*
 * Thrown when two collections are combined or compared, but they hold different types of element.
 */

namespace Programster\Collections\Exceptions;

class CollectionMismatchException extends \RuntimeException implements CollectionException
{
}
