<?php
// Rend le catalogue avec le vrai moteur Blade de Laravel (vendor de xomiton/) -> blade/_rendu/styleguide.html
// Usage : cd xomiton && php ../design-system/blade/render-test.php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$here = __DIR__;
Blade::anonymousComponentPath($here.'/components');
view()->addNamespace('ds', $here);
$errors = new Illuminate\Support\ViewErrorBag;
view()->share('errors', $errors);

$brand = $here.'/_rendu/assets/images/brand'; @mkdir($brand, 0777, true);
foreach (glob(dirname($here).'/logo/*.svg') as $f) copy($f, $brand.'/'.basename($f));
config(['app.asset_url' => 'file:///'.str_replace('\\', '/', $here).'/_rendu']);
$ds = 'file:///'.str_replace('\\', '/', dirname($here));
$html = view('ds::styleguide', [
    'css' => [
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
        "$ds/tokens/tokens.css", "$ds/tokens/social-xomiton.css", "$ds/tokens/components.css",
    ],
    'js' => [
        'https://cdn.jsdelivr.net/npm/@alpinejs/focus@3.14.1/dist/cdn.min.js',
        "$ds/blade/js/xomiton-ui.js",
        'https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    ],
])->render();

@mkdir($here.'/_rendu');
file_put_contents($here.'/_rendu/styleguide.html', $html);
echo "ok ", strlen($html), " octets\n";
