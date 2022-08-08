<?php
namespace App\Models;

use App\Config;
use Core\Error;
use Core\Model as CoreModel;

class DepotIM extends CoreModel
{
    /**
     * Запомнить сопоставление со складом
     * @param $depot_products
     * @return array
     */
    public function createDepotOrder($order_id){
        $notice = '';
        $error = '';

        $order_info = $this->getOrder($order_id);
        $order_info = $order_info['order'];

        $deliveryPlannedMoment = "";
        if ($order_info['order']['delivery_date']) {
            $deliveryPlannedMoment = date("Y-m-d", $order_info['order']['delivery_date']);
            $deliveryPlannedMoment .= " ";
            $deliveryPlannedMoment .= explode(' - ', $order_info['order']['delivery_time'])[1].":00";
        }
        //$moment = date('Y-m-d H:i:s', strtotime($order_info['order']['date']));
        $moment = $deliveryPlannedMoment;

        $positions = array();
        foreach ($order_info['products'] as $product) {
            if (!$product['depot_id']) {
                $error = "У позиции \"{$product['title']}\" не указан складской код";
                break;
            }

            //учтем делимость позиции для правильного количества и стоимости в заказе на складе
            $product_count = $product['count'];
            $product_price = $product['price'];
            /*$ref_product = $this->getProductFields($product['product_id']);
            $ref_product = $ref_product['product'];
            if ($ref_product['count_type_part'] > 0) {
                $product_count = $product['count'] * $ref_product['count_part'];
                $product_price = $product_price / $ref_product['count_part'];
            }*/

            $positions[] = array(
                "quantity" => round($product_count, 2),
                "price" => $product_price  * 100, //в копейках
                "assortment" => array(
                    "meta" => array(
                        "href" => Config::get('DEPOT_API_URL')."/entity/product/".$product['depot_id'],
                        "type" => "product",
                        "mediaType" => "application/json"
                    )

                )
            );
        }

        if (!$error) {
            $agent = $this->getDepotAgent(array(
                "id" => $order_info['order']['user_id'],
                "name" => $order_info['order']['name'],
                "email" => $order_info['order']['email'],
                "phone" => $order_info['order']['phone'],
                "actualAddress" => $order_info['order']['address_title']
            ));
            $error = $agent['error'];

            if (!$error) {
                $agent = $agent['user_depot_id'];

                $description = ($order_info['order']['pvz_title'])
                    ? "Пункт выдачи: {$order_info['order']['pvz_title']}"
                    : "Доставка: {$order_info['order']['address_title']}";
                $description .= ($order_info['order']['comment']) ? " ({$order_info['order']['comment']})" : '';
                $depot_order_data = array(
                    'vatEnabled' => false,
                    'moment' => $moment,
                    'deliveryPlannedMoment' => $deliveryPlannedMoment,
                    'description' => $description,
                    'organization' => array(
                        "meta" => array(
                            "href" => Config::get('DEPOT_API_URL')."/entity/organization/".Config::get('DEPOT_ORDER_ORGANIZATION'),
                            "type" => "organization",
                            "mediaType" => "application/json"
                        )
                    ),
                    'agent' => array(
                        "meta" => array(
                            "href" => Config::get('DEPOT_API_URL')."/entity/counterparty/$agent",
                            "type" => "counterparty",
                            "mediaType" => "application/json"
                        )
                    ),
                    'store' => array(
                        "meta" => array(
                            "href" => Config::get('DEPOT_API_URL')."/entity/store/".Config::get('DEPOT_ORDER_STORE'),
                            "type" => "store",
                            "mediaType" => "application/json"
                        )
                    ),
                    'state' => array(
                        "meta" => array(
                            "href" => Config::get('DEPOT_API_URL')."/entity/state/".Config::get('DEPOT_ORDER_STATUS'),
                            "type" => "state",
                            "mediaType" => "application/json"
                        )
                    )
                );

                if ($order_info['order']['delivery_cost']) {
                    $delivery_type = Config::get('DEPOT_ORDER_DELIVERY');
                    if ($order_info['order']['delivery_cost'] == 99) {
                        $delivery_type = Config::get('DEPOT_ORDER_DELIVERY_PVZ');
                    }

                    $positions[] = array(
                        "quantity" => round(1, 2),
                        "price" => $order_info['order']['delivery_cost'] * 100, //в копейках
                        "assortment" => array(
                            "meta" => array(
                                "href" => Config::get('DEPOT_API_URL')."/entity/product/".$delivery_type,
                                "type" => "service",
                                "mediaType" => "application/json"
                            )
                        )
                    );
                }
                $depot_order_data['positions'] = $positions;

                $depot_order_id = 0;
                $depot_number = '';
                try {
                    $depot_order = Depot::createDepotOrder($depot_order_data);
                    $depot_order_id = $depot_order['response']['id'];
                    $depot_number = $depot_order['response']['name'];
                }
                catch (\Exception $e) {
                    Error::logError($e);
                    $error = "Ошибка создания заказа на складе.";
                }

                if (!$error) {
                    $edit = $this->editOrderField($order_id, 'depot_id', $depot_order_id);
                    if ($edit['error']) {
                        $error = $edit['error'];
                    }
                    else {
                        $edit = $this->editOrderField($order_id, 'depot_number', $depot_number);
                        if ($edit['error']) {
                            $error = $edit['error'];
                        }

                        $notice = "Успешно";
                    }
                }
            }
        }

        if ($error) {
            $to = $this->settings['mailorders'];
            $subject = "Не удалось создать автоматически заказ №$order_id на складе";
            $message = "<div>$error</div>";
            $this->sendMail($to, $subject, $message);
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Получить код пользователя на складе
     * @param $user_data
     * @return array
     * <pre>
     *  "error" => string описание ошибки (в случае ошибки),
     *  "user_depot_id" => string id пользователя на складе
     * </pre>
     */
    public function getDepotAgent($user_data){
        $error = "";
        $user_depot_id = 0;
        if ($user_data['id']) {
            $user_info = $this->getSiteUser($user_data['id']);
            $user_info = $user_info['user'];
            if ($user_info['depot_id']) {
                $user_depot_id = $user_info['depot_id'];
            }
        }

        if (!$user_depot_id) {
            try {
                $result = Depot::getCounterpartyByUserData($user_data);
                $user_depot_id = $result['rows'][0]['id'];
            }
            catch (\Exception $e) {
                Error::logError($e);
                $error = "Ошибка получения пользователя со склада";
            }
        }

        if (!$user_depot_id) {
            try {
                $result = Depot::createCounterpartyByUserData($user_data);
                $user_depot_id = $result['response']['id'];
            }
            catch (\Exception $e) {
                Error::logError($e);
                $error = "Ошибка создания пользователя на складе";
            }
        }

        if (!$user_depot_id) {
            $user_depot_id = Config::get('DEPOT_ORDER_AGENT');
        }

        return array(
            "error" => $error,
            "user_depot_id" => $user_depot_id
        );
    }
}