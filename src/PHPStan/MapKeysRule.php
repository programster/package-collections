<?php

/*
 * A PHPStan rule that reports a map (see AbstractReadonlyMap and AbstractMap) created with elements that won't have
 * string keys, where it's written, rather than when the map's constructor rejects them as the code runs. PHPStan loads
 * it through the package's extension.neon (automatically with phpstan/extension-installer).
 */

declare(strict_types = 1);

namespace Programster\Collections\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\StringType;
use PHPStan\Type\VerbosityLevel;
use Programster\Collections\AbstractMap;
use Programster\Collections\AbstractReadonlyMap;

/**
 * A map's constructor takes its elements as a typed variadic, and an element only keeps its key when it's passed by
 * name: by spreading an array with string keys (new UserMap(...['ann' => $ann])), or as a named argument
 * (new UserMap(ann: $ann)). PHP can't require that, so this reports the two ways to get it wrong: passing an element
 * by position, and spreading an array whose keys might not all be strings (e.g. a list, or a key like "123", which PHP
 * turns into a number).
 *
 * @implements Rule<New_>
 */
final class MapKeysRule implements Rule
{
    private ReflectionProvider $m_reflectionProvider;


    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->m_reflectionProvider = $reflectionProvider;
    }


    public function getNodeType(): string
    {
        return New_::class;
    }


    /**
     * @param New_ $node - the "new" expression.
     *
     * @param Scope $scope - where it is.
     *
     * @return list<IdentifierRuleError> - an error for each argument that won't give its element a string key, or
     * none if the class isn't a map.
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        // An anonymous class, or a class name worked out as the code runs, can't be checked.
        if ($node->class instanceof Name)
        {
            $className = $scope->resolveName($node->class);

            if ($this->isMap($className))
            {
                foreach ($node->getArgs() as $index => $arg)
                {
                    $errors = [...$errors, ...$this->checkArgument($arg, $index + 1, $className, $scope)];
                }
            }
        }

        return $errors;
    }


    /**
     * Whether a class is one of the maps, or extends one.
     */
    private function isMap(string $className): bool
    {
        $isMap = false;

        if ($this->m_reflectionProvider->hasClass($className))
        {
            $class = $this->m_reflectionProvider->getClass($className);

            $isMap = (
                   $class->getName() === AbstractReadonlyMap::class
                || $class->getName() === AbstractMap::class
                || $class->isSubclassOf(AbstractReadonlyMap::class)
                || $class->isSubclassOf(AbstractMap::class)
            );
        }

        return $isMap;
    }


    /**
     * Check one argument to a map's constructor gives its element (or elements) string keys.
     *
     * @param Arg $arg - the argument.
     *
     * @param int $position - its position, from 1, for the message.
     *
     * @param string $className - the map's class, for the message.
     *
     * @param Scope $scope - where it is.
     *
     * @return list<IdentifierRuleError> - an error if it doesn't, otherwise none.
     */
    private function checkArgument(Arg $arg, int $position, string $className, Scope $scope): array
    {
        $errors = [];
        $shortName = substr((string)strrchr('\\' . $className, '\\'), 1);
        $example = "e.g. new {$shortName}(...['name' => \$element]) or new {$shortName}(name: \$element)";

        if ($arg->unpack)
        {
            $keyType = $scope->getType($arg->value)->getIterableKeyType();

            if (!(new StringType())->isSuperTypeOf($keyType)->yes())
            {
                $keys = $keyType->describe(VerbosityLevel::typeOnly());

                $message = "{$shortName}'s elements need string keys, but argument #{$position} spreads an array whose "
                    . "keys can be {$keys}. Spread an array with string keys, {$example}. Keys that are whole numbers "
                    . "(e.g. \"123\") can't be used, as PHP turns them into positions.";

                $errors[] = RuleErrorBuilder::message($message)->identifier('collections.mapKeys')->build();
            }
        }
        elseif ($arg->name === null)
        {
            $message = "{$shortName}'s elements need keys, but argument #{$position} is passed by position, so it "
                . "would have none. Pass the elements by name, {$example}.";

            $errors[] = RuleErrorBuilder::message($message)->identifier('collections.mapKeys')->build();
        }

        return $errors;
    }
}
