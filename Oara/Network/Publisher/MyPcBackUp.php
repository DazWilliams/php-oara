<?php
namespace Oara\Network\Publisher;
    /**
     * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
     * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
     *
     * Copyright (C) 2016  Fubra Limited
     * This program is free software: you can redistribute it and/or modify
     * it under the terms of the GNU Affero General Public License as published by
     * the Free Software Foundation, either version 3 of the License, or any later version.
     * This program is distributed in the hope that it will be useful,
     * but WITHOUT ANY WARRANTY; without even the implied warranty of
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     * GNU Affero General Public License for more details.
     * You should have received a copy of the GNU Affero General Public License
     * along with this program.  If not, see <http://www.gnu.org/licenses/>.
     *
     * Contact
     * ------------
     * Fubra Limited <support@fubra.com> , +44 (0)1252 367 200
     **/
/**
 * Export Class
 *
 * @author     Carlos Morillo Merino
 * @category   MyPcBackUP
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class MyPcBackUP extends \Oara\Network
{

    private $_credentials = null;
    /**
     * Client
     * @var unknown_type
     */
    private $_client = null;

    /**
     * Constructor and Login
     * @param $credentials
     * @return PureVPN
     */
    public function login($credentials)
    {
        $this->_credentials = $credentials;
        self::logIn();

    }

    private function logIn()
    {

        $valuesLogin = array(
            new \Oara\Curl\Parameter('username', $this->_credentials['user']),
            new \Oara\Curl\Parameter('password', $this->_credentials['password']),
            new \Oara\Curl\Parameter('login', 'Login'),
        );

        $loginUrl = 'http://affiliates.mypcbackup.com/login';
        $this->_client = new \Oara\Curl\Access($loginUrl, $valuesLogin, $this->_credentials);

    }

    /**
     * Check the connection
     */
    public function checkConnection()
    {
        //If not login properly the construct launch an exception
        $connection = true;
        $urls = array();
        $urls[] = new \Oara\Curl\Request('http://affiliates.mypcbackup.com/', array());

        $exportReport = $this->_client->get($urls);
        if (!preg_match("/logout/", $exportReport[0])) {
            $connection = false;
        }
        return $connection;
    }

    /**
     * @return array
     */
    public function getNeededCredentials()
    {
        $credentials = array();

        $parameter = array();
        $parameter["user"]["description"] = "User Log in";
        $parameter["user"]["required"] = true;
        $credentials[] = $parameter;

        $parameter = array();
        $parameter["password"]["description"] = "Password to Log in";
        $parameter["password"]["required"] = true;
        $credentials[] = $parameter;

        return $credentials;
    }

    /**
     * (non-PHPdoc)
     * @see library/Oara/Network/Interface#getMerchantList()
     */
    public function getMerchantList()
    {
        $merchants = array();

        $obj = array();
        $obj['cid'] = "1";
        $obj['name'] = "MyPcBackUp";
        $merchants[] = $obj;

        return $merchants;
    }

    /**
     * (non-PHPdoc)
     * @see library/Oara/Network/Interface#getTransactionList($aMerchantIds, $dStartDate, $dEndDate, $sTransactionStatus)
     */
    public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
    {
        $totalTransactions = array();

        $urls = array();
        $valuesFromExport = array();
        $valuesFromExport[] = new \Oara\Curl\Parameter('hop_id', "0");
        $valuesFromExport[] = new \Oara\Curl\Parameter('transaction_id', "");
        $valuesFromExport[] = new \Oara\Curl\Parameter('sales', "1");
        $valuesFromExport[] = new \Oara\Curl\Parameter('refunds', "1");
        $valuesFromExport[] = new \Oara\Curl\Parameter('csv', "Download CSV");
        $valuesFromExport[] = new \Oara\Curl\Parameter('start', $dStartDate->toString("MM/dd/yyyy"));
        $valuesFromExport[] = new \Oara\Curl\Parameter('end', $dEndDate->toString("MM/dd/yyyy"));

        $urls[] = new \Oara\Curl\Request('http://affiliates.mypcbackup.com/transactions?', $valuesFromExport);
        $exportReport = $this->_client->get($urls);
        $exportData = str_getcsv($exportReport[0], "\n");
        $num = count($exportData);
        for ($i = 1; $i < $num; $i++) {
            $transactionExportArray = str_getcsv($exportData[$i], ",");
            $transaction = Array();
            $transaction['merchantId'] = 1;
            $transaction['uniqueId'] = $transactionExportArray[2];
            $transactionDate = new \DateTime($transactionExportArray[0] . " " . $transactionExportArray[1], 'yyyy-MM-dd HH:mm:ss', 'en');
            $transaction['date'] = $transactionDate->toString("yyyy-MM-dd HH:mm:ss");
            unset($transactionDate);

            if (preg_match('/[-+]?[0-9]*\.?[0-9]+/', $transactionExportArray[5], $match)) {
                $transaction['amount'] = (double)$match[0];
            }
            if (preg_match('/[-+]?[0-9]*\.?[0-9]+/', $transactionExportArray[5], $match)) {
                $transaction['commission'] = (double)$match[0];
            }
            if ($transactionExportArray[4] == "Sale") {
                $transaction['status'] = \Oara\Utilities::STATUS_CONFIRMED;
            } else if ($transactionExportArray[4] == "Refund") {
                $transaction['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                $transaction['amount'] = -$transaction['amount'];
                $transaction['commission'] = -$transaction['commission'];
            }
            if ($transactionExportArray[7] != null) {
                $transaction['customId'] = $transactionExportArray[7];
            }
            $totalTransactions[] = $transaction;


        }

        return $totalTransactions;
    }

    /**
     * (non-PHPdoc)
     * @see Oara/Network/Base#getPaymentHistory()
     */
    public function getPaymentHistory()
    {
        $paymentHistory = array();

        $urls = array();
        $urls[] = new \Oara\Curl\Request('http://affiliates.mypcbackup.com/paychecks', array());
        $exportReport = $this->_client->get($urls);
        $dom = new Zend_Dom_Query($exportReport[0]);
        $tableList = $dom->query('.transtable');
        if ($tableList->current() != null) {
            $exportData = self::htmlToCsv(self::DOMinnerHTML($tableList->current()));
            $num = count($exportData);
            for ($i = 1; $i < $num; $i++) {
                $paymentExportArray = str_getcsv($exportData[$i], ";");
                try {
                    $obj = array();
                    $date = new \DateTime($paymentExportArray[14], "MM/dd/yyyy");
                    $obj['date'] = $date->toString("yyyy-MM-dd HH:mm:ss");
                    $obj['pid'] = preg_replace('/[^0-9\.,]/', "", $paymentExportArray[14]);
                    $obj['method'] = $paymentExportArray[16];
                    $value = preg_replace('/[^0-9\.,]/', "", $paymentExportArray[12]);

                    $obj['value'] = \Oara\Utilities::parseDouble($value);
                    $paymentHistory[] = $obj;
                } catch (Exception $e) {
                    echo "Payment failed\n";
                }

            }
        }
        return $paymentHistory;
    }


    /**
     *
     * Function that Convert from a table to Csv
     * @param unknown_type $html
     */
    private function htmlToCsv($html)
    {
        $html = str_replace(array("\t", "\r", "\n"), "", $html);
        $csv = "";
        $dom = new Zend_Dom_Query($html);
        $results = $dom->query('tr');
        $count = count($results); // get number of matches: 4
        foreach ($results as $result) {
            $tdList = $result->childNodes;
            $tdNumber = $tdList->length;
            if ($tdNumber > 0) {
                for ($i = 0; $i < $tdNumber; $i++) {
                    $value = $tdList->item($i)->nodeValue;
                    if ($i != $tdNumber - 1) {
                        $csv .= trim($value) . ";";
                    } else {
                        $csv .= trim($value);
                    }
                }
                $csv .= "\n";
            }
        }
        $exportData = str_getcsv($csv, "\n");
        return $exportData;
    }

    /**
     *
     * Function that returns the innet HTML code
     * @param unknown_type $element
     */
    private function DOMinnerHTML($element)
    {
        $innerHTML = "";
        $children = $element->childNodes;
        foreach ($children as $child) {
            $tmp_dom = new DOMDocument();
            $tmp_dom->appendChild($tmp_dom->importNode($child, true));
            $innerHTML .= trim($tmp_dom->saveHTML());
        }
        return $innerHTML;
    }

}
