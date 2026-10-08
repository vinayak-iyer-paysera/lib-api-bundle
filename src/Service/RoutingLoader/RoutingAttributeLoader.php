<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Service\RoutingLoader;

use Doctrine\Common\Annotations\DocParser;
use Doctrine\Common\Annotations\PhpParser;
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
     * @var array<string, array<string, string>|null>
     */
    private $annotationImports = [];

    /**
     * @var DocParser|null
     */
    private $docParser;

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

    /**
     * @throws ConfigurationException
     */
    private function loadAnnotations(Route $route, ReflectionClass $class, ReflectionMethod $method): void
    {
        if (!property_exists($this, 'reader') || !isset($this->reader)) {
            $this->refuseDocblockAnnotations($class, $method);

            return;
        }

        $annotations = [];
        foreach ($this->reader->getClassAnnotations($class) as $annotation) {
            if ($annotation instanceof RestAnnotationInterface) {
                $annotations[] = $annotation;
            }
        }

        foreach ($this->reader->getMethodAnnotations($method) as $annotation) {
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
     * @throws ConfigurationException
     */
    private function refuseDocblockAnnotations(ReflectionClass $class, ReflectionMethod $method): void
    {
        $classImports = $this->getAnnotationImports($class);
        $methodImports = $this->getMethodAnnotationImports($method);
        if ($classImports === null && $methodImports === null) {
            return;
        }

        if ((new ReflectionClass(self::class))->getDocComment() === false) {
            throw new ConfigurationException(sprintf(
                '%s::%s() cannot be checked for docblock annotations of paysera/lib-api-bundle because PHP strips '
                . 'docblocks. Enable opcache.save_comments.',
                $class->getName(),
                $method->getName()
            ));
        }

        $parser = $this->getDocParser();
        $parser->setImports($classImports ?? []);
        $annotations = $parser->parse((string)$class->getDocComment(), 'class ' . $class->getName());
        $parser->setImports($methodImports ?? []);
        $annotations = array_merge($annotations, $parser->parse(
            (string)$method->getDocComment(),
            sprintf('method %s::%s()', $class->getName(), $method->getName())
        ));
        $names = [];
        foreach ($annotations as $annotation) {
            if ($annotation instanceof RestAnnotationInterface) {
                $names[] = (new ReflectionClass($annotation))->getShortName();
            }
        }

        if ($names === []) {
            return;
        }

        throw new ConfigurationException(sprintf(
            '%s::%s() uses docblock annotations of paysera/lib-api-bundle (@%s), which are not read because no '
            . 'annotation reader is available. Use the attributes of the same name from '
            . 'Paysera\\Bundle\\ApiBundle\\Attribute instead.',
            $class->getName(),
            $method->getName(),
            implode(', @', array_unique($names))
        ));
    }

    /**
     * @return array<string, string>|null
     */
    private function getMethodAnnotationImports(ReflectionMethod $method): ?array
    {
        $class = $method->getDeclaringClass();
        $imports = $this->getAnnotationImports($class);
        foreach ($class->getTraits() as $trait) {
            if ($trait->getFileName() !== $method->getFileName() || !$trait->hasMethod($method->getName())) {
                continue;
            }

            $traitImports = $this->getAnnotationImports($trait);
            if ($traitImports !== null) {
                $imports = array_merge($imports ?? [], $traitImports);
            }
        }

        return $imports;
    }

    /**
     * @return array<string, string>|null null when the file of the class neither imports nor names the annotations
     */
    private function getAnnotationImports(ReflectionClass $class): ?array
    {
        $name = $class->getName();
        if (!array_key_exists($name, $this->annotationImports)) {
            $imports = array_filter(
                (new PhpParser())->parseUseStatements($class),
                static function (string $import): bool {
                    $namespace = ltrim($import, '\\') . '\\';

                    return strpos($namespace, self::ANNOTATION_NAMESPACE) === 0
                        || strpos(self::ANNOTATION_NAMESPACE, $namespace) === 0;
                }
            );
            $file = $class->getFileName();
            $named = $file !== false && strpos((string)file_get_contents($file), self::ANNOTATION_NAMESPACE) !== false;
            $this->annotationImports[$name] = $imports !== [] || $named ? $imports : null;
        }

        return $this->annotationImports[$name];
    }

    private function getDocParser(): DocParser
    {
        if ($this->docParser === null) {
            $this->docParser = new DocParser();
            $this->docParser->setIgnoreNotImportedAnnotations(true);
        }

        return $this->docParser;
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
