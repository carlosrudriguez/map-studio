# Map Studio

Build interactive SVG maps in WordPress with region-specific content, custom colors, smooth vector zoom, and an optional clickable region list.

Map Studio is designed for site owners and editors who need polished, configurable maps without building custom templates for each project. Create a map in the WordPress dashboard, choose a built-in SVG map, add content to the regions that matter, and publish it anywhere with a shortcode.

## What It Does

- Creates reusable map records in the WordPress admin.
- Lets editors choose from the bundled SVG map library.
- Adds rich content to individual regions with the standard WordPress editor.
- Makes only content-filled regions clickable on the public map.
- Supports optional custom colors per region.
- Provides global colors for inactive regions, active regions, hover states, strokes, and info bubbles.
- Adds an optional public region list sidebar, shown only for regions that have content.
- Lets the region list appear on the left or right side of the map.
- Uses SVG `viewBox` zooming so clicked regions stay crisp instead of becoming pixelated.
- Supports multiple maps on the same page with isolated styles and interactions.

## Included Maps

Map Studio discovers SVG files from `assets/maps/`. The current library includes 57 maps:

- Africa (`AFRICA`)
- Argentina (`AR`)
- Asia (`ASIA`)
- Australia (`AU`)
- Austria (`AT`)
- Belgium (`BE`)
- Brazil (`BR`)
- Bulgaria (`BG`)
- Canada (`CA`)
- China (`CN`)
- Continents (`CONTINENTS`)
- Croatia (`HR`)
- Cyprus (`CY`)
- Czechia (`CZ`)
- Denmark (`DK`)
- Egypt (`EG`)
- Estonia (`EE`)
- Europe (`EUROPE`)
- Finland (`FI`)
- France (`FR`)
- Germany (`DE`)
- Greece (`GR`)
- Hungary (`HU`)
- Iceland (`IS`)
- India (`IN`)
- Indonesia (`ID`)
- Ireland (`IE`)
- Israel (`IL`)
- Italy (`IT`)
- Japan (`JP`)
- Latvia (`LV`)
- Lithuania (`LT`)
- Malaysia (`MY`)
- Mexico (`MX`)
- Netherlands (`NL`)
- New Zealand (`NZ`)
- Norway (`NO`)
- Poland (`PL`)
- Portugal (`PT`)
- Romania (`RO`)
- Russia (`RU`)
- Saudi Arabia (`SA`)
- Serbia (`RS`)
- Slovakia (`SK`)
- Slovenia (`SI`)
- South Africa (`ZA`)
- South Korea (`KR`)
- Spain (`ES`)
- Sweden (`SE`)
- Switzerland (`CH`)
- Thailand (`TH`)
- Turkey (`TR`)
- Ukraine (`UA`)
- United Arab Emirates (`AE`)
- United Kingdom (`GB`)
- United States (`US`)
- World (`WORLD`)

## How To Use

1. Upload and activate the plugin in WordPress.
2. Go to **Map Studio > Maps**.
3. Add a new map and give it a title.
4. Choose a base map in **Map Setup**.
5. Add content to the regions you want visitors to click.
6. Optional: enable **Region list** and choose whether the sidebar appears on the left or right.
7. Optional: adjust selected region colors and global map colors in **Appearance**.
8. Publish the map.
9. Copy the shortcode into a page, post, or template area:

```text
[map_studio id="123"]
```

Replace `123` with the ID of the published Map Studio map.

## Public Behavior

Visitors can interact with a map in two ways:

- Click a highlighted region directly on the SVG map.
- Click the matching name in the optional region list sidebar.

Both actions open the same content bubble and trigger the same map zoom behavior. Regions without content remain inactive, even if they have a custom color in the editor.

## Requirements

- WordPress with standard plugin support.
- PHP 8.0 or newer.
- A theme or page layout wide enough to display responsive SVG content.

There is no Composer, npm, or build pipeline required.

## Development

The plugin is intentionally small and framework-free. The main entry point is `map-studio.php`, PHP classes live in `includes/`, admin and frontend assets live in `assets/`, and deterministic CLI checks live in `tests/contracts.php`.

Run the contract suite with:

```bash
php tests/contracts.php
```

Useful syntax checks after editing PHP or JavaScript:

```bash
php -l map-studio.php
php -l includes/MapMeta.php
php -l includes/Admin/MapMetaBox.php
php -l includes/Admin/MapDashboardSection.php
php -l includes/Admin/MapSettingsFields.php
php -l includes/Frontend/Shortcode.php
php -l tests/contracts.php
node --check assets/js/admin.js
node --check assets/js/frontend.js
node --check assets/js/viewbox-animation.js
```

## License

Map Studio is licensed under the GPL-3.0-or-later. See `LICENSE` for the full license text.
