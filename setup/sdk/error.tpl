<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /");
    die();
}

$origRedirectUri = array_key_exists('CCS_SPIDPHP_REDIRECT_URI', $_COOKIE) ? $_COOKIE['CCS_SPIDPHP_REDIRECT_URI'] : '';
$isBdr = !empty($origRedirectUri) &&
        (strpos($origRedirectUri, 'bachecadelriutilizzo') !== false || strpos($origRedirectUri, 'localhost:5001') !==
                false);

if(!$isBdr) {
    $redirectUri =
            $origRedirectUri.'?statusCode='.urlencode($_POST['statusCode']).
            '&statusMessage='.urlencode($_POST['statusMessage']).
            '&errorMessage='.urlencode($_POST['errorMessage']);
    $origState = array_key_exists('CCS_SPIDPHP_STATE', $_COOKIE) ? $_COOKIE['CCS_SPIDPHP_STATE'] : '';
    if(!empty($origState)) {
        $redirectUri .= '&state='.urlencode($origState);
    }
    header("Location: ".$redirectUri);
    die();
}

/*
require_once("../proxy-spid-php.php");
$sspSession = \SimpleSAML\Session::getSessionFromRequest();

$authState = $sspSession->getDataOfType('\SimpleSAML\Auth\State');

// Stesso return url del success state, ma con dati di errore
foreach($authState as $state) {
    $stateData = unserialize($state);

    if(is_array($stateData) && array_key_exists('\SimpleSAML\Auth\Source.Return', $stateData)){
        $returnData = $stateData['\SimpleSAML\Auth\Source.Return'];

        if(!empty($returnData)){
            $parsedUrl = parse_url($returnData);

            if(is_array($parsedUrl) && array_key_exists('query', $parsedUrl)){
                $query = $parsedUrl['query'];
                parse_str($query, $queryParts);

                if(is_array($queryParts) && array_key_exists('redirect_uri', $queryParts) && !empty($queryParts['redirect_uri'])){
                    $redirectUri =
                        $queryParts['redirect_uri'].'?statusCode='.urlencode($_POST['statusCode']).
                            '&statusMessage='.urlencode($_POST['statusMessage']).
                            '&errorMessage='.urlencode($_POST['errorMessage']);

                    if(array_key_exists('state', $queryParts)){
                        $redirectUri .= '&state='.urlencode($queryParts['state']);
                    }

                    header("Location: ".$redirectUri);
                    die();
                }

            }

        }
    }

}
*/

//--Rut - 02/02/2024 - se arriva fin qui, niente redirect uri nello state - sicuramente, BDR
$error_data = array(
    'spid-login' => 'error',
    'status' => urlencode($_POST['statusCode']),
    'message' => urlencode($_POST['statusMessage']),
    'error' => urlencode($_POST['errorMessage']),
);

header("Location: https://bachecadelriutilizzo.ccs.to.it?" . http_build_query($error_data), 302)

?>
<!-- <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
</head>
<body>
<h1> Errore durante il processo di autenticazione </h1>
 <div style="border: 1px solid #eee; padding: 1em; margin: 1em 0">
        <p style="margin: 1px">StatusCode: <?php echo htmlspecialchars($_POST['statusCode']); ?></p>
        <p style="margin: 1px">StatusMessage: <?php echo htmlspecialchars($_POST['statusMessage']); ?></p>
        <p style="margin: 1px">ErrorMessage: <?php echo htmlspecialchars($_POST['errorMessage']); ?></p>
 </div>

</body>
</html> -->
