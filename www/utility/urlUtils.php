<?php
function checkServiceURL($service, array $legal_service_urls)
{
    foreach ($legal_service_urls AS $legalurl) {
        if (strpos($service, $legalurl) === 0) return TRUE;
    }

    return FALSE;
}

function sanitize($parameter)
{
    return preg_replace('/;jsessionid=.*/', '', $parameter);
}

?>