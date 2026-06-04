<?php
declare(strict_types=1);

namespace MapStudio;

/**
 * Represents one discovered SVG map and its selectable shapes.
 * The renderer and editor depend on these stable keys instead of raw SVG IDs.
 */
final class MapDefinition {
    private string $id;
    private string $label;
    private string $assetPath;

    /**
     * @var array<int, array{key: string, svgId: string, label: string}>
     */
    private array $shapes;

    /**
     * @var array<string, string>
     */
    private array $shapeLabels;

    /**
     * @var array<string, string>
     */
    private array $pathKeys;

    /**
     * @param array<int, array{key: string, svgId: string, label: string}> $shapes
     * @param array<string, string> $pathKeys
     */
    public function __construct(string $id, string $label, string $assetPath, array $shapes, array $pathKeys) {
        $this->id = $id;
        $this->label = $label;
        $this->assetPath = $assetPath;
        $this->shapes = $shapes;
        $this->pathKeys = $pathKeys;
        $this->shapeLabels = [];

        foreach ($shapes as $shape) {
            $this->shapeLabels[$shape['key']] = $shape['label'];
        }
    }

    public function id(): string {
        return $this->id;
    }

    public function label(): string {
        return $this->label;
    }

    public function assetPath(): string {
        return $this->assetPath;
    }

    /**
     * @return array<int, array{key: string, svgId: string, label: string}>
     */
    public function shapes(): array {
        return $this->shapes;
    }

    public function shapeLabel(string $shapeKey): ?string {
        return $this->shapeLabels[$shapeKey] ?? null;
    }

    public function hasShape(string $shapeKey): bool {
        return isset($this->shapeLabels[$shapeKey]);
    }

    public function keyForPath(string $svgId, string $label): ?string {
        return $this->pathKeys[$this->pathLookupKey($svgId, $label)] ?? null;
    }

    public function pathLookupKey(string $svgId, string $label): string {
        return $svgId . "\n" . $label;
    }
}
