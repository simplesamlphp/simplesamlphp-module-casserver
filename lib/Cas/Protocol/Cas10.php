<?php

class sspmod_sbcasserver_Cas_Protocol_Cas10
{
    public function __construct($config)
    {
    }

    public function getSuccessResponse($username)
    {
        return "yes\n" . $username . "\n";
    }

    public function getFailureResponse($errorCode, $explanation)
    {
        return "no\n";
    }
}

?>