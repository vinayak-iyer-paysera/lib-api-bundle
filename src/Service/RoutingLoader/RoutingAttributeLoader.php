<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Service\RoutingLoader;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\PhpParser;
use Doctrine\Common\Annotations\Reader;
use Paysera\Bundle\ApiBundle\Annotation\RestAnnotationInterface;
use Paysera\Bundle\ApiBundle\Attribute\RestAttributeInterface;
use Paysera\Bundle\ApiBundle\Exception\ConfigurationException;
use Paysera\Bundle\ApiBundle\Service\RestRequestHelper;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Route;

/**
 * @internal
 */
class RoutingAttributeLoader extends AttributeRouteControllerLoader
{
    private const ANNOTATION_NAMESPACE = 'paysera\bundle\apibundle\annotation\\';

    /**
     * @var RestRequestHelper
     */
    private $restRequestHelper;

    /**
     * @var RestRequestAnnotationOptionsBuilder
     */
    private $annotationOptionsBuilder;

    /**
     * @var RestRequestAttributeOptionsBuilder
     */
    private $attributeOptionsBuilder;

    /**
     * @var bool
     */
    private $docblockAnnotationsEnabled = false;

    /**
     * @var AnnotationReader|null
     */
    private $annotationReader;

    /**
     * @var array<string, RestAnnotationInterface[]>
     */
    private $classAnnotations = [];

    /**
     * @var PhpParser|null
     */
    private $phpParser;

    /**
     * @var array<string, string|null>
     */
    private $annotationNamespaceImports = [];

    public function setRequestHelper(RestRequestHelper $restRequestHelper)
    {
        $this->restRequestHelper = $restRequestHelper;
    }

    public function setRestRequestAnnotationOptionsBuilder(RestRequestAnnotationOptionsBuilder $annotationOptionsBuilder
    ) {
        $this->annotationOptionsBuilder = $annotationOptionsBuilder;
    }

    public function setRestRequestAttributeOptionsBuilder(RestRequestAttributeOptionsBuilder $attributeOptionsBuilder)
    {
        $this->attributeOptionsBuilder = $attributeOptionsBuilder;
    }

    public function setDocblockAnnotationsEnabled(bool $docblockAnnotationsEnabled): void
    {
        $this->docblockAnnotationsEnabled = $docblockAnnotationsEnabled;
    }

    protected function configureRoute(
        Route $route,
        ReflectionClass $class,
        ReflectionMethod $method,
        object $annot
    ): void {
        parent::configureRoute($route, $class, $method, $annot);

        $annotations = $this->getRestAnnotations($class, $method);
        $attributes = $this->getRestAttributes($class, $method);
        if ($annotations !== [] && $attributes !== []) {
            throw new ConfigurationException(sprintf(
                'The route of method %s::%s() is configured with both docblock annotations (%s) and attributes (%s)'
                . ' that implement the bundle\'s interfaces, on the method or its class: configure the method and its'
                . ' class with attributes only, or with docblock annotations only. No route is loaded, rather than one'
                . ' kind being applied and the other dropped',
                $class->getName(),
                $method->getName(),
                $this->listShortClassNames($annotations),
                $this->listShortClassNames($attributes)
            ));
        }

        if ($annotations !== []) {
            $this->restRequestHelper->setOptionsForRoute(
                $route,
                $this->annotationOptionsBuilder->buildOptions($annotations, $method)
            );
        } elseif ($attributes !== []) {
            $this->restRequestHelper->setOptionsForRoute(
                $route,
                $this->attributeOptionsBuilder->buildOptions($attributes, $method)
            );
        }
    }

    /**
     * @return RestAnnotationInterface[]
     */
    private function getRestAnnotations(ReflectionClass $class, ReflectionMethod $method): array
    {
        if (isset($this->reader)) {
            return $this->readRestAnnotations($this->reader, $class, $method);
        }

        if (!$this->docblockAnnotationsEnabled) {
            $this->refuseImportedAnnotations($class, $method);

            return [];
        }

        try {
            if ($this->annotationReader === null) {
                $this->annotationReader = new AnnotationReader();
            }

            return $this->readRestAnnotations($this->annotationReader, $class, $method);
        } catch (AnnotationException $exception) {
            throw new ConfigurationException(sprintf(
                'Reading the docblock annotations with paysera_api.docblock_annotations: true failed: %s Fix what'
                . ' this message names (a tag that is not an annotation can be listed with @IgnoreAnnotation in the'
                . ' class docblock), or replace the bundle\'s docblock annotations with its attributes and set the'
                . ' option to false. No route is loaded; reading stopped at method %s::%s()',
                $exception->getMessage(),
                $class->getName(),
                $method->getName()
            ), 0, $exception);
        }
    }

    /**
     * @return RestAnnotationInterface[]
     */
    private function readRestAnnotations(Reader $reader, ReflectionClass $class, ReflectionMethod $method): array
    {
        $annotations = $this->getRestClassAnnotations($reader, $class);
        foreach ($reader->getMethodAnnotations($method) as $annotation) {
            if ($annotation instanceof RestAnnotationInterface) {
                $annotations[] = $annotation;
            }
        }

        return $annotations;
    }

    /**
     * @return RestAnnotationInterface[]
     */
    private function getRestClassAnnotations(Reader $reader, ReflectionClass $class): array
    {
        $className = $class->getName();
        if (!isset($this->classAnnotations[$className])) {
            $annotations = [];
            foreach ($reader->getClassAnnotations($class) as $annotation) {
                if ($annotation instanceof RestAnnotationInterface) {
                    $annotations[] = $annotation;
                }
            }
            $this->classAnnotations[$className] = $annotations;
        }

        return $this->classAnnotations[$className];
    }

    private function refuseImportedAnnotations(ReflectionClass $class, ReflectionMethod $method): void
    {
        foreach ($this->getImportScopes($class, $method) as $scope) {
            $import = $this->findAnnotationNamespaceImport($scope);
            if ($import !== null) {
                throw new ConfigurationException(sprintf(
                    '%s imports %s, but the bundle\'s docblock annotations are not read where Symfony passes no'
                    . ' annotation reader and paysera_api.docblock_annotations is false: set the option to true, or'
                    . ' remove the import and use the bundle\'s attributes. No route is loaded; the check stopped at'
                    . ' method %s::%s()',
                    $scope->getName(),
                    $import,
                    $class->getName(),
                    $method->getName()
                ));
            }
        }
    }

    /**
     * @return ReflectionClass[]
     */
    private function getImportScopes(ReflectionClass $class, ReflectionMethod $method): array
    {
        $declaringClass = $method->getDeclaringClass();
        $scopes = [$class, $declaringClass];
        $traits = array_values($declaringClass->getTraits());
        while ($traits !== []) {
            $trait = array_shift($traits);
            if ($trait->getFileName() === $method->getFileName()) {
                $scopes[] = $trait;
            }
            $traits = array_merge($traits, array_values($trait->getTraits()));
        }

        return $scopes;
    }

    private function findAnnotationNamespaceImport(ReflectionClass $class): ?string
    {
        $className = $class->getName();
        if (!array_key_exists($className, $this->annotationNamespaceImports)) {
            if ($this->phpParser === null) {
                $this->phpParser = new PhpParser();
            }

            $found = null;
            foreach ($this->phpParser->parseUseStatements($class) as $import) {
                $name = ltrim($import, '\\');
                $prefix = strtolower($name) . '\\';
                if (
                    strpos(self::ANNOTATION_NAMESPACE, $prefix) === 0
                    || strpos($prefix, self::ANNOTATION_NAMESPACE) === 0
                ) {
                    $found = $name;
                    break;
                }
            }
            $this->annotationNamespaceImports[$className] = $found;
        }

        return $this->annotationNamespaceImports[$className];
    }

    /**
     * @param object[] $objects
     */
    private function listShortClassNames(array $objects): string
    {
        $names = [];
        foreach ($objects as $object) {
            $names[] = (new ReflectionClass($object))->getShortName();
        }

        return implode(', ', $names);
    }

    /**
     * @return RestAttributeInterface[]
     */
    private function getRestAttributes(ReflectionClass $class, ReflectionMethod $method): array
    {
        $attributes = array_merge($class->getAttributes(), $method->getAttributes());

        $restAttributes = [];
        foreach ($attributes as $attribute) {
            if (is_subclass_of($attribute->getName(), RestAttributeInterface::class)) {
                /** @var RestAttributeInterface $instance */
                $instance = $attribute->newInstance();
                $restAttributes[] = $instance;
            }
        }

        return $restAttributes;
    }
}
