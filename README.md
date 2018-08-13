# PHP-PM HttpKernel Adapter

HttpKernel adapter for use of Symfony and Laravel frameworks with PHP-PM. See https://github.com/php-pm/php-pm.

### Setup

  1. Install PHP-PM

          composer require php-pm/php-pm

  2. Install HttpKernel Adapter

          composer require php-pm/httpkernel-adapter

  3. Optionally provide the namespace of your Kernel using the `APP_KERNEL_NAMESPACE` environment variable. 
  Example: `APP_KERNEL_NAMESPACE=Acme\MyProduct\`. This will attempt to use the class `Acme\MyProduct\Kernel` 
  as the fully qualified class name

> **Note**: For Symfony, make sure your `AppKernel` is autoloaded in your
> `composer.json` (shouldn't be an issue for projects created using the Standard
> Edition after November 2015):
>
> ```
> {
>     "autoload": {
>         "classmap": ["app/AppKernel.php"]
>     }
> }
> ```
