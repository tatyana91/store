<?php
namespace App\Controllers;

use App\Config;
use App\Models\Payment;
use Core\Controller as CoreController;
use Core\View as CoreView;
use App\Models\Catalog as CatalogModel;
use App\Models\Payment as PaymentModel;
use App\Models\DepotIM as DepotIMModel;

class Catalog extends CoreController{
    private $model;

    public function __construct(){
        parent::__construct();
        $this->model = new CatalogModel();
    }

    /**
     * Страницы каталога
     * @param $url
     * @throws \Exception
     */
    public function index($url){
        $url_params = explode('/', $url);
        $page_url = $url_params[count($url_params) - 1];

        $page = $this->model->getPageInfo($page_url);
        $product = [];

        $show_404 = false;
        $show_page = false;
        $show_product = false;
        if ($page['error']) {
            $show_404 = true;
        }
        else if (!$page['page'] || $page['page']['full_url'] != $url){
            $product = $this->model->getProductInfo($page_url);
            if ($product['error'] || !$product['product'] || $product['product']['full_url'] != $url) {
                $show_404 = true;
            }
            else {
                $show_product = true;
            }
        }
        else {
            $show_page = true;
        }

        if ($show_404) {
            throw new \Exception("404 Маршрут не найден", 404);
        }
        else if ($show_page || $show_product) {
            if ($show_page) {
                $categories = $this->model->getParentCategories($page['page']['id']);
                if ($categories['error']) {
                    $this->error = $categories['error'];
                }
                $page['page']['categories'] = $categories['pages'];

                if ($page_url == 'katalog') {
                    $show_filters = false;
                    $show_products = false;
                }
                else {
                    $show_filters = true;
                    $show_products = true;
                }

                $filters = array();
                if ($show_filters) {
                    //выборка всех характеристик
                    $chars = $this->model->getCategoryChars($page['page']['id']);

                    //посмотрим выбранные фильтры
                    foreach($chars['chars'] as $char_id => &$char_info) {
                        $p = "p".$char_id;
                        if (isset($_GET[$p])){
                            $param_chars = explode(',', $_GET[$p]);
                            foreach($char_info['values'] as $char_value_id => $char_value_title){
                                $checked = false;
                                if (in_array($char_value_id, $param_chars)){
                                    $checked = true;
                                    $filters[$char_id][$char_value_id] = $char_value_id;
                                }
                                $char_info['values'][$char_value_id]['checked'] = $checked;
                            }
                        }
                    }

                    //выборка мин и макс price
                    $prices = $this->model->getPriceRange($page['page']['id']);
                    $page['page']['min_price'] = 0;
                    $page['page']['max_price'] = $prices['max_price'];

                    $page['page']['title_min_price'] = $prices['min_price'];

                    if (isset($_GET['min_price'])){
                        $filters['min_price'] = (int)$_GET['min_price'];
                        $page['page']['min_price_checked'] = $filters['min_price'];
                    }
                    else {
                        $page['page']['min_price_checked'] = $page['page']['min_price'];
                    }

                    if (isset($_GET['max_price'])){
                        $filters['max_price'] = (int)$_GET['max_price'];
                        $page['page']['max_price_checked'] = $filters['max_price'];
                    }
                    else {
                        $page['page']['max_price_checked'] = $page['page']['max_price'];
                    }

                    $page['page']['sort'] = '0';
                    if (isset($_GET['sort'])){
                        $filters['sort'] = $_GET['sort'];
                        $page['page']['sort'] = $filters['sort'];
                    }

                    $page['page']['chars'] = $chars['chars'];
                }

                $products = array();
                if ($show_products) {
                    $products = $this->model->getCategoryProducts($page['page']['id'], $filters);
                    if ($products['error']) {
                        $this->error = $products['error'];
                    }
                    $products = $products['products'];
                }

                $page['page']['products'] = $products;

                $page['page']['sort_params'] = array();
                $page['page']['sort_params']['price_asc'] = 'возрастанию цены';
                $page['page']['sort_params']['price_desc'] = 'убыванию цены';
                $page['page']['sort_params']['title_asc'] = 'возрастанию названия';
                $page['page']['sort_params']['title_desc'] = 'убыванию названия';
                $page['page']['sort_params']['seni'] = 'сначала seni';
                $page['page']['sort_params']['tena'] = 'сначала TENA';
                $page['page']['sort_params']['id'] = 'сначала iD';

                $page['page']['selected_filters'] = (bool)count($filters);

                $seo_data = $this->model->getSeoData($page['page']['id'], "pages");
                if ($seo_data['error']) {
                    $this->error = $seo_data['error'];
                }
                else {
                    if (count($seo_data['seo'])) {
                        $page['page']['seo'] = $seo_data['seo'];
                    }
                    else {
                        if ($page['page']['url'] == 'katalog') {
                            $page['page']['seo']['title'] = $page['page']['title'];
                            $page['page']['seo']['keywords'] = "";
                            $page['page']['seo']['description'] = $page['page']['title'];
                        }
                        else {
                            $page['page']['seo']['title'] = "Купить {$page['page']['title']} по цене от {$page['page']['title_min_price']} руб. в интернет-магазине \"Лотос Маркет\"";
                            $page['page']['seo']['keywords'] = "";
                            $page['page']['seo']['description'] = "{$page['page']['title']}, купить {$page['page']['title']} с доставкой по СПБ, интернет-магазин \"Лотос Маркет\"";
                        }
                    }
                }

                CoreView::renderTemplate('Catalog/page.html', [
                    'settings' => $this->settings,
                    'header_pages' => $this->header_pages,
                    'catalog_menu_pages' => $this->catalog_menu_pages,
                    'show_filters' => $show_filters,
                    'show_products' => $show_products,
                    'page' => $page['page'],
                    'catalog_menu' => $this->catalog_menu,
                    'params' => $this->params
                ]);
                exit();
            }

            if ($show_product) {
                $chars = $this->model->getProductChars($product['product']['id']);
                if ($chars['error']) {
                    $this->error = $chars['error'];
                }
                $product['product']['chars'] = $chars['chars'];

                $product['product']['sales'] = ($product['product']['sales']) ? explode(',', $product['product']['sales']) : array();

                $sales_pages = array();
                $get_sales_pages = $this->model->getPageChilds(51);
                foreach ($get_sales_pages['pages'] as $sales_page) {
                    $sales_pages[$sales_page['id']]['title'] = $sales_page['title'];
                    $sales_pages[$sales_page['id']]['full_url'] = $sales_page['full_url'];
                }

                foreach ($product['product']['sales'] as $key => $sale) {
                    if (!count($sales_pages[$sale])){
                        unset($product['product']['sales'][$key]);
                    }
                }

                $product['product']['goods_info'] = $this->model->getGoods($product['product']['goods']);

                $seo_data = $this->model->getSeoData($product['product']['id'], "products");
                if ($seo_data['error']) {
                    $this->error = $seo_data['error'];
                }
                else {
                    if (count($seo_data['seo'])) {
                        $product['product']['seo'] = $seo_data['seo'];
                    }
                    else {
                        $product['product']['seo']['title'] = "Купить {$product['product']['title']} по цене ".(($product['product']['price_sale']) ?: $product['product']['price'])." руб. в интернет-магазине \"Лотос Маркет\"";
                        $product['product']['seo']['keywords'] = "";
                        $product['product']['seo']['description'] = "{$product['product']['title']}, купить {$product['product']['title']} с доставкой по СПБ, интернет-магазин \"Лотос Маркет\"";
                    }
                }

                CoreView::renderTemplate('Catalog/card.html', [
                    'settings' => $this->settings,
                    'header_pages' => $this->header_pages,
                    'catalog_menu' => $this->catalog_menu,
                    'params' => $this->params,
                    'catalog_menu_pages' => $this->catalog_menu_pages,
                    'product' => $product['product'],
                    'sales_pages' => $sales_pages
                ]);
                exit();
            }
        }
    }

    /**
     * Обработчик асинхронных запросов
     */
    public function ajax(){
        $act = $_POST['act'];
        $error = '';
        if ($act == 'add_to_cart') {
            $id = (int)$_POST['id'];

            if (isset($_SESSION['cart'][$id])) {
                $_SESSION['cart'][$id]++;
            }
            else {
                $_SESSION['cart'][$id] = 1;
            }

            $result = array();
            $result['error'] = $error;
            $result['count'] = count($_SESSION['cart']);
            $result['inputs'] = CoreView::returnTemplate('/inc/card_change.html', [
                "card_product_id" => $id,
                "card_product_count" => $_SESSION['cart'][$id]
            ]);
            echo json_encode($result);
            exit();
        }

        if ($act == 'remove_from_cart') {
            $id = (int)$_POST['id'];

            if (isset($_SESSION['cart'][$id])) {
                unset($_SESSION['cart'][$id]);
            }

            $result = array();
            $result['error'] = $error;
            $result['count'] = count($_SESSION['cart']);
            echo json_encode($result);
            exit();
        }

        if ($act == 'change_cart_count') {
            $id = (int)$_POST['id'];
            $count = (int)$_POST['count'];

            if ($count > 0) {
                $_SESSION['cart'][$id] = $count;
            }
            else {
                unset($_SESSION['cart'][$id]);
            }

            $cart_info = $this->model->getCartInfo($_SESSION['cart']);

            $result = array();
            $result['error'] = $error;
            $result['count'] = count($_SESSION['cart']);
            $result['cart_cost'] = $cart_info['cart_cost'];
            $result['total_cost'] = $cart_info['total_cost'];
            $result['delivery_cost'] = $cart_info['delivery_cost'];
            $result['current_product_cost'] = $cart_info['products'][$id]['cost'];
            echo json_encode($result);
            exit();
        }

        if ($act == 'apply_promo') {
            $result = array('error' => '');

            $_SESSION['promo'] = $_POST['promo'];
            $cart_info = $this->model->getCartInfo($_SESSION['cart'], $_POST['promo']);
            if ($cart_info['text'] != 'Промокод применен') {
                $result['error'] = $cart_info['text'];
            }
            else {
                $result['discount'] = $cart_info['discount'];
                $result['total_cost'] = $cart_info['total_cost'];
                $result['cart_cost'] = $cart_info['cart_cost'];
                $result['discount_products'] = $cart_info['discount_products'];
                $result['text'] = $cart_info['text'];
            }

            echo json_encode($result);
            exit();
        }

        if ($act == 'create_order') {
            $order_data = $_POST['data'];
            $order_id = 0;
            $order_cost = 0;
            $payment_type_info = "";
            $payment_link = "";
            $payment_link_lifetime = 0;

            $need_pay_link = false;
            if ($order_data['payment_type'] == 2) {
                if (!$order_data['pvz_id'] && $order_data['region'] != 1) {
                    $payment_type_info = "<div>Онлайн оплата доступна только для заказов по Санкт-Петербургу.</div>";
                    $payment_type_info .= "<div>Способ оплаты заказа изменен на \"наличными или картой при получении\".</div>";
                    $order_data['payment_type'] = 1;
                }
                else {
                    $need_pay_link = true;
                }
            }

            $create_order = $this->model->createOrder($order_data);
            if ($create_order['error']) {
                $error = $create_order['error'];
            }
            else {
                $order_id = $create_order['order_id'];
                $order_cost = $create_order['order_cost'];
            }

            if (!$error && $need_pay_link) {
                $sber_order_info = PaymentModel::createSberOrder(array("order_id" => $order_id, "order_cost" => $order_cost));
                $paymentOrderId = 0;
                $status = 1;
                if ($sber_order_info && $sber_order_info['orderId']) {
                    $paymentOrderId = $sber_order_info['orderId'];
                    $payment_link = $sber_order_info['formUrl'];
                    $payment_link_lifetime = time() + 1800;
                    $payment_type = 2;
                    $status = 2;
                }
                else {
                    $payment_type_info = "<div>Ошибка формирования ссылки на оплату.</div>";
                    $payment_type_info .= "<div>Способ оплаты заказа изменен на \"наличными или картой при получении\".</div>";

                    $tel_link = "<a href='tel:+7(812)111-1-111'>+7 (812) 111-1-111</a>";
                    $email_link = "<a href='mailto:order@site.ru'>order@site.ru</a>";
                    $payment_type_info .= "<div>Пожалуйста, сообщите нам об этом по телефону $tel_link или напишите на эл.почту $email_link</div>";

                    $payment_type = 1;
                }

                $result_update = $this->model->updateOrderPayment(array(
                    'order_id' => $order_id,
                    'paymentOrderId' => $paymentOrderId,
                    'paymentFormUrl' => $payment_link,
                    'paymentFormUrlLifeTime' => $payment_link_lifetime,
                    'payment_type' => $payment_type,
                    'status' => $status
                ));
                if ($result_update['error']) {
                    $error = $result_update['error'];
                }
            }

            if (!$error) {
                $depot = new DepotIMModel();
                $depot->createDepotOrder($order_id);
            }

            if (!$error) {
                $to = $this->settings['mailorders'];
                $subject = "Поступил заказ №$order_id с сайта {$_SERVER['SERVER_NAME']}";
                $send_order = $this->model->sendOrderMail($to, $subject, $order_id);
                if ($send_order['error']) {
                    $error = $send_order['error'];
                }

                if (!$error && $order_data['email']) {
                    $to = $order_data['email'];
                    $subject = "Информация о заказе №$order_id на сайте {$_SERVER['SERVER_NAME']}";
                    $send_order = $this->model->sendOrderMail($to, $subject, $order_id);
                    if ($send_order['error']) {
                        $error = $send_order['error'];
                    }
                }

                if (!$error) {
                    unset($_SESSION['cart']);
                }
            }

            $html = CoreView::returnTemplate('/inc/checkout_result.html', [
                "order_id" => $order_id,
                "order_cost" => $order_cost,
                "payment_type_info" => $payment_type_info,
                "payment_link" => $payment_link
            ]);

            $result = array();
            $result['error'] = $error;
            $result['html'] = $html;
            $result['payment_link'] = $payment_link;
            echo json_encode($result);
            exit();
        }

        if ($act == 'checkout_cart') {
            $product_errors = array();
            $cart_info = $this->model->getCartInfo($_SESSION['cart']);
            if ($cart_info['error']) {
                $error = $cart_info['error'];
            }
            else {
                foreach ($cart_info['products'] as $product_info) {
                    if ($product_info['cart_count'] > $product_info['free_qty']) {
                        $product_errors[$product_info['id']] = $product_info['free_qty'];
                    }
                }
            }

            $result = array();
            $result['error'] = $error;
            $result['product_errors'] = $product_errors;
            echo json_encode($result);
            exit();
        }

        if ($act == 'get_delivery_options') {
            $delivery_params = $this->model->getDeliveryParams($_POST['date']);

            $html = CoreView::returnTemplate('/inc/delivery_options.html', [
                "delivery_time_items" => $delivery_params['times']
            ]);

            $result = array();
            $result['html'] = $html;
            $result['day'] = $delivery_params['day'];
            echo json_encode($result);
            exit();
        }

        if ($act == 'get_full_delivery_options') {
            $result = array();
            $result['html'] = CoreView::returnTemplate('/inc/delivery_options.html', [
                "delivery_time_items" => CatalogModel::getDeliveryTimeItems()
            ]);
            echo json_encode($result);
            exit();
        }

        if ($act == 'request_priceform') {
            echo json_encode($this->model->requestPriceList($_POST['email'], $_POST['phone']));
            exit();
        }

        if ($act == 'send_preorder') {
            echo json_encode($this->model->sendPreorder($_POST));
            exit();
        }
    }

    /**
     * Корзина
     */
    public function cart(){
        $page = $this->model->getPageInfo('cart');

        $cart_info = $this->model->getCartInfo($_SESSION['cart']);
        if ($cart_info['error']) {
            $this->error = $cart_info['error'];
        }
        else {
            $page['page']['products'] = $cart_info['products'];
            $page['page']['cart_cost'] = $cart_info['cart_cost'];
            $page['page']['delivery_cost'] = $cart_info['delivery_cost'];
            $page['page']['total_cost'] = $cart_info['total_cost'];

            $goods = array();
            foreach ($cart_info['products'] as $product) {
                if ($product['goods']) {
                    $g = explode(',', $product['goods']);
                    $goods = array_merge($goods, $g);
                }
            }
            $goods = array_unique($goods);
            $page['page']['goods_info'] = $this->model->getGoods(implode(',', $goods));
        }

        CoreView::renderTemplate('Catalog/cart.html', [
            'settings' => $this->settings,
            'header_pages' => $this->header_pages,
            'catalog_menu_pages' => $this->catalog_menu_pages,
            'page' => $page['page'],
            'catalog_menu' => $this->catalog_menu,
            'params' => $this->params
        ]);
        exit();
    }

    /**
     * Оформить заказ
     */
    public function checkout(){
        $page = $this->model->getPageInfo('checkout');

        if (!count($_SESSION['cart'])) {
            header("Location: /cart");
            exit();
        }

        $product_errors = array();
        $cart_info = $this->model->getCartInfo($_SESSION['cart']);
        if ($cart_info['error']) {
            $this->error = $cart_info['error'];
        }
        else {
            foreach ($cart_info['products'] as $product_info) {
                if ($product_info['cart_count'] > $product_info['free_qty']) {
                    $product_errors[$product_info['id']] = $product_info['free_qty'];
                }
            }

            $page['page']['products'] = $cart_info['products'];
            $page['page']['cart_cost'] = $cart_info['cart_cost'];
            $page['page']['delivery_cost'] = $cart_info['delivery_cost'];
            $page['page']['total_cost'] = $cart_info['total_cost'];
        }

        $user = array();
        $user['addresses'] = array();
        if (isset($_SESSION['user']['id'])) {
            $get_user = $this->model->getSiteUser($_SESSION['user']['id']);
            if ($get_user['error']) {
                $this->error = $get_user['error'];
            }
            $user = $get_user['user'];

            $get_addresses = $this->model->getSiteUserAddresses();
            if ($get_addresses['error']) {
                $this->error = $get_addresses['error'];
            }

            $user['addresses'] = $get_addresses['addresses'];
        }

        $delivery_params = $this->model->getDeliveryParams();
        CoreView::renderTemplate('Catalog/checkout.html', [
            'settings' => $this->settings,
            'header_pages' => $this->header_pages,
            'catalog_menu_pages' => $this->catalog_menu_pages,
            'page' => $page['page'],
            'user' => $user,
            'catalog_menu' => $this->catalog_menu,
            'params' => $this->params,
            'product_errors' => count($product_errors),
            'delivery_params' => $delivery_params
        ]);
        exit();
    }

    /**
     * Страница результата оплаты заказа
     */
    public function payResult(){
        $error = "";
        $order = array();

        file_put_contents(dirname($_SERVER['DOCUMENT_ROOT'])."/logs/test_callback.txt", date('d.m.Y H:i:s')." payResult - ".json_encode($_GET)."\r\n", FILE_APPEND);

        $update_order = $this->updateOrderBySberOrderId($_GET['orderId']);
        if ($update_order['error']) {
            $error = $update_order['error'];
        }
        else {
            $order = $this->model->getOrderWithoutProducts($update_order['order_id'])['order'];
            $order['notice'] = $update_order['order_notice'];
        }

        $page = $this->model->getPageInfo('oplata_zakaza');
        CoreView::renderTemplate('Catalog/oplata_zakaza.html', [
            'settings' => $this->settings,
            'header_pages' => $this->header_pages,
            'catalog_menu_pages' => $this->catalog_menu_pages,
            'page' => $page['page'],
            'catalog_menu' => $this->catalog_menu,
            'params' => $this->params,
            'error' => $error,
            'order' => $order
        ]);
        exit();
    }


    /**
     * Обработчик уведомлений обратного вызова сбербанка
     */
    public function payCallback(){
        file_put_contents(dirname($_SERVER['DOCUMENT_ROOT'])."/logs/test_callback.txt", date('d.m.Y H:i:s')." payCallback - ".json_encode($_GET)."\r\n", FILE_APPEND);

        $data = "mdOrder;{$_GET['mdOrder']};operation;{$_GET['operation']};orderNumber;{$_GET['orderNumber']};status;{$_GET['status']};";
        $hmac = hash_hmac( 'sha256' , $data , Config::get('SBER_CALLBACK_KEY'));
        if (strtolower($_GET['checksum']) == $hmac) {
            file_put_contents(dirname($_SERVER['DOCUMENT_ROOT'])."/logs/test_callback.txt", date('d.m.Y H:i:s')." payCallback - {$_GET['checksum']} == $hmac\r\n", FILE_APPEND);
            $update_order = $this->updateOrderBySberOrderId($_GET['mdOrder']);
            if ($update_order['error']) {
                $this->model->addLog(array(
                    'log_code' => 0,
                    'user_id' => 1,
                    'history' => "{$update_order["order_id"]}",
                    'mod_id' => 0
                ));
            }
        }
        else {
            $this->model->addLog(array(
                'log_code' => 0,
                'user_id' => 1,
                'history' => "Некорректное уведомление ".json_encode($_GET),
                'mod_id' => 0
            ));
        }
    }

    /**
     * Обновить информацию об оплате заказа
     * @param $sberOrderId
     * @return array
     * "error" => string описание ошибки (в случае ошибки),
     * "order_notice" => string замечания по заказу,
     * "order_id" => int id заказа в нашей базе
     */
    public function updateOrderBySberOrderId($sberOrderId){
        $error = "";
        $order_notice = '';
        $order_id = 0;
        $order_info = $this->model->getOrderBySberOrderId($sberOrderId);

        file_put_contents(dirname($_SERVER['DOCUMENT_ROOT'])."/logs/test_callback.txt", date('d.m.Y H:i:s')." updateOrderBySberOrderId - $sberOrderId - ".json_encode($order_info)."\r\n", FILE_APPEND);

        if ($order_info['error']) {
            $error = "Что то пошло не так, заказ не найден.";
        }
        else {
            $order_id = $order_info['order']['id'];
        }

        if (!$error) {
            $order_payment_info = array();
            $sber_order_info = Payment::getSberOrderInfo($sberOrderId);

            file_put_contents(dirname($_SERVER['DOCUMENT_ROOT'])."/logs/test_callback.txt", date('d.m.Y H:i:s')." getSberOrderInfo - $sberOrderId - ".json_encode($sber_order_info)."\r\n", FILE_APPEND);

            if ($sber_order_info['error']) {
                $order_notice = $sber_order_info['error'];
                $order_notice .= " Способ оплаты заказа изменен на \"наличными или картой при получении\".";
                $order_payment_info = array(
                    'order_id' => $order_id,
                    'paymentOrderId' => '',
                    'paymentFormUrl' => '',
                    'paymentFormUrlLifeTime' => 0,
                    'payment_type' => 1,
                    'status' => 1
                );
            }
            else {
                $orderStatus = $sber_order_info['orderStatus'];
                /*
                0 - заказ зарегистрирован, но не оплачен;
                1 - предавторизованная сумма удержана (для двухстадийных платежей);
                2 - проведена полная авторизация суммы заказа;
                3 - авторизация отменена;
                4 - по транзакции была проведена операция возврата;
                5 - инициирована авторизация через сервер контроля доступа банка-эмитента;
                6 - авторизация отклонена.
                */
                if ($orderStatus != 0) {
                    if ($orderStatus == 1 || $orderStatus == 2) {
                        $order_payment_info = array(
                            'order_id' => $order_id,
                            'paymentOrderId' => $sberOrderId,
                            'paymentFormUrl' => '',
                            'paymentFormUrlLifeTime' => 0,
                            'payment_type' => 2,
                            'status' => 3
                        );
                    }
                    else if ($orderStatus == 3 || $orderStatus == 4) {
                        $order_notice = "По заказу осуществлен возврат средств.";
                        $order_payment_info = array(
                            'order_id' => $order_id,
                            'paymentOrderId' => $sberOrderId,
                            'paymentFormUrl' => '',
                            'paymentFormUrlLifeTime' => 0,
                            'payment_type' => 2,
                            'status' => 5
                        );
                    }
                    else {
                        $order_notice = "Оплата заказа отклонена банком.";
                        if ($order_info['order']['pvz_id']) {
                            $order_notice .= " Заказ отклонен.";
                            $order_payment_info = array(
                                'order_id' => $order_id,
                                'paymentOrderId' => $sberOrderId,
                                'paymentFormUrl' => '',
                                'paymentFormUrlLifeTime' => 0,
                                'payment_type' => 2,
                                'status' => 5
                            );
                        }
                        else {
                            $order_notice .= " Способ оплаты заказа изменен на \"наличными или картой при получении\".";
                            $order_payment_info = array(
                                'order_id' => $order_id,
                                'paymentOrderId' => $sberOrderId,
                                'paymentFormUrl' => '',
                                'paymentFormUrlLifeTime' => 0,
                                'payment_type' => 1,
                                'status' => 1
                            );
                        }

                    }
                }
            }

            if (count($order_payment_info)) {
                $result_update = $this->model->updateOrderPayment($order_payment_info, $order_notice);
                if ($result_update['error']) {
                    $error = $result_update['error'];
                }
            }
        }

        return array(
            "error" => $error,
            "order_notice" => $order_notice,
            "order_id" => $order_id
        );
    }

    /**
     * Выгрузка яндекса
     */
    public function katalog_yml(){
        $products = $this->model->getProducts()['products'];
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\r\n";
        $out .= '<yml_catalog date="' . date('Y-m-d H:i') . '">' . "\r\n";
        $out .= '<shop>' . "\r\n";
        $out .= '<name>Лотос Маркет</name>' . "\r\n";
        $out .= '<company>ИП Акаев Петр Валентинович</company>' . "\r\n";
        $out .= '<url>https://lotosdirect.ru/</url>' . "\r\n";

        $out .= '<currencies>' . "\r\n";
        $out .= '<currency id="RUR" rate="1"/>' . "\r\n";
        $out .= '</currencies>' . "\r\n";

        $out .= '<categories>' . "\r\n";
        foreach ($category as $row) {
            $out .= '<category id="' . $row['id'] . '" parentId="' . $row['parent'] . '">'
                . $row['name'] . '</category>' . "\r\n";
        }

        $out .= '</categories>' . "\r\n";

        $out .= '<offers>' . "\r\n";
        foreach ($prods as $row) {
            $out .= '<offer id="' . $row['id'] . '">' . "\r\n";

            // URL страницы товара на сайте магазина.
            $out .= '<url>http://site.com/prod/' . $row['id'] . '.html</url>' . "\r\n";

            // Цена, предполагается что в БД хранится цена и цена со скидкой.
            if (!empty($row['price_sale'])) {
                $out .= '<price>' . $row['price_sale'] . '</price>' . "\r\n";
                $out .= '<oldprice>' . $row['price'] . '</oldprice>' . "\r\n";
            } else {
                $out .= '<price>' . $row['price'] . '</price>' . "\r\n";
            }

            // Валюта товара.
            $out .= '<currencyId>RUR</currencyId>' . "\r\n";

            // ID категории.
            $out .= '<categoryId>' . $row['category'] . '</categoryId>' . "\r\n";

            // Изображения товара, до 10 ссылок.
            $out .= '<picture>http://site.com/img/1.jpg</picture>' . "\r\n";
            $out .= '<picture>http://site.com/img/2.jpg</picture>' . "\r\n";

            // Название товара.
            $out .= '<name>'.$row['name'].'</name>' . "\r\n";

            // Описание товара, максимум 3000 символов.
            $out .= '<description><![CDATA[' . stripslashes($row['text']) . ']]></description>' . "\r\n";
            $out .= '</offer>' . "\r\n";
        }

        $out .= '</offers>' . "\r\n";
        $out .= '</shop>' . "\r\n";
        $out .= '</yml_catalog>' . "\r\n";

        header('Content-Type: text/xml; charset=utf-8');
        echo $out;
        exit;
    }
}