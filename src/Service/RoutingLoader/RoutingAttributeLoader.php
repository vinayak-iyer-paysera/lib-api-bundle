<?php

declare(strict_types=1);

namespace Paysera\Bundle\ApiBundle\Service\RoutingLoader;

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
    private const DOCBLOCK_TAG_PATTERN = '/(?<![^\\s*])@(\\\\*[a-z_][\\w\\\\]*(?:(?<=\\\\)[\\s*]+[a-z_][\\w\\\\]*)*)/i';

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
     * @var array<string, string[]>
     */
    private $docblockAnnotations = [];

    /**
     * @var array<string, string>|null
     */
    private $annotationClasses;

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
        $contexts = [[$class, $class], [$this->getMethodOwner($method), $method->getDeclaringClass()]];
        foreach ($contexts as [$owner, $importer]) {
            $names = $this->getDocblockAnnotations($owner, $importer);
            if ($names !== []) {
                throw new ConfigurationException(sprintf(
                    'Cannot load the route of %s::%s(): %s uses docblock annotations of paysera/lib-api-bundle (@%s), '
                    . 'which are not read because no annotation reader is available. Use the attributes of the same '
                    . 'name from Paysera\\Bundle\\ApiBundle\\Attribute instead.',
                    $class->getName(),
                    $method->getName(),
                    $owner->getFileName(),
                    implode(', @', $names)
                ));
            }
        }
    }

    private function getMethodOwner(ReflectionMethod $method): ReflectionClass
    {
        $owner = $method->getDeclaringClass();
        $traits = $owner->getTraits();
        while (
            ($owner->getFileName() !== $method->getFileName() || !$owner->hasMethod($method->getName()))
            && $traits !== []
        ) {
            $owner = array_shift($traits);
            $traits = array_merge($traits, $owner->getTraits());
        }

        return $owner;
    }

    /**
     * @return string[]
     */
    private function getDocblockAnnotations(ReflectionClass $owner, ReflectionClass $importer): array
    {
        $key = $owner->getFileName() . '|' . $importer->getName();
        if (!array_key_exists($key, $this->docblockAnnotations)) {
            $this->docblockAnnotations[$key] = $this->readDocblockAnnotations($owner, $importer);
        }

        return $this->docblockAnnotations[$key];
    }

    /**
     * @return string[]
     */
    private function readDocblockAnnotations(ReflectionClass $owner, ReflectionClass $importer): array
    {
        $parser = new PhpParser();
        $imports = array_filter(
            array_merge($parser->parseUseStatements($importer), $parser->parseUseStatements($owner)),
            [$this, 'isInAnnotationNamespace']
        );
        $namespace = $importer->getNamespaceName();
        $source = (string)file_get_contents((string)$owner->getFileName());
        if (
            $imports === []
            && !$this->isInAnnotationNamespace($namespace)
            && stripos($source, self::ANNOTATION_NAMESPACE) === false
        ) {
            return [];
        }

        $names = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                preg_match_all(self::DOCBLOCK_TAG_PATTERN, $token[1], $matches);
                foreach ($matches[1] as $tag) {
                    $tag = (string)preg_replace('/\\\\[\\s*]+/', '\\\\', $tag);
                    $names[] = $this->resolveAnnotation($tag, $imports, $namespace);
                }
            }
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * @param array<string, string> $imports
     */
    private function resolveAnnotation(string $tag, array $imports, string $namespace): ?string
    {
        $separator = strpos($tag, '\\');
        $alias = strtolower($separator === false ? $tag : substr($tag, 0, $separator));
        $candidates = [$tag, $namespace . '\\' . $tag];
        if (isset($imports[$alias])) {
            $candidates[] = $imports[$alias] . ($separator === false ? '' : substr($tag, $separator));
        }

        $annotationClasses = $this->getAnnotationClasses();
        foreach ($candidates as $candidate) {
            $name = $annotationClasses[strtolower(ltrim($candidate, '\\'))] ?? null;
            if ($name !== null) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function getAnnotationClasses(): array
    {
        if ($this->annotationClasses === null) {
            $this->annotationClasses = [];
            foreach (scandir(dirname(__DIR__, 2) . '/Annotation') ?: [] as $file) {
                if (substr($file, -4) === '.php') {
                    $name = basename($file, '.php');
                    $this->annotationClasses[strtolower(self::ANNOTATION_NAMESPACE . $name)] = $name;
                }
            }
        }

        return $this->annotationClasses;
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
