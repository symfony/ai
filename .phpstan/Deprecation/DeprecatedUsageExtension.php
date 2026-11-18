<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PHPStan\Deprecation;

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassConstantReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ExtendedPropertyReflection;
use PHPStan\Rules\ClassNameUsageLocation;
use PHPStan\Rules\RestrictedUsage\RestrictedClassConstantUsageExtension;
use PHPStan\Rules\RestrictedUsage\RestrictedClassNameUsageExtension;
use PHPStan\Rules\RestrictedUsage\RestrictedMethodUsageExtension;
use PHPStan\Rules\RestrictedUsage\RestrictedPropertyUsageExtension;
use PHPStan\Rules\RestrictedUsage\RestrictedUsage;

/**
 * Reports code of the monorepo that uses deprecated code of the monorepo.
 *
 * The deprecation would reach users through code they cannot change. Deprecated code itself, the file
 * declaring it and legacy tests (#[IgnoreDeprecations], #[Group('legacy')]) may use it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DeprecatedUsageExtension implements RestrictedMethodUsageExtension, RestrictedClassNameUsageExtension, RestrictedPropertyUsageExtension, RestrictedClassConstantUsageExtension
{
    private const IDENTIFIER = 'symfonyAi.deprecation.usage';

    public function isRestrictedMethodUsage(ExtendedMethodReflection $methodReflection, Scope $scope): ?RestrictedUsage
    {
        $class = $methodReflection->getDeclaringClass();
        if (!$methodReflection->isDeprecated()->yes() || !self::isOwn($class) || self::isAllowed($scope, $class)) {
            return null;
        }

        return RestrictedUsage::create(
            \sprintf('Call to deprecated method %s::%s()%s', $class->getName(), $methodReflection->getName(), self::reason($methodReflection->getDeprecatedDescription())),
            self::IDENTIFIER,
        );
    }

    public function isRestrictedClassNameUsage(ClassReflection $classReflection, Scope $scope, ClassNameUsageLocation $location): ?RestrictedUsage
    {
        if (!$classReflection->isDeprecated() || !self::isOwn($classReflection) || self::isAllowed($scope, $classReflection)) {
            return null;
        }

        return RestrictedUsage::create(
            \sprintf('Usage of deprecated %s %s%s', strtolower($classReflection->getClassTypeDescription()), $classReflection->getName(), self::reason($classReflection->getDeprecatedDescription())),
            self::IDENTIFIER,
        );
    }

    public function isRestrictedPropertyUsage(ExtendedPropertyReflection $propertyReflection, Scope $scope): ?RestrictedUsage
    {
        $class = $propertyReflection->getDeclaringClass();
        if (!$propertyReflection->isDeprecated()->yes() || !self::isOwn($class) || self::isAllowed($scope, $class)) {
            return null;
        }

        return RestrictedUsage::create(
            \sprintf('Access to deprecated property %s::$%s%s', $class->getName(), $propertyReflection->getName(), self::reason($propertyReflection->getDeprecatedDescription())),
            self::IDENTIFIER,
        );
    }

    public function isRestrictedClassConstantUsage(ClassConstantReflection $constantReflection, Scope $scope): ?RestrictedUsage
    {
        $class = $constantReflection->getDeclaringClass();
        if (!$constantReflection->isDeprecated()->yes() || !self::isOwn($class) || self::isAllowed($scope, $class)) {
            return null;
        }

        return RestrictedUsage::create(
            \sprintf('Access to deprecated constant %s::%s%s', $class->getName(), $constantReflection->getName(), self::reason($constantReflection->getDeprecatedDescription())),
            self::IDENTIFIER,
        );
    }

    private static function isOwn(ClassReflection $class): bool
    {
        return str_starts_with($class->getName(), 'Symfony\\AI\\');
    }

    /**
     * The file declaring deprecated code holds its BC layer, which keeps using it.
     */
    private static function isAllowed(Scope $scope, ClassReflection $declaringClass): bool
    {
        if ($declaringClass->getFileName() === $scope->getFile()) {
            return true;
        }

        if ($scope->getClassReflection()?->isDeprecated() || $scope->getTraitReflection()?->isDeprecated()) {
            return true;
        }

        $function = $scope->getFunction();
        if (null !== $function && $function->isDeprecated()->yes()) {
            return true;
        }

        if (!PackageResolver::isTestFile($scope->getFile())) {
            return false;
        }

        $attributes = [...($function?->getAttributes() ?? []), ...($scope->getClassReflection()?->getAttributes() ?? [])];
        foreach ($attributes as $attribute) {
            if ('PHPUnit\Framework\Attributes\IgnoreDeprecations' === $attribute->getName()) {
                return true;
            }
            if ('PHPUnit\Framework\Attributes\Group' === $attribute->getName()) {
                foreach ($attribute->getArgumentTypes() as $type) {
                    if (\in_array('legacy', $type->getConstantScalarValues(), true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function reason(?string $description): string
    {
        return null !== $description && '' !== trim($description) ? ': '.trim($description) : '.';
    }
}
