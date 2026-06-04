<?php
declare(strict_types=1);

namespace MapStudio;

use DOMDocument;
use DOMElement;

/**
 * Discovers map SVG files and extracts labeled shapes for Map Studio.
 * Friendly map labels are kept here while shape labels come from SVG metadata.
 */
final class MapRegistry {
    /**
     * @var array<string, string>
     */
    private const MAP_LABELS = [
        'AE' => 'United Arab Emirates',
        'AFRICA' => 'Africa',
        'AR' => 'Argentina',
        'ASIA' => 'Asia',
        'AT' => 'Austria',
        'AU' => 'Australia',
        'BE' => 'Belgium',
        'BG' => 'Bulgaria',
        'BR' => 'Brazil',
        'CA' => 'Canada',
        'CH' => 'Switzerland',
        'CN' => 'China',
        'CONTINENTS' => 'Continents',
        'CY' => 'Cyprus',
        'CZ' => 'Czechia',
        'DE' => 'Germany',
        'DK' => 'Denmark',
        'EE' => 'Estonia',
        'EG' => 'Egypt',
        'ES' => 'Spain',
        'EUROPE' => 'Europe',
        'FI' => 'Finland',
        'FR' => 'France',
        'GB' => 'United Kingdom',
        'GR' => 'Greece',
        'HR' => 'Croatia',
        'HU' => 'Hungary',
        'ID' => 'Indonesia',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IN' => 'India',
        'IS' => 'Iceland',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'LT' => 'Lithuania',
        'LV' => 'Latvia',
        'MX' => 'Mexico',
        'MY' => 'Malaysia',
        'NL' => 'Netherlands',
        'NO' => 'Norway',
        'NZ' => 'New Zealand',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'RO' => 'Romania',
        'RS' => 'Serbia',
        'RU' => 'Russia',
        'SA' => 'Saudi Arabia',
        'SE' => 'Sweden',
        'SI' => 'Slovenia',
        'SK' => 'Slovakia',
        'TH' => 'Thailand',
        'TR' => 'Turkey',
        'UA' => 'Ukraine',
        'US' => 'United States',
        'WORLD' => 'World',
        'ZA' => 'South Africa',
    ];

    private string $mapsPath;

    /**
     * @var array<string, MapDefinition>|null
     */
    private ?array $maps = null;

    public function __construct(string $mapsPath) {
        $this->mapsPath = rtrim($mapsPath, '/');
    }

    /**
     * @return array<string, MapDefinition>
     */
    public function all(): array {
        if (is_array($this->maps)) {
            return $this->maps;
        }

        $maps = [];
        $files = glob($this->mapsPath . '/*.svg') ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $file) {
            $map = $this->definitionFromFile($file);

            if ($map !== null) {
                $maps[$map->id()] = $map;
            }
        }

        uasort($maps, static fn(MapDefinition $a, MapDefinition $b): int => strnatcasecmp($a->label(), $b->label()));

        $this->maps = $maps;

        return $this->maps;
    }

    public function get(string $mapId): ?MapDefinition {
        $mapId = strtoupper(trim($mapId));
        $maps = $this->all();

        return $maps[$mapId] ?? null;
    }

    private function definitionFromFile(string $file): ?MapDefinition {
        $id = strtoupper(pathinfo($file, PATHINFO_FILENAME));
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->load($file, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        $paths = [];
        $labelsById = [];

        foreach ($document->getElementsByTagName('path') as $path) {
            if (!$path instanceof DOMElement || !$path->hasAttribute('id')) {
                continue;
            }

            $svgId = trim($path->getAttribute('id'));
            $label = $this->labelForPath($path);

            if ($svgId === '' || $label === '') {
                continue;
            }

            $paths[] = [
                'svgId' => $svgId,
                'label' => $label,
            ];
            $labelsById[$svgId][$label] = true;
        }

        if ($paths === []) {
            return null;
        }

        $shapes = [];
        $seenShapes = [];
        $pathKeys = [];

        foreach ($paths as $path) {
            $key = $this->shapeKeyFor($path['svgId'], $path['label'], $labelsById[$path['svgId']] ?? []);
            $lookupKey = $path['svgId'] . "\n" . $path['label'];
            $pathKeys[$lookupKey] = $key;

            if (isset($seenShapes[$key])) {
                continue;
            }

            $seenShapes[$key] = true;
            $shapes[] = [
                'key' => $key,
                'svgId' => $path['svgId'],
                'label' => $path['label'],
            ];
        }

        usort($shapes, static fn(array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return new MapDefinition(
            $id,
            self::MAP_LABELS[$id] ?? $this->fallbackLabel($id),
            $file,
            $shapes,
            $pathKeys
        );
    }

    private function labelForPath(DOMElement $path): string {
        $label = trim($path->getAttribute('data-name'));

        if ($label !== '') {
            return $label;
        }

        return trim($path->getAttribute('data-name_long'));
    }

    /**
     * @param array<string, true> $labelsForId
     */
    private function shapeKeyFor(string $svgId, string $label, array $labelsForId): string {
        if (count($labelsForId) <= 1) {
            return $svgId;
        }

        $base = trim($svgId, "-_ \t\n\r\0\x0B");

        if ($base === '') {
            $base = '99';
        }

        return $base . '--' . $this->slug($label);
    }

    private function slug(string $value): string {
        $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : false;
        $value = is_string($ascii) && $ascii !== '' ? $ascii : $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'region';
    }

    private function fallbackLabel(string $id): string {
        return ucwords(strtolower(str_replace(['-', '_'], ' ', $id)));
    }
}
