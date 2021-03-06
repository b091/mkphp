<?php

/**
 * MK
 *
 * @category MK
 * @package    MK
 * @author    bskrzypkowiak
 */
class MK
{

    /**
     * Autoloader dla MKPhp
     *
     * @param String $className
     *
     * @return Boolean
     */
    public static function _autoload($className)
    {

        if(substr($className, 0, 3) == 'MK_') { //todo tu jest błąd bo nie załaduje MK::someMethod(); :)
            include (substr(MK_PATH, 0, -3) . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php');
            return true;
        }
        return false;
    }

    /**
     * Funkcja wywoływana na zakończenie skryptu PHP
     * w przypadku gdy skrypt kończy się błędem uruchamia funkcje powiadamiajaca o błędzie
     */
    public static function shutdownFunction()
    {
        $error = error_get_last();
        if(!empty($error)) {
            MK_Error::handler(isset($error['type']) ? $error['type'] : null, isset($error['message']) ? $error['message'] : null, isset($error['file']) ? $error['file'] : null, isset($error['line']) ? $error['line'] : null);
        }
    }

    /**
     * Uruchamia kontroler konsoli i wywołuje z niego metode z parametrami
     *
     * @param Array        $argv - (default:array())Tablica z parametrami przekazanymi w lini polecen
     *
     * @return Boolean
     */
    public static function executeCLICommand(array $argv)
    {
        if(empty($argv)) {
            return;
        }
        if(version_compare(PHP_VERSION, '5.3.0', '>=') && APP_NAME !== 'spirb'  && APP_NAME !== 'docflow') { // Tymczasowy "myk" dla SPiRB-a z APP_NAME
            $consoleController = strtolower(APP_NAME) . '\controller\Console';
        } else {
            $consoleController = ucfirst(APP_NAME) . '_Controller_Console';
        }

        if(class_exists($consoleController)) {
            $consoleController = new $consoleController();
        } elseif(class_exists('ConsoleController')) {
            /** @noinspection PhpUndefinedClassInspection */
            $consoleController = new ConsoleController();
        } else {
            $consoleController = new MK_Controller_Console();
        }

        if(count($argv) >= 2) {
            $optArgv = array_slice($argv, 2);
        } else {
            $optArgv = array ();
        }

        $optArray = getopt('m::');
        if(empty($optArray)) {
            return;
        }

        foreach ($optArray as $optKey => $optValue) {
            switch ($optKey) {
                case 'm':
                    if(is_array($optValue)) {
                        foreach ($optValue as $value) {
                            if(!method_exists($consoleController, $value)) {
                                exit("Brak funkcji {$value}\n");
                            }
                            $consoleController->{$value}($optArgv);
                        }
                    } else {
                        if(!method_exists($consoleController, $optValue)) {
                            exit("Brak funkcji {$optValue}\n");
                        }
                        $consoleController->{$optValue}($optArgv);
                    }
                    break;
            }
        }
        exit;
    }

    /**
     * Sprawdza czy rządanie jest wysłane za pomocą XMLHttpRequest (AJAX)
     * w przypadku ajax'a zwraca true
     * w pozostałych przypadkach false
     *
     * @param Boolean    $sendHeaders (default:false) - jezeli true to wyśle nagłówki  dla typu JSON i z wyłączonym caschowaniem
     *
     * @return Boolean
     */
    public static function isAjaxExecution($sendHeaders = false)
    {

        if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            if($sendHeaders) {
                MK::sendJSONHeaders();
            }
            return true;
        }
        return false;
    }

    /**
     *  Sprawdza czy istnieje plik blokujacy uzycie aplikacji
     */
    public static function checkApplicationState()
    {
        if(file_exists(APP_FILE_LOCK)) {
            if(strpos(file_get_contents(APP_FILE_LOCK), 'upgrade') !== false) {
                $msg = '<h1>Przerwa techniczna</h1><br/>Proszę spróbować za 10 minut.<br/>Proszę nie wyłączać i nie restartować serwera.';
                if(self::isAjaxExecution(true)) {
                    echo json_decode(array ("success" => false, "msg" => $msg));
                } else {
                    if(file_exists(MK_DIR_VIEW_ERRORS . DIRECTORY_SEPARATOR . 'upgrade.html')) {
                        include(MK_DIR_VIEW_ERRORS . DIRECTORY_SEPARATOR . 'upgrade.html');
                    } else {
                        header("Content-type: text/html; charset=utf-8");
                        echo $msg;
                    }
                }
                die;
            }
        }
    }

    /**
     * Tworzy nagłówki JSON, i ustawia brak cashowania (do obsługi zapytan typu XHR)
     */
    public static function sendJSONHeaders()
    {
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 10) . ' GMT');
        header('Content-type: application/json');
    }

}