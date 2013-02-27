<?php

class sspmod_sbcasserver_Cas_Protocol_Cas10
{
    public function __construct($config)
    {
    }

    public function getValidateSuccessResponse($username)
    {
        return "yes\n" . $username . "\n";
    }

    public function getValidateFailureResponse($errorCode, $explanation)
    {
        return "no\n";
    }
}

?>