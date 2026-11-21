<?php

/**
 * Unit-suite bootstrap. Loads the plugin's Composer autoloader plus Yii's and
 * Craft's class façades so pure logic that calls `Craft::t()` resolves without
 * booting a full Craft application. With no app present, `Craft::t()` falls
 * back to placeholder interpolation on the source string — exactly what unit
 * assertions expect. App-backed behaviour stays in the integration suite.
 */

$root = dirname(__DIR__, 2);

require_once $root . '/vendor/autoload.php';
require_once $root . '/vendor/yiisoft/yii2/Yii.php';

// Craft 6 moved the global \Craft facade out of craftcms/cms entirely, into
// the yii2-adapter package instead (vendor/craftcms/cms/src/Craft.php simply
// doesn't exist there). Craft 5 still has it at the original path.
$craftFacade = $root . '/vendor/craftcms/cms/src/Craft.php';
if (!file_exists($craftFacade)) {
    $craftFacade = $root . '/vendor/craftcms/yii2-adapter/legacy/Craft.php';
}
require_once $craftFacade;

// On Craft 6, craft\base\Field and friends only resolve to the real
// CraftCms\Cms\... classes once craftcms/yii2-adapter's class_alias() calls
// have run — normal app boot (Laravel's service-provider discovery) does
// this automatically, a bare require of the autoloader does not. No-op on
// Craft 5, where the adapter isn't installed at all.
if (class_exists(\CraftCms\Yii2Adapter\ClassAliases::class)) {
    (new \CraftCms\Yii2Adapter\ClassAliases())->register();
}

// Craft 6's Craft::t() routes through a real Laravel Facade (I18N) instead
// of the old no-app-tolerant fallback this bootstrap's docblock describes —
// with no facade root set, it hard-throws ("A facade root has not been
// set") instead of returning the source string. Stand in a container
// binding a translate() that does the same placeholder interpolation Craft
// 5's fallback did, restoring the behavior this suite was built to rely on.
// No-op on Craft 5, where Illuminate\Support\Facades\Facade isn't installed.
if (class_exists(\Illuminate\Support\Facades\Facade::class)) {
    $container = new \Illuminate\Container\Container();
    $container->instance(\CraftCms\Cms\Translation\I18N::class, new class {
        public function translate(\Stringable|string|null $message, array $parameters = [], ?string $category = null, ?string $locale = null): string
        {
            $message = (string) $message;
            foreach ($parameters as $key => $value) {
                $message = str_replace('{' . $key . '}', (string) $value, $message);
            }

            return $message;
        }
    });
    \Illuminate\Support\Facades\Facade::setFacadeApplication($container);
}
