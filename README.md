# PHP-PM HttpKernel Adapter

HttpKernel adapter for use of Symfony and Laravel frameworks with PHP-PM. See https://github.com/php-pm/php-pm.

### Setup

  1. Install PHP-PM

          composer require php-pm/php-pm:dev-master

  2. Install HttpKernel Adapter

          composer require php-pm/httpkernel-adapter:dev-master

**Symfony 4**:

Make sure `httpkernel` can access the Symfony session service. Add this to `services.yml`:

	session.handler:
	    public: true

**Earlier Symfony versions**:

Make sure your `AppKernel` is autoloaded in your `composer.json`:

	{
	    "autoload": {
	        "classmap": ["app/AppKernel.php"]
	    }
	}
