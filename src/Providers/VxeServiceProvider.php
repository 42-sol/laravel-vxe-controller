<?php

namespace VxeController\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use VxeController\Http\Controller\VxeController;

class VxeServiceProvider extends ServiceProvider {

  /**
   * Boot the application events.
   */
  public function boot() {
    $this
      ->registerTranslations()
      ->registerMacro();
  }

  private function registerMacro() {
    Route::macro('vxeController', function ($controller) {
      /** @var VxeController $instance */
      $instance = app($controller);
      $modelName = class_basename($instance->model());
      $routePath = Str::snake($modelName);

      Route::prefix($routePath)->group(function () use ($instance, $controller) {
        Route::match(['get', 'post'], '', [$controller, 'index']);

        if ($instance->routeUpdate) {
          Route::post('/update', [$controller, 'update']);
        }

        if ($instance->routeDestroy) {
          Route::post('/delete', [$controller, 'destroy']);
        }
      });
    });
  }

  private function registerTranslations() {
    $this->loadJsonTranslationsFrom(self::path('resources/lang/'));
    return $this;
  }

  private static function path(string $path = ''): string {
    $current = dirname(__DIR__, 2);
    return realpath($current.($path ? DIRECTORY_SEPARATOR.$path : $path));
  }

}
