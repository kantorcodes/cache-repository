<?php namespace Drapor\CacheRepository;

use Illuminate\Support\ServiceProvider;

class CacheRepositoryServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
	
	}

	public function boot()
	{
		$configPath = __DIR__.'/config/cacherepository.php';

		$this->publishes([$configPath => config_path('cacherepository.php')], 'config');
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return ['cacherepository'];
	}

}
