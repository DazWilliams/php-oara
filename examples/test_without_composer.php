<?php
include_once (dirname(__FILE__) . '/Oara/settings_without_composer.php');

function __autoload($class_name) {
  $class_name = str_replace("\\","/",$class_name);
  require_once dirname(__FILE__) .'/'. $class_name . '.php';
}

$network = new \Oara\Network\Publisher\Amazon;
$credentialsNeeded = $network->getNeededCredentials();
$credentials = array();
$credentials["user"] = "username";
$credentials["password"] = "passwd";
$network->login($credentials);
if ($network->checkConnection()){
    $merchantList = $network->getMerchantList();
    $startDate = new \DateTime('2017-01-01');
    $endDate = new \DateTime('2017-01-31');
    $transactionList = $network->getTransactionList($merchantList, $startDate, $endDate);
    echo '<pre>';
    print_r($transactionList);
    echo '</pre>';

} else {
    echo "Network credentials not valid \n";
}
