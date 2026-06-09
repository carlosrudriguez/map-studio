<?php
declare(strict_types=1);

namespace MapStudio;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Prepares discovered SVG maps for isolated public rendering.
 * It depends on MapDefinition so duplicate raw SVG IDs can render safely.
 */
final class SvgMap {
    private MapDefinition $mapDefinition;

    public function __construct(MapDefinition $mapDefinition) {
        $this->mapDefinition = $mapDefinition;
    }

    /**
     * @return array<int, string>
     */
    public function pathIds(): array {
        $document = $this->loadDocument();
        $pathIds = [];

        foreach ($document->getElementsByTagName('path') as $path) {
            if ($path instanceof DOMElement && $path->hasAttribute('id')) {
                $pathIds[] = $path->getAttribute('id');
            }
        }

        return $pathIds;
    }

    /**
     * @param array<int, string> $activeRegionKeys
     * @param array<string, string> $customRegionColors
     */
    public function renderForInstance(string $instanceId, array $activeRegionKeys, array $customRegionColors = []): string {
        $document = $this->loadDocument();
        $root = $document->documentElement;

        if (!$root instanceof DOMElement) {
            throw new RuntimeException('SVG document root is missing.');
        }

        $this->appendClasses($root, ['map-studio__svg']);
        $activeRegions = array_fill_keys($activeRegionKeys, true);
        $customColorRegions = array_fill_keys(array_keys($customRegionColors), true);
        $pathIndex = 0;

        foreach ($document->getElementsByTagName('path') as $path) {
            if (!$path instanceof DOMElement || !$path->hasAttribute('id')) {
                continue;
            }

            $svgId = $path->getAttribute('id');
            $label = $this->labelForPath($path);
            $regionKey = $this->mapDefinition->keyForPath($svgId, $label);

            if ($regionKey === null) {
                continue;
            }

            $pathIndex++;
            $path->setAttribute('data-map-studio-region-key', $regionKey);
            $path->setAttribute('id', $instanceId . '-' . $regionKey . '-' . $pathIndex);

            $classes = ['map-studio__region'];

            if (isset($customColorRegions[$regionKey])) {
                $classes[] = 'has-custom-color';
            }

            if (isset($activeRegions[$regionKey])) {
                $classes[] = 'is-active';
                $this->appendClasses($path, $classes);
                $path->setAttribute('role', 'button');
                $path->setAttribute('tabindex', '0');
                $path->setAttribute('aria-label', $this->mapDefinition->shapeLabel($regionKey) ?? $label);
                $path->removeAttribute('aria-hidden');
                continue;
            }

            $classes[] = 'is-inactive';
            $this->appendClasses($path, $classes);
            $path->removeAttribute('role');
            $path->removeAttribute('tabindex');
            $path->removeAttribute('aria-label');
            $path->setAttribute('aria-hidden', 'true');
        }

        $markup = $document->saveXML($root);

        if (!is_string($markup)) {
            throw new RuntimeException('SVG markup could not be serialized.');
        }

        return $markup;
    }

    private function loadDocument(): DOMDocument {
        if (!is_readable($this->mapDefinition->assetPath())) {
            throw new RuntimeException('SVG asset is not readable.');
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->load($this->mapDefinition->assetPath(), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new RuntimeException('SVG asset could not be parsed.');
        }

        return $document;
    }

    private function labelForPath(DOMElement $path): string {
        $label = trim($path->getAttribute('data-name'));

        if ($label !== '') {
            return $label;
        }

        return trim($path->getAttribute('data-name_long'));
    }

    /**
     * @param array<int, string> $classes
     */
    private function appendClasses(DOMElement $element, array $classes): void {
        $currentClasses = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
        $classMap = [];

        foreach ($currentClasses as $className) {
            if ($className !== '') {
                $classMap[$className] = true;
            }
        }

        foreach ($classes as $className) {
            $classMap[$className] = true;
        }

        $element->setAttribute('class', implode(' ', array_keys($classMap)));
    }
}
