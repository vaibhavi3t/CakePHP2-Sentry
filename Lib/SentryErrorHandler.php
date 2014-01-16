<?php
/**
 * Error/Exception handlers to send data to Sentry
 */

App::uses('ClassRegistry', 'Utility');
App::import('Vendor', 'Sentry.raven/lib/Raven/Autoloader');

/**
 * Provides error/exception handling that logs to Sentry. There is a
 * fallback to the default ErrorHandler implementation if anything goes
 * wrong when sending data to the Sentry server.
 *
 * @package Sentry
 * @extends ErrorHandler
 */
class SentryErrorHandler extends ErrorHandler {

    /**
     * Raven client used for sending information to Sentry
     *
     * @var Raven_Client
     */
    private static $_client = null;

    /**
     * Send the exception to Sentry
     *
     * @param Exception $exception
     * @return String|null Event id for the Sentry event
     */
    private static function sentryCapture(Exception $exception) {
        try {
            Raven_Autoloader::register();
            // Instantiate the client if it hasn't already been created
            if (self::$_client === null) {
                $options = Configure::read('Sentry.options');
                if (empty($options)) $options = array();
                self::$_client = new Raven_Client(Configure::read('Sentry.DSN.PHP'), $options);
            }
            self::setUserContext();
            $event = self::$_client->captureException($exception, get_class($exception), 'PHP');
            return self::$_client->getIdent($event);
        } catch (Exception $e) {
            parent::handleException($e);
        }
        return null;
    }

    /**
     * Set the user context for the Raven client
     */
    private static function setUserContext() {
        // Clear the user context
        self::$_client->context->user = null;

        // Check if the `AuthComponent` is in use for current request
        if (class_exists('AuthComponent')) {
            // Instantiate the user model to get valid field names
            $modelName = Configure::read('Sentry.user.model');
            $user = ClassRegistry::init((empty($modelName)) ? 'User' : $modelName);

            // Check if the user is authenticated
            $id = AuthComponent::user($user->primaryKey);
            if ($id) {
                // Check custom username field (defaults to `displayField` on `User` model)
                $usernameField = Configure::read('Sentry.user.fieldMapping.username');
                if (empty($usernameField)) $usernameField = $user->displayField;
                $extraUserData = array(
                    'username' => AuthComponent::user($usernameField)
                );

                // Get user emails
                $emailField = Configure::read('Sentry.user.fieldMapping.email');
                $email = (!empty($emailField)) ? AuthComponent::user($emailField) : null;

                // Set the user context
                self::$_client->set_user_data($id, $email, $extraUserData);
            }
        }
    }

    /**
     * @see ErrorHandler::handleError
     */
    public static function handleError($code, $description, $file = null,
                                       $line = null, $context = null) {
        $severity = (Configure::read('Sentry.treatErrorAsWarning') === true) ? E_WARNING : 1;
        $e = new ErrorException($description, $code, $severity, $file, $line);;
        self::sentryCapture($e);
        return parent::handleError($code, $description, $file, $line, $context);
    }

    /**
     * @see ErrorHandler::handleException
     */
    public static function handleException(Exception $exception) {
        // Check if the exception is not in the `ignoredExceptions` array
        $ignoredExceptions = Configure::read('Sentry::ignoredExceptions');
        if (!$ignoredExceptions) $ignoredExceptions = array();
        $className = get_class($exception);
        if (!in_array($className, $ignoredExceptions)) {
            self::sentryCapture($exception);
            parent::handleException($exception);
        }
    }
}