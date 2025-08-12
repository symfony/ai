<?php

namespace App\Utils;

class EnvValidator
{
    public static function validateAwsCredentials(): void
    {
        if (!isset($_SERVER['AWS_ACCESS_KEY_ID'], $_SERVER['AWS_SECRET_ACCESS_KEY'], $_SERVER['AWS_DEFAULT_REGION'])) {
            echo 'Please set the AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY and AWS_DEFAULT_REGION environment variables.' . \PHP_EOL;
            exit(1);
        }
    }
}
