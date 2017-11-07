CakePHP-Sentry
--------------

A CakePHP plugin to send errors/exceptions/logs to Sentry (getsentry.com) https://www.getsentry.com

Installation
------------

1. Clone the files either into `app/Plugin/Sentry`or `private/plugins` (remember to also get the PHP Sentry submodule - `gitsubmodule init` then `git submodule update`)
2. Load the plugin in `app/Config/bootstrap.php` by calling `CakePlugin::load('Sentry');`
3. Setup the plugin as the Error/Exception handler in `app/Config/core.php`
```php
	App::uses('SentryErrorHandler', 'Sentry.Lib');

  Configure::write('Error', array(
    'handler' => 'SentryErrorHandler::handleError',
    'level' => E_ALL & ~E_DEPRECATED,
    'trace' => true
  ));

  Configure::write('Exception', array(
    'handler' => 'SentryErrorHandler::handleException',
    'renderer' => 'ExceptionRenderer',
    'log' => true
  ));
```

4. Place your settings in `app/Config/core.php`
```php
    Configure::write('Sentry', array(
        'DSN' => array(
            // Your private Sentry DSN
            'PHP' => 'http://123456@sentry.com/1'
        ),
        // Configuration for the Sentry user interface - will display a CakePHP logged in user
        'user' => array(
            // Change the model used for the CakePHP user
            'model' => 'CustomUser',
            // Map the fields sent by Sentry to custom fields on the user model
            'fieldMapping' => array(
                'username' => 'custom_username',
                'email' => 'custom_email'
            )
        ),
        'ignoredExceptions' => array('NotFoundException'),
        // Treat any Errors captured in Sentry as warnings
        'treatErrorAsWarning' => true
    ));
```
