<?php

/*
 * Thrown when the elements are sorted or read by a property or method they don't have.
 */

namespace Programster\Collections\Exceptions;

class InvalidPropertyOrMethod extends \RuntimeException implements CollectionException
{
}
