<?php 
use SAML2\Compat\Ssp\Container;
use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Database;
use SimpleSAML\Session;

class CcsContainer extends Container {
    const TABLE_NAME = 'cie_auth_requests';

    /**
     * The PDO object
     */
    private $db;    

    public function __construct() {
        parent::__construct();
        $dbConfig = Configuration::getOptionalConfig('ccsConfig.php');
        $this->db = Database::getInstance($dbConfig);
    }

    public function debugMessage($message, string $type) : void
    {
        $this->utilsXml->debugSAMLMessage($message, $type);        

        if($type == 'in' || $type == 'out'){
            if (!$message instanceof DOMElement) {
                $doc = DOMDocument::loadXML($message);
                $message = $doc->documentElement;
            }            

            /**
             * @var $sspSession Session
             */
            $sspSession = Session::getSessionFromRequest();
            $sessionId = $sspSession->getSessionId();
            $sessionTrackId = $sspSession->getTrackID();
            
            // Log Request
            if($message->tagName == 'samlp:AuthnRequest'){
                $requestId = $message->getAttribute('ID');

                $requestXml = $message->ownerDocument->saveXML($message);
                $requestXml = $this->utilsXml->formatXMLString($requestXml);
    
                //Check se la stessa request è già stata loggata (può capitare ma va scartata in tal caso)
                if (!$this->_isAuthRequestRecorded($requestId)) {
                    //TODO verifica se c'è un riferimento ad una request da una response già arrivata prima
                    /**
                     * @see setup\simplesamlphp\saml2\src\SAML2\Message.php
                     */
                    $stateId = str_replace('spid-php', '', $requestId);
                    $idp = $this->_getIdp($stateId);
                    $auth_type = $this->_getAuthTypeFromIdp($idp);

                    $issueInstant = $message->getAttribute('IssueInstant');
                    $authnInstantTime = new DateTime($issueInstant);

                    $sessionRedirectUrl = $this->_getStateReturnURLParam($stateId, 'redirect_uri');

                    $params = [
                        'auth_type' => $auth_type,
                        'idp' => $idp,
                        'authn_request' => $requestXml,
                        'authn_request_id' => $requestId,
                        'issue_instant' => $issueInstant,
                        'authn_instant_time' => $authnInstantTime->format('Y-m-d H:i:s'),
                        'session_id' => $sessionId,
                        'session_track_id' => $sessionTrackId,
                        'session_redirect_url' => $sessionRedirectUrl,
                    ];
                    $insertQ = 
                        "INSERT INTO ".self::TABLE_NAME." (AuthType, IDP, AuthnRequest, AuthnReq_ID, AuthnReq_IssueInstant, AuthnInstant, SamlSessionID, SamlSessionTrackID, SamlSessionRedirectURL) ".
                        "VALUES (:auth_type, :idp, :authn_request, :authn_request_id, :issue_instant, :authn_instant_time, :session_id, :session_track_id, :session_redirect_url)";

                    $rows = $this->db->write($insertQ, $params);

                    if($rows < 1) {
                        throw new \Exception(
                            'PDO CcsContainer: Database error: ' . var_export($this->db->getLastError(), true)
                        );
                    }
                } 
            } else if($message->tagName == 'saml2p:Response') {
                //Per collegarsi alla request
                $requestId = $message->getAttribute('InResponseTo');
                $responseId = $message->getAttribute('ID');

                $stateId = str_replace('spid-php', '', $requestId);
                $idp = $this->_getIdp($stateId);
                $auth_type = $this->_getAuthTypeFromIdp($idp);
                $responseXml = $message->ownerDocument->saveXML($message);
                $responseXml = $this->utilsXml->formatXMLString($responseXml);

                $issueInstant = $message->getAttribute('IssueInstant');
                $respInstantTime = new DateTime($issueInstant);

                $doc = new DOMDocument();
                $doc->loadXML($responseXml);
                $xpath = new DOMXPath($doc);
                $xpath->registerNamespace("saml2", "urn:oasis:names:tc:SAML:2.0:assertion");
                $issuers = $xpath->query('//saml2p:Response/saml2:Issuer');
                $issuer = '';
                if($issuers->length > 0) {
                    $issuer = $issuers->item(0);
                    $issuer = $issuer->nodeValue;
                }
                $assertionId = '';
                $assertionSubject = '';
                $assertionSubjectNameQualifier = '';
                $fiscalNumber = '';
                $assertions = $xpath->query('//saml2p:Response/saml2:Assertion');
                if($assertions->length > 0) {
                    $assertion = $assertions->item(0);
                    $assertionId = $assertion->getAttribute('ID');
                    foreach ($assertion->childNodes as $childNode) { 
                        if($childNode->nodeName == 'saml2:Subject') {
                            $assertionSubject = $doc->saveXML($childNode);

                            foreach($childNode->childNodes as $subChildNode) {
                                if($subChildNode->nodeName == 'saml2:NameID') {
                                    $assertionSubjectNameQualifier = $subChildNode->attributes->getNamedItem('NameQualifier')->nodeValue;
                                    break;
                                }                                
                            }

                        } else if($childNode->nodeName == 'saml2:AttributeStatement') {
                            foreach($childNode->childNodes as $subChildNode) {
                                if($subChildNode->nodeName == 'saml2:Attribute') {
                                    $attributeName = $subChildNode->attributes->getNamedItem('Name')->nodeValue;                                    
                                    if($attributeName == 'fiscalNumber') {
                                        foreach($subChildNode->childNodes as $subSubChildNode) {
                                            if($subSubChildNode->nodeName == 'saml2:AttributeValue') {
                                                $fiscalNumber = $subSubChildNode->nodeValue;
                                                break;
                                            }                                            
                                        }                                        
                                        break;
                                    }
                                } 
                            }
                        }

                    }

                }

                $stateId = str_replace('spid-php', '', $requestId);
                $sessionRedirectUrl = $this->_getStateReturnURLParam($stateId, 'redirect_uri');

                $params = [
                    'request_id' => $requestId,
                    'response' => $responseXml,
                    'response_id' => $responseId,
                    'issue_instant' => $issueInstant,
                    'resp_instant_time' => $respInstantTime->format('Y-m-d H:i:s'),
                    'resp_issuer' => $issuer,
                    'assertion_id' => $assertionId,
                    'assertion_subject' => $assertionSubject,
                    'assertion_subject_name_qualifier' => $assertionSubjectNameQualifier,
                    'session_id' => $sessionId,
                    'session_track_id' => $sessionTrackId,
                    'session_redirect_url' => $sessionRedirectUrl,
                    'fiscal_number' => $fiscalNumber
                ];

                $insertOrUpdateQ = "";
                $isInsert = false;

                if ($this->_isAuthRequestRecorded($requestId)) {
                    $insertOrUpdateQ = 
                        "UPDATE ".self::TABLE_NAME.
                        " SET Response=:response, Resp_ID=:response_id, Resp_IssueInstant=:issue_instant, RespInstant=:resp_instant_time, Resp_Issuer=:resp_issuer, Assertion_ID=:assertion_id, Assertion_subject=:assertion_subject, Assertion_subject_NameQualifier=:assertion_subject_name_qualifier, SamlSessionID=:session_id, SamlSessionTrackID=:session_track_id, SamlSessionRedirectURL=:session_redirect_url, FiscalNumber=:fiscal_number ".
                        "WHERE AuthnReq_ID=:request_id";                    
                } else {
                    $params['auth_type'] = $auth_type;
                    $params['idp'] = $idp;
                    // Non c'è ancora il recorda request - forse arriverà in seguito - intanto si inserisce la response
                    $insertOrUpdateQ = 
                        "INSERT INTO ".self::TABLE_NAME." (AuthType, IDP, AuthnReq_ID, Response, Resp_ID, Resp_IssueInstant, RespInstant, Resp_Issuer, Assertion_ID, Assertion_subject, Assertion_subject_NameQualifier, SamlSessionID, SamlSessionTrackID, SamlSessionRedirectURL, FiscalNumber) ".
                        "VALUES (:auth_type, :idp, :request_id, :response, :response_id, :issue_instant, :resp_instant_time, :resp_issuer, :assertion_id, :assertion_subject, :assertion_subject_name_qualifier, :session_id, :session_track_id, :session_redirect_url, :fiscal_number)";
                    $isInsert = true;
                }

                // echo $insertOrUpdateQ;
                // echo '<br><br>';
                $rows = $this->db->write($insertOrUpdateQ, $params);

                if($rows < 1 && $isInsert) {
                    throw new \Exception(
                        'PDO CcsContainer: Database error: ' . var_export($this->db->getLastError(), true)
                    );
                }

                // echo "UPDATED: ".$rows." - insert? ";
                // var_dump($isInsert);
                
                // exit;
            }
            
            
        }

    }

    /**
     * Check da database se esiste un record con questa authRequest
     * @* @param string $authRequestId attributo 'ID' del messaggio SAML 'samlp:AuthnRequest'
     */
    private function _isAuthRequestRecorded($authRequestId) {
        $sameRequest = $this->db->read(
            "SELECT AuthnReq_ID FROM ".self::TABLE_NAME." WHERE AuthnReq_ID = :auth_id",
            [
                'auth_id' => $authRequestId,
            ]
        );

        $retrivedAuthIDs = $sameRequest->fetch();
        return $retrivedAuthIDs !== false && count($retrivedAuthIDs) > 0;
    }

    /**
     * Calcolo IDP da: relay state - oppure dalla query - oppure dalla sessione
     * @* @param string $stateId attributo ID dell'elemento samlp:AuthnRequest 'spogliato' del prefisso 'spid-php'
     */
    private function _getIdp($stateId) {
        if(array_key_exists('idp', $_REQUEST) && !empty($_REQUEST['idp'])) {
            return $_REQUEST['idp'];
        }

        if(array_key_exists('RelayState', $_REQUEST) && !empty($_REQUEST['RelayState'])) {
            $relayState = $_REQUEST['RelayState'];

            $globalConfig = Configuration::getInstance();
            $secretSalt = $globalConfig->getString('secretsalt');

            $encryptedRelayState = $_POST['RelayState'];

            $ivSize = openssl_cipher_iv_length("aes-256-cbc");
            $decodedData = base64_decode($encryptedRelayState);
            $iv = substr($decodedData, 0, $ivSize);

            $relayStateStr = openssl_decrypt(substr($decodedData, $ivSize), "aes-256-cbc", $secretSalt, 0, $iv);

            //https://ciedev.ccs.to.it/proxy.php?client_id=6491a3356ea6f&action=login&redirect_uri=https://www.ccs.to.it/serviziwebdev/auth/cie-landing&idp=CIE%20TEST&state=sportello_online
            $relayStateQuery = parse_url(relayStateStr, PHP_URL_QUERY);
            if($relayStateQuery !== false) {
                parse_str($relayStateQuery, $queryParts);
                if(is_array($queryParts) && array_key_exists('idp', $queryParts) && !empty($queryParts['idp'])) {
                    return $queryParts['idp'];
                } 
            }
        }

        // Non c'è relayState - si tenta da authState in base a id request        
        return $this->_getStateReturnURLParam($stateId, 'idp');
        
    }

    /**
     * Calcolo parametro del ReturnUrl da state salvato in sessione
     * @* @param string $stateId attributo ID dell'elemento samlp:AuthnRequest 'spogliato' del prefisso 'spid-php'
     */
    private function _getStateReturnURLParam($stateId, $param) {
        $state = State::loadState($stateId, 'saml:sp:sso', true);
        $output = '';
        $sessionReturnUrlQuery = parse_url($state['\SimpleSAML\Auth\Source.ReturnURL'], PHP_URL_QUERY);
        // var_dump($stateId);exit;
        if($sessionReturnUrlQuery !== false) {
            parse_str($sessionReturnUrlQuery, $queryParts);
            if(is_array($queryParts) && array_key_exists($param, $queryParts)) {
                $output = $queryParts[$param];
            } 
        }

        return $output;
    }

    private function _getAuthTypeFromIdp($idp) {
        $isCIE = ($idp=="CIE" || $idp=="CIE TEST");
        return $isCIE? "CIE" : "SPID";
    }
}

?>