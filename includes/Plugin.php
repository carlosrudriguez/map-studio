<?php
declare(strict_types=1);

namespace MapStudio;

/**
 * Registers Map Studio services with WordPress hooks from one central entry point.
 * Feature services are discovered defensively so CLI contracts can run without WordPress.
 */
final class Plugin {
    public function register(): void {
        $services = [
            Admin\Menu::class,
            Admin\MapPostType::class,
            Admin\MapListTable::class,
            Admin\MapMetaBox::class,
            Frontend\Shortcode::class,
        ];

        foreach ($services as $serviceClass) {
            if (!class_exists($serviceClass)) {
                continue;
            }

            $service = new $serviceClass();

            if (method_exists($service, 'register')) {
                $service->register();
            }
        }
    }
}
