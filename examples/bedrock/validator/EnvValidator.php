<?php


namespace validator;
class EnvValidator
{
    public static function validateAwsCredentials(): void
    {
        env('AWS_ACCESS_KEY_ID');
        env('AWS_SECRET_ACCESS_KEY');
        env('AWS_DEFAULT_REGION');
    }
}
