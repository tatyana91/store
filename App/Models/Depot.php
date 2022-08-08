<?php
namespace App\Models;

use App\Config;
use Core\Model as CoreModel;

class Depot extends CoreModel
{
    /**
     * Сделать запрос к моему складу
     * @param $uri
     * @param string $method
     * @param array $payload
     * @return array
     */
    public static function sendRequest($uri, $method = 'GET', $payload = array()){
        file_put_contents(dirname($_SERVER['DOCUMENT_ROOT'])."/logs/depot_requests.txt", date('d.m.Y H:i:s', time())." $uri, $method, ".json_encode($payload)."\r\n", FILE_APPEND);

        $curl = curl_init();

        $uri = Config::get('DEPOT_API_URL').$uri;
        curl_setopt($curl, CURLOPT_URL, $uri);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);

        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_HEADER, true);

        curl_setopt($curl, CURLOPT_USERPWD, Config::get('DEPOT_USER_NAME').":".Config::get('DEPOT_USER_PASSWORD'));

        $user_agent = 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.2309.372 Safari/537.36';
        curl_setopt($curl, CURLOPT_USERAGENT, $user_agent);

        switch($method){
            case 'GET':
                curl_setopt($curl, CURLOPT_HTTPGET, true);
                break;
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_PUT, true);
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($curl);

        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $response_headers = substr($response, 0, $header_size);
        $response_body = substr($response, $header_size);

        $headers = [];
        $headers_data = explode("\n", rtrim($response_headers));
        $headers['status'] = $headers_data[0];
        array_shift($headers_data);
        foreach($headers_data as $part){
            $middle = explode(":", $part,2);
            if ( !isset($middle[1]) ) $middle[1] = null;
            $headers[trim($middle[0])] = trim($middle[1]);
        }

        $error = '';
        if (curl_errno($curl)) {
            $error = curl_error($curl);
        }

        $result = array();
        $result['error'] = $error;
        $result['headers'] = $headers;
        $result['response'] = json_decode($response_body, true);
        file_put_contents(dirname($_SERVER['DOCUMENT_ROOT'])."/logs/depot_requests.txt", date('d.m.Y H:i:s', time())." ".json_encode($result)."\r\n", FILE_APPEND);
        return $result;
    }

    /**
     * Получить ассортимент
     * @return array
     */
    public static function getAssortment($url = '/entity/assortment?expand=uom', $depot_products = array()){
        $result = self::sendRequest($url);
        $depot_products = array_merge($depot_products, $result['response']['rows']);

        $next_url = str_replace(Config::get('DEPOT_API_URL'), '', $result['response']['meta']['nextHref']);
        if ($next_url) {
            $depot_products = self::getAssortment($next_url, $depot_products);
        }

        return $depot_products;
    }

    /**
     * Получить папки
     * @param string $url
     * @param array $depot_folders
     * @return array
     */
    public static function getProductFolders($url = '/entity/productfolder?expand=productFolder&filter=archived=true;archived=false', $depot_folders = array()){
        $result = self::sendRequest($url);
        $depot_folders = array_merge($depot_folders, $result['response']['rows']);

        $next_url = str_replace(Config::get('DEPOT_API_URL'), '', $result['response']['meta']['nextHref']);
        if ($next_url) {
            $depot_folders = self::getAssortment($next_url, $depot_folders);
        }

        return $depot_folders;
    }

    /**
     * Получить заказы
     * @param string $url
     * @param array $depot_orders
     * @return array
     */
    public static function getOrders($url = '/entity/customerorder', $depot_orders = array()){
        $result = self::sendRequest($url);
        $depot_orders = array_merge($depot_orders, $result['response']['rows']);

        $next_url = str_replace(Config::get('DEPOT_API_URL'), '', $result['response']['meta']['nextHref']);
        if ($next_url) {
            $depot_orders = self::getAssortment($next_url, $depot_orders);
        }

        return $depot_orders;
    }

    /**
     * Получить контрагентов
     * @param string $url
     * @param array $depot_counterparty
     * @return array
     */
    public static function getCounterparty($url = '/entity/counterparty', $depot_counterparty = array()){
        $result = self::sendRequest($url);
        $depot_counterparty = array_merge($depot_counterparty, $result['response']['rows']);

        $next_url = str_replace(Config::get('DEPOT_API_URL'), '', $result['response']['meta']['nextHref']);
        if ($next_url) {
            $depot_counterparty = self::getAssortment($next_url, $depot_counterparty);
        }

        return $depot_counterparty;
    }

    /**
     * Получить склады
     * @param string $url
     * @param array $depot_stores
     * @return array
     */
    public static function getStores($url = '/entity/store', $depot_stores = array()){
        $result = self::sendRequest($url);
        $depot_stores = array_merge($depot_stores, $result['response']['rows']);

        $next_url = str_replace(Config::get('DEPOT_API_URL'), '', $result['response']['meta']['nextHref']);
        if ($next_url) {
            $depot_stores = self::getAssortment($next_url, $depot_stores);
        }

        return $depot_stores;
    }

    /**
     * Получить организации
     * @param string $url
     * @param array $depot_organization
     * @return array
     */
    public static function getOrganizations($url = '/entity/organization', $depot_organizations = array()){
        $result = self::sendRequest($url);
        $depot_organizations = array_merge($depot_organizations, $result['response']['rows']);

        $next_url = str_replace(Config::get('DEPOT_API_URL'), '', $result['response']['meta']['nextHref']);
        if ($next_url) {
            $depot_organizations = self::getAssortment($next_url, $depot_organizations);
        }

        return $depot_organizations;
    }

    /**
     * Получить точки продаж
     * @param string $url
     * @param array $depot_retailtstore
     * @return array
     */
    public static function getRetailstore($url = '/entity/retailstore?expand=cashiers,store', $depot_retailtstore = array()){
        $result = self::sendRequest($url);
        $depot_retailtstore = array_merge($depot_retailtstore, $result['response']['rows']);

        $next_url = str_replace(Config::get('DEPOT_API_URL'), '', $result['response']['meta']['nextHref']);
        if ($next_url) {
            $depot_retailtstore = self::getAssortment($next_url, $depot_retailtstore);
        }

        return $depot_retailtstore;
    }

    /**
     * Получить печатные формы
     * @param string $url
     * @param array $depot_forms
     * @return array
     /
    public static function getForms($url = '/entity/customerOrder/metadata/customtemplate/', $depot_forms = array()){
        $result = self::sendRequest($url);
        $depot_forms = array_merge($depot_forms, $result['response']['rows']);

        $next_url = str_replace(Config::get('DEPOT_API_URL'), '', $result['response']['meta']['nextHref']);
        if ($next_url) {
            $depot_forms = self::getAssortment($next_url, $depot_forms);
        }

        return $depot_forms;
    }*/

    /**
     * Получить товарный чек
     * @param $depot_order_id
     * @param $receipt_data
     * @return array
     * @throws \Exception
     */
    public static function printReceipt($depot_order_id, $receipt_data){
        $result = self::sendRequest("/entity/customerorder/$depot_order_id/export/", "POST", $receipt_data);
        if ($result['response']['errors']) {
            throw new \Exception(json_encode($result['response']['errors']));
        }
        return $result;
    }

    /**
     * Создать заказ на складе
     * @param array $order_data
     * @return array
     */
    public static function createDepotOrder($order_data){
        $result = self::sendRequest('/entity/customerorder', "POST", $order_data);
        if ($result['response']['errors']) {
            throw new \Exception(json_encode($result['response']['errors']));
        }
        return $result;
    }

    /**
     * Найти пользователя на складе
     * @param array $user_data
     * @return array
     */
    public static function getCounterpartyByUserData($user_data){
        $user_data['phone'] = preg_replace('/[ ()-]/', '', $user_data['phone']);
        $filter = "filter=phone=".urlencode($user_data['phone']);
        $result = self::sendRequest("/entity/counterparty?$filter");
        if ($result['response']['errors']) {
            throw new \Exception(json_encode($result['response']['errors']));
        }
        return $result['response'];
    }

    /**
     * Создать пользователя на складе
     * @param array $user_data
     * @return array
     */
    public static function createCounterpartyByUserData($user_data){
        unset($user_data['id']);
        $user_data['companyType'] = 'individual';
        return self::sendRequest('/entity/counterparty', "POST", $user_data);
    }

    /**
     * Получить ссылку на товарный чек
     * @param array $depot_order_id
     * @return array
     */
    public static function getOrderReceiptLink($depot_order_id){
        $result = array(
            "error" => '',
            "link" => ''
        );
        try {
            $template_href = "https://online.moysklad.ru/api/remap/1.2";
            $template_href .= "/entity/customerorder/metadata/customtemplate/".Config::get('DEPOT_RECEIPT_TEMPLATE');
            $receipt_data = array(
                "template" => array(
                    "meta" => array(
                        "href" => $template_href,
                        "type" => "customtemplate",
                        "mediaType" => "application/json"
                    )
                ),
                "extension" => "xls"
            );

            $depot_forms = Depot::printReceipt($depot_order_id, $receipt_data);
            $result['link'] = $depot_forms['headers']['Location'];
        }
        catch (\Exception $e) {
            $result['error'] = 'Ошибка получения товарного чека';
        }

        return $result;
    }

    /**
     * Получить товар
     * @param string $url
     * @param string
     * @return array
     */
    public static function getDepotProduct($product_guid){
        //note получить складской товар
        $url = "/entity/product/$product_guid?expand=uom,productFolder";
        $result = self::sendRequest($url);
        return $result['response'];
    }
}