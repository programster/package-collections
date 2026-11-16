<?php

/*
 * A PHPStan rule that reports writing to a readonly collection or map (see AbstractReadonlyCollection and
 * AbstractReadonlyMap) with array syntax, where it's written, rather than when it throws as the code runs. PHPStan
 * loads it through the package's extension.neon (automatically with phpstan/extension-installer).
 */

declare(strict_types = 1);

namespace Programster\Collections\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Unset_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\TypeCombinator;
use Programster\Collections\AbstractReadonlyCollection;
use Programster\Collections\AbstractReadonlyMap;

/**
 * A readonly collection or map can be read with array syntax ($users[0], isset() and ??), as it implements
 * ArrayAccess, but PHP can't stop that same syntax being used to write to it, which can only throw when it runs. This
 * reports each way of writing to one: assigning ($users[0] = $x, $users[] = $x, or by reference), changing an element
 * in place ($users[0] .= $x, $users[0] ??= $x, $users[0]++), assigning to one in a list ([$users[0]] = $xs) or a
 * foreach (foreach ($xs as $users[0])), and unset($users[0]).
 *
 * @implements Rule<Node>
 */
final class ReadonlyWriteRule implements Rule
{
    private ReflectionProvider $m_reflectionProvider;


    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->m_reflectionProvider = $reflectionProvider;
    }


    public function getNodeType(): string
    {
        return Node::class;
    }


    /**
     * @param Node $node - any node, as writes are made by several kinds of expression and statement.
     *
     * @param Scope $scope - where it is.
     *
     * @return list<IdentifierRuleError> - an error for each readonly collection or map the node writes to, or none.
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->getWrittenTargets($node) as $target)
        {
            foreach ($this->findArrayWrites($target) as $write)
            {
                $errors = [...$errors, ...$this->checkWrite($write, $node instanceof Unset_, $scope)];
            }
        }

        return $errors;
    }


    /**
     * Get the expressions a node writes to, if it's one that writes.
     * @param Node $node - the node.
     * @return list<Expr> - what it writes to, or none if it doesn't write.
     */
    private function getWrittenTargets(Node $node): array
    {
        $isAssignment = (
               $node instanceof Assign
            || $node instanceof AssignRef
            || $node instanceof AssignOp
            || $node instanceof PreInc
            || $node instanceof PreDec
            || $node instanceof PostInc
            || $node instanceof PostDec
        );

        if ($isAssignment)
        {
            $targets = [$node->var];
        }
        elseif ($node instanceof Unset_)
        {
            $targets = array_values($node->vars);
        }
        elseif ($node instanceof Foreach_)
        {
            $targets = ($node->keyVar === null) ? [$node->valueVar] : [$node->keyVar, $node->valueVar];
        }
        else
        {
            $targets = [];
        }

        return $targets;
    }


    /**
     * Find the array writes in what an expression writes to, which is either one, or a list that's destructured into
     * several (which can be nested).
     *
     * @param Expr $target - what's written to.
     *
     * @return list<ArrayDimFetch> - the array writes, or none if it isn't written to with array syntax.
     */
    private function findArrayWrites(Expr $target): array
    {
        $writes = [];

        if ($target instanceof ArrayDimFetch)
        {
            $writes[] = $target;
        }
        elseif ($target instanceof List_ || $target instanceof Array_)
        {
            foreach ($target->items as $item)
            {
                if ($item !== null)
                {
                    $writes = [...$writes, ...$this->findArrayWrites($item->value)];
                }
            }
        }

        return $writes;
    }


    /**
     * Check whether an array write is to a readonly collection or map.
     *
     * @param ArrayDimFetch $write - the array write, e.g. $users[0].
     *
     * @param bool $isUnset - whether it's in an unset(), for the message.
     *
     * @param Scope $scope - where it is.
     *
     * @return list<IdentifierRuleError> - an error if it's to a readonly collection or map, otherwise none.
     */
    private function checkWrite(ArrayDimFetch $write, bool $isUnset, Scope $scope): array
    {
        $errors = [];

        // PHPStan only gives a union's classes if all of it is objects, so a nullable one (e.g. UserCollection|null)
        // has null taken out first. A union is reported once, for the first readonly class in it.
        $type = TypeCombinator::removeNull($scope->getType($write->var));

        foreach ($type->getObjectClassNames() as $className)
        {
            $kind = $this->getReadonlyKind($className);

            if (count($errors) === 0 && $kind !== ReadonlyKind::NEITHER)
            {
                $shortName = substr((string)strrchr('\\' . $className, '\\'), 1);
                $alternative = $kind->describeAlternative($isUnset);
                $action = $isUnset ? "unset an element of" : "write to";

                $message = "Can't {$action} {$shortName} with array syntax, as it's a readonly {$kind->describe()}, "
                    . "so it would throw an UnsupportedOperationException. {$alternative}";

                $errors[] = RuleErrorBuilder::message($message)->identifier('collections.readonlyWrite')->build();
            }
        }

        return $errors;
    }


    /**
     * Whether a class is a readonly collection or map.
     * @param string $className - the class.
     * @return ReadonlyKind
     */
    private function getReadonlyKind(string $className): ReadonlyKind
    {
        $kind = ReadonlyKind::NEITHER;

        if ($this->m_reflectionProvider->hasClass($className))
        {
            $class = $this->m_reflectionProvider->getClass($className);

            $isCollection = (
                   $class->getName() === AbstractReadonlyCollection::class
                || $class->isSubclassOf(AbstractReadonlyCollection::class)
            );

            $isMap = (
                   $class->getName() === AbstractReadonlyMap::class
                || $class->isSubclassOf(AbstractReadonlyMap::class)
            );

            if ($isCollection)
            {
                $kind = ReadonlyKind::COLLECTION;
            }
            elseif ($isMap)
            {
                $kind = ReadonlyKind::MAP;
            }
        }

        return $kind;
    }
}
