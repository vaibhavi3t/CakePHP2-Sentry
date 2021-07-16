<?php
/**
 * Error/Exception handlers to send data to Sentry
 */

App::uses('ClassRegistry', 'Utility');
App::import('Vendor', 'Sentry.sentry/lib/Raven/Autoloader');

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
    private static function sentryCapture($exception) {
        try {
            Raven_Autoloader::register();
            // Instantiate the client if it hasn't already been created
            if (self::$_client === null) {
                $options = Configure::read('Sentry.options');
                if (empty($options)) $options = array();
                self::$_client = new Raven_Client(Configure::read('Sentry.DSN.PHP'), $options);
            }
            self::setUserContext();
            $event = self::$_client->captureException($exception, null, 'PHP', null);
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

        $id = null;
        $ip = Configure::read('Sentry.user.ip');
        $extraUserData = array(
                        'ip' => $ip,
                    );
        $email = null;
        self::$_client->set_user_data($id, $email, $extraUserData);
    }

    /**
     * @see ErrorHandler::handleError
     */
    public static function handleError($code, $description, $file = null,
                                       $line = null, $context = null) {
        $severity = (Configure::read('Sentry.treatErrorAsWarning') === true) ? E_WARNING : 1;
        $e = new ErrorException($description, $code, $severity, $file, $line);
        // Check that Sentry has captured the error
        if (self::sentryCapture($e))
            return parent::handleError($code, $description, $file, $line, $context);
        return true;
    }

    /**
     * @see ErrorHandler::handleException
     */
    public static function handleException($exception) {
        // Check if the exception is not in the `ignoredExceptions` array
        $ignoredExceptions = Configure::read('Sentry.ignoredExceptions');
        if (!$ignoredExceptions) $ignoredExceptions = array();
        $className = get_class($exception);
        $eventId = true;
        if (!in_array($className, $ignoredExceptions)) {
            $eventId = self::sentryCapture($exception);
        }
        if ($eventId !== null)
            parent::handleException($exception);
    }
}
