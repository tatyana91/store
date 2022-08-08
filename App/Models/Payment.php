<?php
namespace App\Models;

use App\Config;
use Core\Model as CoreModel;

class Payment extends CoreModel
{
    static $orderStatus = array(
        0 => 'ожидает оплаты',
        1 => 'успешно оплачен (средства зарезервированы)',
        2 => 'успешно оплачен',
        3 => 'отменен',
        4 => 'средства возвращены',
        5 => 'инициирована авторизация через ACS банка-эмитента',
        6 => 'отклонен',
    );

    /**
     * Создать заказ у сбера
     * @param $order_data
     * <pre>
     * "order_id" => id заказа в системе интернет-магазина
     * "order_cost" => сумма заказа в рублях
     * </pre>
     * @return mixed
     * <pre>
     * "error" => string описание ошибки (в случае ошибки),
     * "orderId" => string id заказа в системе сбербанка,
     * "formUrl" => string ссылка на форму оплаты
     * </pre>
     */
    public static function createSberOrder($order_data) {
        $result = array('error' => '', 'orderId' => 0, 'formUrl' => '');

        $vars = array();
        $vars['token'] = Config::get('SBER_TOKEN');
        $vars['orderNumber'] = $order_data['order_id']; //id заказа в системе интернет-магазина
        $vars['amount'] = $order_data['order_cost'] * 100; //сумма заказа в копейках
        $vars['returnUrl'] = Config::get('SBER_RETURN_LINK'); //URL куда клиент вернется в случае успешной оплаты
        $vars['failUrl'] = Config::get('SBER_RETURN_LINK'); //URL куда клиент вернется в случае ошибки
        $vars['description'] = "Заказ № {$order_data['order_id']}"; //описание заказа, не более 24 символов, запрещены % + \r \n
        $params = http_build_query($vars);

        $create_order_url = Config::get('SBER_LINK').'register.do?'.$params;
        $ch = curl_init($create_order_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $request_result = curl_exec($ch);
        curl_close($ch);

        if ($request_result) {
            $request_result = json_decode($request_result, true);
            $result['orderId'] = $request_result['orderId'];
            $result['formUrl'] = $request_result['formUrl'];
        }
        else {
            $result['error'] = "Ошибка запроса к сервису оплаты";
        }

        return $result;
    }

    /**
     * Информация о платеже
     * @param $orderId string идентификатор заказа в системе Сбербанка
     * @return array
     * <pre>
     * "error" => string описание ошибки (в случае ошибки),
     * "orderStatus" => int статус заказа в системе Сбербанка
     * </pre>
     */
    public static function getSberOrderInfo($orderId) {
        $result = array("error" => "", "orderStatus" => '');

        $vars = array();
        $vars['token'] = Config::get('SBER_TOKEN');
        $vars['orderId'] = $orderId;
        $params = http_build_query($vars);

        $get_info_order_url = Config::get('SBER_LINK').'getOrderStatusExtended.do?'.$params;
        $ch = curl_init($get_info_order_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $request_result = curl_exec($ch);
        curl_close($ch);

        if ($request_result) {
            $request_result = json_decode($request_result, true);
            /*
                0 - Обработка запроса прошла без системных ошибок.
                5 - Доступ запрещён.
                5 - Пользователь должен сменить свой пароль.
                5 - [orderId] не задан.
                6 - Незарегистрированный orderId.
                7 - Системная ошибка.
             */
            if ($request_result['errorCode'] == 0) {
                $result['orderStatus'] = $request_result['orderStatus'];
            }
            else if ($request_result['errorCode'] == 6) {
                $result['error'] = "Заказ в системе оплаты не найден.";
            }
            else {
                $result['error'] = "Ошибка запроса к сервису оплаты.";
            }
        }
        else {
            $result['error'] = "Ошибка запроса к сервису оплаты.";
        }

        return $result;
    }

    /**
     * Получить текстовое описание статуса
     * @param $orderStatus
     * @return string
     */
    public static function getSberOrderStatusName($orderStatus) {
        return self::$orderStatus[$orderStatus];
    }

    /**
     * Возврат средств (не работает, надо писать в поддержку)
     * @param $orderId
     * @return array
     * <pre>
     * "error" => string описание ошибки (в случае ошибки)
     * </pre>
     */
    public static function reverseSberOrder($orderId){
        $result = array('error' => '');

        $vars = array();
        $vars['token'] = Config::get('SBER_TOKEN');
        $vars['orderId'] = $orderId;
        $params = http_build_query($vars);

        $get_info_order_url = Config::get('SBER_LINK').'reverse.do?'.$params;
        $ch = curl_init($get_info_order_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $request_result = curl_exec($ch);
        curl_close($ch);

        if ($request_result) {
            $request_result = json_decode($request_result, true);
            if ($request_result['errorCode']) {
                $result['error'] = $request_result['errorMessage'];
            }
        }
        else {
            $result['error'] = "Ошибка запроса к сервису оплаты";
        }

        return $result;
    }
}