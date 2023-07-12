<?php 
namespace SPID_PHP;

use Composer\Script\Event;
use SPID_PHP\Colors;
use SPID_PHP\Setup;
use Symfony\Component\Filesystem\Filesystem;

// readline replacement
if (!function_exists('readline')) {
  function readline() {
    $fp = fopen("php://stdin", "r");
    $line = rtrim(fgets($fp, 1024));
    return $line;
  }
}

class Ccs extends Setup {
    public static function setup(Event $event) {
        $filesystem = new Filesystem();
        $colors = new Colors();
        $version = $event->getComposer()->getConfig()->get("version");

        if ($colors->hasColorSupport()) {
            // Clear the screen
            echo "\e[H\e[J";
        }

        echo $colors->getColoredString("SPID PHP SDK CCS-Setup\nversion " . $version . "\n\n", "green");

        echo $colors->getColoredString("\nWrite patched version of simplesamlphp module.php file for customized logs... ", "white");
        $config = file_exists("spid-php-setup.json") ?
                json_decode(file_get_contents("spid-php-setup.json"), true) : array();

        // configuration for proxy (variabili come Sdk home)
        $vars = self::proxyVariables($config);
        $template = file_get_contents($config['installDir'] . '/setup/sdk/module.tpl', true);
        $customized = str_replace(array_keys($vars), $vars, $template);

        file_put_contents($config['installDir'] .
            "/vendor/simplesamlphp/simplesamlphp/www/module.php", $customized);

        $ccsCconfig = file_exists("spid-php-setup-ccs.json") ?
        json_decode(file_get_contents("spid-php-setup-ccs.json"), true) : array();

        if (!isset($ccsCconfig['ccsTrackingDb'])) {
            $defaultCcsDb = 'mysql:host=localhost;dbname=cie_tracking';
            echo "Please insert dns connection for authentication tracking database (" .
            $colors->getColoredString($defaultCcsDb, "green") . "): ";
            $ccsCconfig['ccsTrackingDb'] = readline();
            if ($ccsCconfig['ccsTrackingDb'] == null || $ccsCconfig['ccsTrackingDb'] == "") {
                $ccsCconfig['ccsTrackingDb'] = $defaultCcsDb;
            }

            $defaultCcsDbUsername = 'cie_tracking';
            echo "Please insert user for authentication tracking database (" .
            $colors->getColoredString($defaultCcsDbUsername, "green") . "): ";
            $ccsCconfig['ccsTrackingDbUsername'] = readline();
            if ($ccsCconfig['ccsTrackingDbUsername'] == null || $ccsCconfig['ccsTrackingDbUsername'] == "") {
                $ccsCconfig['ccsTrackingDbUsername'] = $defaultCcsDbUsername;
            }
            
            $defaultCcsDbPassword = 'cie_tracking';
            echo "Please insert password for authentication tracking database (" .
            $colors->getColoredString($defaultCcsDbPassword, "green") . "): ";
            $ccsCconfig['ccsTrackingDbPassword'] = readline();
            if ($ccsCconfig['ccsTrackingDbPassword'] == null || $ccsCconfig['ccsTrackingDbPassword'] == "") {
                $ccsCconfig['ccsTrackingDbPassword'] = $defaultCcsDbPassword;
            }

        }

        // customize and copy config file
        echo $colors->getColoredString("\nWrite CCS config file... ", "white");
        $vars = array(
            "{{TRACKINGDBDNS}}" => "'" . $ccsCconfig['ccsTrackingDb'] . "'",
            "{{TRACKINGDBUSERNAME}}" => "'" . $ccsCconfig['ccsTrackingDbUsername'] . "'",
            "{{TRACKINGDBPASSWORD}}" => "'" . $ccsCconfig['ccsTrackingDbPassword'] . "'"
        );
        $template = file_get_contents($config['installDir'] . '/setup/config/ccs_config.tpl', true);
        $customized = str_replace(array_keys($vars), $vars, $template);
        file_put_contents($config['installDir'] .
                "/vendor/simplesamlphp/simplesamlphp/config/ccsConfig.php", $customized);

        file_put_contents("spid-php-setup-ccs.json", json_encode($ccsCconfig));


        //TODO - ask 4 db connection
        

        echo $colors->getColoredString("OK", "green");
        echo "\n\n";
    }

    /**
     * @param $config
     * @return array
     */
    private static function proxyVariables($config): array {
        return array(
            "{{SDKHOME}}" => $config['installDir'],
            "{{PROXY_CLIENT_CONFIG}}" => var_export($config['proxyConfig'], true),
            "{{PROXY_CLIENT_ID}}" => array_keys($config['proxyConfig']['clients'])[0],
            "{{PROXY_REDIRECT_URI}}" => $config['proxyConfig']['clients'][array_keys($config['proxyConfig']['clients'])[0]]['redirect_uri'][0],
            "{{PROXY_SIGN_RESPONSE}}" => $config['proxyConfig']['signProxyResponse'],
            "{{PROXY_ENCRYPT_RESPONSE}}" => $config['proxyConfig']['encryptProxyResponse']
        );
    }
}
?>