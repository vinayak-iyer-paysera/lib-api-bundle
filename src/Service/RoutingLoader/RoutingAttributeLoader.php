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
     * @var array<string, bool>
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
                . ' that implement the bundle\'s interfaces, on the method or its class, so no route is loaded: the'
                . ' attributes would replace all of the docblock options. Configure the method and its class with'
                . ' attributes only, or with docblock annotations only',
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
        try {
            return $this->readRestAnnotations($class, $method);
        } catch (AnnotationException $exception) {
            throw new ConfigurationException(sprintf(
                'Reading the docblock annotations of method %s::%s() failed, so no route is loaded. %s Fix what this'
                . ' message names (a tag that is not an annotation can be listed with @IgnoreAnnotation in the class'
                . ' docblock), or replace the bundle\'s docblock annotations with its attributes and set'
                . ' paysera_api.docblock_annotations to false',
                $class->getName(),
                $method->getName(),
                $exception->getMessage()
            ), 0, $exception);
        }
    }

    /**
     * @return RestAnnotationInterface[]
     */
    private function readRestAnnotations(ReflectionClass $class, ReflectionMethod $method): array
    {
        $reader = $this->getAnnotationReader();
        if ($reader === null) {
            if ($this->importsBundleAnnotations($class, $method)) {
                throw new ConfigurationException(sprintf(
                    'The bundle\'s docblock annotations are imported for the route of method %s::%s(), but they'
                    . ' are not read: Symfony passes no annotation reader and paysera_api.docblock_annotations is'
                    . ' false. Set the option to true, or replace them with the bundle\'s attributes and remove the'
                    . ' import',
                    $class->getName(),
                    $method->getName()
                ));
            }

            return [];
        }

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
            $this->classAnnotations[$className] = [];
            foreach ($reader->getClassAnnotations($class) as $annotation) {
                if ($annotation instanceof RestAnnotationInterface) {
                    $this->classAnnotations[$className][] = $annotation;
                }
            }
        }

        return $this->classAnnotations[$className];
    }

    private function importsBundleAnnotations(ReflectionClass $class, ReflectionMethod $method): bool
    {
        $declaringClass = $method->getDeclaringClass();
        $scopes = [$class, $declaringClass];
        foreach ($declaringClass->getTraits() as $trait) {
            if ($trait->hasMethod($method->getName()) && $trait->getFileName() === $method->getFileName()) {
                $scopes[] = $trait;
            }
        }

        foreach ($scopes as $scope) {
            if ($this->importsAnnotationNamespace($scope)) {
                return true;
            }
        }

        return false;
    }

    private function importsAnnotationNamespace(ReflectionClass $class): bool
    {
        $className = $class->getName();
        if (!isset($this->annotationNamespaceImports[$className])) {
            if ($this->phpParser === null) {
                $this->phpParser = new PhpParser();
            }

            $this->annotationNamespaceImports[$className] = false;
            foreach ($this->phpParser->parseUseStatements($class) as $import) {
                $import = strtolower($import) . '\\';
                if (
                    strpos(self::ANNOTATION_NAMESPACE, $import) === 0
                    || strpos($import, self::ANNOTATION_NAMESPACE) === 0
                ) {
                    $this->annotationNamespaceImports[$className] = true;
                    break;
                }
            }
        }

        return $this->annotationNamespaceImports[$className];
    }

    private function getAnnotationReader(): ?Reader
    {
        if (isset($this->reader)) {
            return $this->reader;
        }

        if (!$this->docblockAnnotationsEnabled) {
            return null;
        }

        if ($this->annotationReader === null) {
            $this->annotationReader = new AnnotationReader();
        }

        return $this->annotationReader;
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
