<?php

require_once 'vendor/autoload.php';
require_once 'config/config.php';
require_once 'exception/WeeklyException.php';
require_once 'weekly.php';

$class = $argv[1];
$action = $argv[2];

(new $class)->$action();

use Awesome\Weekly;
use Awesome\Config\MainConfig;

class MainClass
{
    public function weeklyProvide()
    {
        try {
            $weekly = new Weekly([
                'mail_address' => MainConfig::MAIL_QQ_ADDRESS,
                'mail_password' => MainConfig::MAIL_QQ_PASSWORD,
                'mail_name' => MainConfig::MAIL_NAME,
                'mail_host' => MainConfig::MAIL_QQ_SMTP_HOST,
                'mail_port' => MainConfig::MAIL_QQ_PORT,
                'mail_to' => MainConfig::MAIL_TO,
                'jira_base_uri' => MainConfig::JIRA_BASE_URI,
                'oa_username' => MainConfig::OA_USERNAME,
                'oa_password' => MainConfig::OA_PASSWORD,
            ]);

            $weekly->run();
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }
}