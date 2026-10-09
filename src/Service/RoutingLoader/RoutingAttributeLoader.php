<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Service\RoutingLoader;

use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\DocParser;
use Doctrine\Common\Annotations\PhpParser;
use Paysera\Bundle\ApiBundle\Annotation\RestAnnotationInterface;
use Paysera\Bundle\ApiBundle\Attribute\RestAttributeInterface;
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
    private const ANNOTATION_NAMESPACE = 'Paysera\\Bundle\\ApiBundle\\Annotation\\';

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
     * @var DocParser|null
     */
    private $docParser;

    /**
     * @var array<string, array<string, string>>
     */
    private $imports = [];

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

    protected function configureRoute(
        Route $route,
        ReflectionClass $class,
        ReflectionMethod $method,
        object $annot
    ): void {
        parent::configureRoute($route, $class, $method, $annot);

        $this->loadAnnotations($route, $class, $method);
        $this->loadAttributes($route, $class, $method);
    }

    private function loadAnnotations(Route $route, ReflectionClass $class, ReflectionMethod $method): void
    {
        if (isset($this->reader)) {
            $classAnnotations = $this->reader->getClassAnnotations($class);
            $methodAnnotations = $this->reader->getMethodAnnotations($method);
        } else {
            $classAnnotations = $this->parseDocblock(
                $class->getDocComment(),
                $class,
                Target::TARGET_CLASS,
                'class ' . $class->getName()
            );
            $methodAnnotations = $this->parseDocblock(
                $method->getDocComment(),
                $method->getDeclaringClass(),
                Target::TARGET_METHOD,
                'method ' . $method->getDeclaringClass()->getName() . '::' . $method->getName() . '()'
            );
        }

        $annotations = [];
        foreach (array_merge($classAnnotations, $methodAnnotations) as $annotation) {
            if ($annotation instanceof RestAnnotationInterface) {
                $annotations[] = $annotation;
            }
        }

        if ($annotations === []) {
            return;
        }

        $this->restRequestHelper->setOptionsForRoute(
            $route,
            $this->annotationOptionsBuilder->buildOptions($annotations, $method)
        );
    }

    /**
     * @param string|false $docComment
     * @return object[]
     */
    private function parseDocblock($docComment, ReflectionClass $importingClass, int $target, string $context): array
    {
        if ($docComment === false) {
            return [];
        }

        if ($this->docParser === null) {
            $this->docParser = new DocParser();
            $this->docParser->setIgnoreNotImportedAnnotations(true);
        }
        $this->docParser->setTarget($target);
        $this->docParser->setImports($this->getImports($importingClass));

        return $this->docParser->parse($docComment, $context);
    }

    /**
     * @return array<string, string>
     */
    private function getImports(ReflectionClass $class): array
    {
        $name = $class->getName();
        if (!isset($this->imports[$name])) {
            $this->imports[$name] = $this->getAnnotationUseStatements($class);
            if ($this->isInAnnotationNamespace($class->getNamespaceName())) {
                $this->imports[$name]['__NAMESPACE__'] = $class->getNamespaceName();
            }
        }

        return $this->imports[$name];
    }

    /**
     * @return array<string, string>
     */
    private function getAnnotationUseStatements(ReflectionClass $class): array
    {
        $useStatements = array_filter(
            (new PhpParser())->parseUseStatements($class),
            [$this, 'isInAnnotationNamespace']
        );
        foreach ($class->getTraits() as $trait) {
            $useStatements = array_merge($useStatements, $this->getAnnotationUseStatements($trait));
        }

        return $useStatements;
    }

    private function isInAnnotationNamespace(string $name): bool
    {
        $namespace = strtolower(ltrim($name, '\\') . '\\');
        $annotationNamespace = strtolower(self::ANNOTATION_NAMESPACE);

        return strpos($namespace, $annotationNamespace) === 0 || strpos($annotationNamespace, $namespace) === 0;
    }

    private function loadAttributes(Route $route, ReflectionClass $class, ReflectionMethod $method): void
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

        if ($restAttributes === []) {
            return;
        }

        $this->restRequestHelper->setOptionsForRoute(
            $route,
            $this->attributeOptionsBuilder->buildOptions($restAttributes, $method)
        );
    }
}
