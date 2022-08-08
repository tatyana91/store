<?php
namespace App\Models;

use Core\Model as CoreModel;
use PDO;
use Core\Error;

class Catalog extends CoreModel{
    /**
     * Получить товары определенной категории
     * @param int $parent_id - идентификатор родителя
     * @param array $filters - параметры фильтров
     * @return array
     */
    public function getCategoryProducts($parent_id, $filters = array()){
        $error = '';
        $products = array();
        try{
            $db = static::getDB();

            $parent_ids = $this->getChildIds($parent_id);
            $params = $this->getQuestionMarkPlaceholders($parent_ids);

            $order_by = "rate DESC";
            $left_join = "";
            if ($filters['sort'] == 'price_desc') {
                $order_by = "price DESC";
            }
            else if ($filters['sort'] == 'price_asc') {
                $order_by = "price ASC";
            }
            else if ($filters['sort'] == 'title_desc') {
                $order_by = "title DESC";
            }
            else if ($filters['sort'] == 'title_asc') {
                $order_by = "title ASC";
            }
            else if ($filters['sort'] == 'seni') {
                $order_by = "c.char_value_id = 34 DESC, p.rate DESC";
                $left_join = "LEFT JOIN characteristics c ON c.product_id = p.id AND c.char_id = 4";
            }
            else if ($filters['sort'] == 'id') {
                $order_by = "c.char_value_id = 26 DESC, p.rate DESC";
                $left_join = "LEFT JOIN characteristics c ON c.product_id = p.id AND c.char_id = 4";
            }
            else if ($filters['sort'] == 'tena') {
                $order_by = "c.char_value_id = 23 DESC, p.rate DESC";
                $left_join = "LEFT JOIN characteristics c ON c.product_id = p.id AND c.char_id = 4";
            }

            $res = $db->prepare("SELECT 
                                                  p.id,
                                                  parent_id,
                                                  url, 
                                                  title,
                                                  price,
                                                  price_sale,
                                                  new,
                                                  popular,
                                                  sales,
                                                  qty,
                                                  (CAST(qty AS SIGNED) - (
                                                  	  SELECT IFNULL(SUM(count),0)
                                                      FROM order_products op
                                                      LEFT JOIN orders o ON o.id = op.order_id
                                                      WHERE 
                                                        op.product_id = p.id
                                                        AND o.status IN (1,2,3)
                                                  )) as qty_sort
                                              FROM products p
                                              $left_join
                                              WHERE 
                                                p.archived = 0
                                                AND p.parent_id IN ({$params['params']})
                                              ORDER BY qty_sort = 0 ASC, $order_by");
            $res->execute($parent_ids);
            $products = $res->fetchAll(PDO::FETCH_ASSOC);

            $char_values = [];
            foreach($filters as $char_id => $char_info) {
                foreach($char_info as $char_value_id) {
                    $char_info[$char_value_id] = $char_value_id;
                }
                $char_values[$char_id] = $char_info;
            }

            foreach($products as $key => &$product) {
                $unset = false;
                if (count($filters)) {
                    $product['chars'] = $this->getProductChars($product['id'])['chars'];
                    foreach($char_values as $char_id => $char_value){
                        if ($char_id == 'min_price') {
                            if ($product['price'] < $char_value) {
                                $unset = true;
                            }
                        }
                        else if ($char_id == 'max_price') {
                            if ($product['price'] > $char_value) {
                                $unset = true;
                            }
                        }
                        else if (isset($product['chars'][$char_id])){
                            if (!in_array($product['chars'][$char_id]['id'], $char_value)){
                                $unset = true;
                            }
                        }
                        else if ($char_id != 'sort'){
                            $unset = true;
                        }
                    }
                }

                if ($unset) {
                    unset($products[$key]);
                    continue;
                }

                $product_images = $this->getItemImages($product['id'], 'product');
                $product['image'] = $product_images[0]['path_middle'];

                $page_link_info = $this->getPageLinkInfo($product['parent_id']);
                $product['full_title'] = $page_link_info['title']." -> ".$product['title'];
                $product['full_url'] = $page_link_info['url']."/".$product['url'];

                $product['in_cart'] = (isset($_SESSION['cart'][$product['id']])) ? $_SESSION['cart'][$product['id']] : 0;

                $reserved_count_record = $this->getReservedCount($product['id']);
                if ($reserved_count_record['error']) {
                    $error = $reserved_count_record['error'];
                }
                $reserved_count = $reserved_count_record['reserved_count'];
                $product['free_qty'] = $product['qty'] - $reserved_count;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['products'] = $products;
        return $result;
    }

    /**
     * Получить товары определенной категории
     * @params int $parent_id - идентификатор родителя
     * @params array $filters - параметры фильтров
     * @return array
     */
    public function getProducts(){
        $error = '';
        $products = array();
        try{
            $db = static::getDB();

            $res = $db->query("SELECT 
                                                  p.id,
                                                  parent_id,
                                                  url, 
                                                  title,
                                                  price,
                                                  price_sale,
                                                  new,
                                                  popular,
                                                  sales,
                                                  qty,
                                                  (CAST(qty AS SIGNED) - (
                                                  	  SELECT IFNULL(SUM(count),0)
                                                      FROM order_products op
                                                      LEFT JOIN orders o ON o.id = op.order_id
                                                      WHERE 
                                                        op.product_id = p.id
                                                        AND o.status IN (1,2,3)
                                                  )) as qty_sort
                                              FROM products p
                                              WHERE p.archived = 0");
            $products = $res->fetchAll(PDO::FETCH_ASSOC);

            foreach($products as $key => &$product) {
                $product_images = $this->getItemImages($product['id'], 'product');
                $product['image'] = $product_images[0]['path_middle'];

                $page_link_info = $this->getPageLinkInfo($product['parent_id']);
                $product['full_title'] = $page_link_info['title']." -> ".$product['title'];
                $product['full_url'] = $page_link_info['url']."/".$product['url'];

                $product['in_cart'] = (isset($_SESSION['cart'][$product['id']])) ? $_SESSION['cart'][$product['id']] : 0;

                $reserved_count_record = $this->getReservedCount($product['id']);
                if ($reserved_count_record['error']) {
                    $error = $reserved_count_record['error'];
                }
                $reserved_count = $reserved_count_record['reserved_count'];
                $product['free_qty'] = $product['qty'] - $reserved_count;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['products'] = $products;
        return $result;
    }

    /**
     * Получить категории
     * @return array
     */
    public function getCategories(){
        $error = '';
        $pages = array();
        try{
            $db = static::getDB();
            $res = $db->query("  SELECT 
                                              id, 
                                              title,
                                              url,
                                              parent_id
                                          FROM pages
                                          WHERE 
                                            parent_id = 19
                                            AND archived = 0
                                          ORDER BY rate DESC");
            $res->execute();
            $pages_res = $res->fetchAll(PDO::FETCH_ASSOC);
            foreach($pages_res as $page) {
                $pages[$page['id']] = $page;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['pages'] = $pages;
        return $result;
    }

    /**
     * Получить дочерние категории
     * @return array
     */
    public function getParentCategories($parent_id){
        $error = '';
        $pages = array();
        try{
            $db = static::getDB();
            $parent_id = ($parent_id == 33) ? 34 : $parent_id;
            $res = $db->prepare("  SELECT 
                                              id, 
                                              title,
                                              url,
                                              parent_id
                                          FROM pages
                                          WHERE 
                                            parent_id = :parent_id
                                            AND archived = 0
                                          ORDER BY rate DESC");
            $res->bindValue(":parent_id", $parent_id, PDO::PARAM_INT);
            $res->execute();
            $pages = $res->fetchAll(PDO::FETCH_ASSOC);
            foreach($pages as &$page) {
                $page_images = $this->getItemImages($page['id'], 'page');
                $page['image'] = $page_images[0]['path_middle'];

                $page_link_info = $this->getPageLinkInfo($page['parent_id']);
                $page['full_title'] = $page_link_info['title']." -> ".$page['title'];
                $page['full_url'] = $page_link_info['url']."/".$page['url'];
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['pages'] = $pages;
        return $result;
    }

    /**
     * Получить фильтры для категории
     * @param int $page_id
     * @return array
     */
    public function getCategoryChars($page_id){
        $error = '';
        try{
            $db = static::getDB();

            $parent_ids = $this->getChildIds($page_id);
            $params = $this->getQuestionMarkPlaceholders($parent_ids);

            $res = $db->prepare("  SELECT 
                                              rc.id as char_id,
                                              rc.title as char_title, 
                                              rcw.id as char_value_id,
                                              rcw.value as char_value_title
                                            FROM products p
                                            LEFT JOIN characteristics c ON c.product_id =  p.id
                                            LEFT JOIN ref_chars rc ON rc.id = c.char_id
                                            LEFT JOIN ref_chars_values rcw ON rcw.id = c.char_value_id
                                            WHERE 
                                                parent_id IN ({$params['params']})
                                                AND c.id IS NOT NULL
                                                AND p.archived = 0
                                            ORDER BY rc.rate DESC, rcw.rate DESC");
            $res->execute($parent_ids);
            while ($chars_res = $res->fetch(PDO::FETCH_ASSOC)){
                $chars[$chars_res['char_id']]['char_title'] = $chars_res['char_title'];
                $chars[$chars_res['char_id']]['values'][$chars_res['char_value_id']]['title'] = $chars_res['char_value_title'];
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $chars = array();
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['chars'] = $chars;
        return $result;
    }

    /**
     * Получить фильтры для категории
     * @param int $page_id
     * @return array
     */
    public function getPriceRange($page_id){
        $error = '';
        $min_price = 0;
        $max_price = 0;
        try{
            $db = static::getDB();

            $parent_ids = $this->getChildIds($page_id);
            $params = $this->getQuestionMarkPlaceholders($parent_ids);

            $res = $db->prepare("  SELECT 
                                             IFNULL(MIN((IF(price_sale > 0, price_sale, price))), 0) as min_price, 
                                             IFNULL(MAX(`price`), 0) as max_price
                                          FROM products p                                          
                                          WHERE 
                                            parent_id IN ({$params['params']})
                                            AND archived = '0'");
            $res->execute($parent_ids);
            $prices = $res->fetch(PDO::FETCH_ASSOC);
            $min_price = $prices['min_price'];
            $max_price = $prices['max_price'];
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['min_price'] = $min_price;
        $result['max_price'] = $max_price;
        return $result;
    }

    /**
     * Обновить информацию об оплате заказе
     * @param array $order_new_data
     * <pre>
     * "order_id" => int id заказа в нашей базе
     * "paymentOrderId" => int id заказа, сгенерированный сбербанком
     * "paymentFormUrl" => string сгенерированная сбером ссылка на оплату
     * "paymentFormUrlLifeTime" => int дата окончания работоспособности ссылки
     * "payment_type" => int способ оплаты (в случае ошибки сбрасываем на оплату наличными или картой при получении)
     * "status" => int статус заказа в интернет магазине
     * </pre>
     * @param $order_notice string
     * @return array
     * <pre>
     * "error" => string сообщение об ошибке (в случае ошибки)
     * </pre>
     */
    public function updateOrderPayment(array $order_new_data, $order_notice = ''):array{
        $error = '';
        try{
            if (!isset($order_new_data['paymentOrderId'])) $order_new_data['paymentOrderId'] = 0;
            if (!isset($order_new_data['paymentFormUrl'])) $order_new_data['paymentFormUrl'] = '';
            if (!isset($order_new_data['paymentFormUrlLifeTime'])) $order_new_data['paymentFormUrlLifeTime'] = 0;
            if (!isset($order_new_data['payment_type'])) $order_new_data['payment_type'] = 1;
            if (!isset($order_new_data['status'])) $order_new_data['status'] = 1;

            $order_old = $this->getOrderWithoutProducts($order_new_data['order_id']);
            $order_old = $order_old['order'];
            $order_old_data = array(
                'order_id' => $order_old['id'],
                'paymentOrderId' => $order_old['paymentOrderId'],
                'paymentFormUrl' => $order_old['paymentFormUrl'],
                'paymentFormUrlLifeTime' => $order_old['paymentFormUrlLifeTime'],
                'payment_type' => $order_old['payment_type'],
                'status' => $order_old['status']);

            if ($order_old_data != $order_new_data) {
                $payment_date = ($order_new_data['status'] == 3) ? time() : 0;
                $db = static::getDB();
                $res = $db->prepare("UPDATE orders 
                                          SET
                                            paymentOrderId = :paymentOrderId,
                                            paymentFormUrl = :paymentFormUrl,
                                            paymentFormUrlLifeTime = :paymentFormUrlLifeTime,
                                            payment_type = :payment_type,
                                            payment_date = :payment_date,
                                            status = :status
                                          WHERE id = :id");
                $res->bindValue(':id', $order_new_data['order_id'], PDO::PARAM_INT);
                $res->bindValue(':paymentOrderId', $order_new_data['paymentOrderId']);
                $res->bindValue(':paymentFormUrl', $order_new_data['paymentFormUrl']);
                $res->bindValue(':paymentFormUrlLifeTime', $order_new_data['paymentFormUrlLifeTime'], PDO::PARAM_INT);
                $res->bindValue(':payment_type', $order_new_data['payment_type'], PDO::PARAM_INT);
                $res->bindValue(':status', $order_new_data['status'], PDO::PARAM_INT);
                $res->bindValue(':payment_date', $payment_date, PDO::PARAM_INT);
                $res->execute();

                $history = "ID заказа сбербанка - {$order_new_data['paymentOrderId']}, ссылка на оплату - {$order_new_data['paymentFormUrl']}";
                $history .= (($order_new_data['paymentFormUrlLifeTime']) ? " (действительна до ".date('d.m.Y H:i', $order_new_data['paymentFormUrlLifeTime']).")" : '') . '. ';
                $history .= "Способ оплаты - " . (($order_new_data['payment_type'] == 2) ? 'банковской картой онлайн' : 'наличными или картой при получении') . '. ';
                $history .= "Статус заказа - {$this->order_statuses[$order_new_data['status']]['title']}.";
                $history .= ($payment_date) ? " Оплачен - ".date('d.m.Y H:i', $payment_date)."." : '';
                $add_log = $this->addLog(array(
                    'log_code' => 5,
                    'user_id' => 1,
                    'history' => $history,
                    'mod_id' => $order_new_data['order_id']
                ));
                if ($add_log['error']) {
                    $error = $add_log['error'];
                }

                $message = "";
                $message_to_admin = "";
                if ($order_new_data['status'] == 1) {
                    $message = "<div>Способ оплаты заказа №{$order_new_data['order_id']} изменен на \"наличными или картой при получении\".</div>";
                    $message_to_admin = $message;
                    $message .= "<div>Для уточнения актуальности заказа мы свяжемся с вами в ближайшее время.</div>";
                    $message .= "<div>Вы так же можете самостоятельно связаться с нами по номеру телефона <a href=\"tel:{$this->settings['phone']}\">{$this->settings['phone']}</a>.</div>";
                }
                else if ($order_new_data['status'] == 3) {
                    $message = "<div>Заказ №{$order_new_data['order_id']} успешно оплачен.</div>";
                    $message_to_admin = $message;
                }
                else if ($order_new_data['status'] == 4) {
                    $message = "<div>Заказ №{$order_new_data['order_id']} выполнен.</div>";
                    $message_to_admin = $message;
                    $message .= "<div>Спасибо, что выбрали нас!</div>";
                }
                else if ($order_new_data['status'] == 5) {
                    $message = "<div>Заказ №{$order_new_data['order_id']} отклонен.</div>";
                    $message .= "<div>$order_notice</div>";
                    $message_to_admin = $message;
                }

                if (!$error){
                    $subject = "Информация по заказу №{$order_new_data['order_id']} на сайте {$_SERVER['SERVER_NAME']}";
                    if ($message && $order_old['email']) {
                        $send_mail = $this->sendMail($order_old['email'], $subject, $message);
                        if (!$send_mail){
                            $error = "Ошибка отправки сообщения";
                        }
                    }

                    if ($message_to_admin) {
                        $send_mail = $this->sendMail($this->settings['mailorders'], $subject, $message_to_admin);
                        if (!$send_mail){
                            $error = "Ошибка отправки сообщения";
                        }
                    }
                }

                if ($order_notice) {
                    $add_log = $this->addLog(array(
                        'log_code' => 5,
                        'user_id' => 1,
                        'history' => $order_notice,
                        'mod_id' => $order_new_data['order_id']
                    ));
                    if ($add_log['error']) {
                        $error = $add_log['error'];
                    }
                }
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о заказе';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }
}