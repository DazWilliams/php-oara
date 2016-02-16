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
 * @category   Tv
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class TerraVision extends \Oara\Network
{
    /**
     * Export client.
     * @var \Oara\Curl\Access
     */
    private $_client = null;

    /**
     * Constructor and Login
     * @param $cartrawler
     * @return Tv_Export
     */
    public function __construct($credentials)
    {

        $user = $credentials['user'];
        $password = $credentials['password'];
        $loginUrl = 'https://book.terravision.eu/login_check?';

        $valuesLogin = array(new \Oara\Curl\Parameter('_username', $user),
            new \Oara\Curl\Parameter('_password', $password),
            new \Oara\Curl\Parameter('_submit', 'Login')
        );

        $this->_client = new \Oara\Curl\Access($loginUrl, $valuesLogin, $credentials);

        $urls = array();
        $urls[] = new \Oara\Curl\Request('https://book.terravision.eu/login', array());
        $exportReport = $this->_client->get($urls);
        $dom = new Zend_Dom_Query($exportReport[0]);
        $results = $dom->query('input[name="_csrf_token"]');
        $token = null;
        foreach ($results as $result) {
            $token = $result->getAttribute("value");
        }

        $valuesLogin = array(new \Oara\Curl\Parameter('_username', $user),
            new \Oara\Curl\Parameter('_password', $password),
            new \Oara\Curl\Parameter('_submit', 'Login'),
            new \Oara\Curl\Parameter('_csrf_token', $token)
        );
        $urls = array();
        $urls[] = new \Oara\Curl\Request($loginUrl, $valuesLogin);
        $exportReport = $this->_client->post($urls);

    }

    /**
     * Check the connection
     */
    public function checkConnection()
    {
        $connection = false;

        $urls = array();
        $urls[] = new \Oara\Curl\Request('https://book.terravision.eu/partner/my/', array());
        $exportReport = $this->_client->get($urls);
        if (preg_match("/logout/", $exportReport[0], $matches)) {
            $connection = true;
        }
        return $connection;
    }

    /**
     * (non-PHPdoc)
     * @see library/Oara/Network/Base#getMerchantList()
     */
    public function getMerchantList()
    {
        $merchants = Array();
        $obj = Array();
        $obj['cid'] = 1;
        $obj['name'] = 'Terravision';
        $obj['url'] = 'https://www.terravision.eu/';
        $merchants[] = $obj;

        return $merchants;
    }

    /**
     * (non-PHPdoc)
     * @see library/Oara/Network/Base#getTransactionList($merchantId, $dStartDate, $dEndDate)
     */
    public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
    {
        $totalTransactions = Array();

        $stringToFind = $dStartDate->toString("MMMM yyyy");

        $urls = array();
        $urls[] = new \Oara\Curl\Request('https://book.terravision.eu/partner/my/payments', array());
        $exportReport = $this->_client->get($urls);
        /*** load the html into the object ***/
        $dom = new Zend_Dom_Query($exportReport[0]);
        $results = $dom->query('#navigation > table');
        $exportData = self::htmlToCsv(self::DOMinnerHTML($results->current()));
        $num = count($exportData);


        for ($i = 1; $i < $num - 1; $i++) {
            $transactionArray = str_getcsv($exportData[$i], ";");
            if ($transactionArray[0] == $stringToFind) {

                $transaction = array();
                $transaction['merchantId'] = 1;
                $transaction['status'] = \Oara\Utilities::STATUS_CONFIRMED;

                $transaction['date'] = $dEndDate->toString("yyyy-MM-dd HH:mm:ss");

                $transaction['amount'] = \Oara\Utilities::parseDouble(preg_replace('/[^0-9\.,]/', "", $transactionArray [2]));
                $transaction['commission'] = \Oara\Utilities::parseDouble(preg_replace('/[^0-9\.,]/', "", $transactionArray [2]));

                $totalTransactions[] = $transaction;
            }
        }

        return $totalTransactions;

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
