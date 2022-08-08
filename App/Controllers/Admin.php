<?php
namespace App\Controllers;

use Core\CommonFunctions;
use Core\Controller as CoreController;
use Core\View as CoreView;
use App\Models\Admin as AdminModel;
use App\Models\Depot;
use App\Models\DepotIM;

class Admin extends CoreController{
    private $model;
    private $page_title;

    public function __construct(){
        parent::__construct();

        if (!isset($_GET['admin/login'])) {
            if (!isset($_SESSION['admin']['id'])) {
                header("Location: /admin/login");
                exit();
            }
        }

        $this->model = new AdminModel();

        $this->params['login'] = $_SESSION['admin']['login'];
        $this->params['menu'] = $this->model->getMenu();

        $this->page_title = "";
        $active_url = preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI']);
        foreach ($this->params['menu'] as $key => $menu_sections) {
            foreach ($menu_sections['items'] as $key2 =>  $menu_items) {
                if ($menu_items['url'] == $active_url) {
                    $this->params['menu'][$key]['items'][$key2]['active'] = true;
                    $this->params['menu'][$key]['active'] = true;
                    $this->page_title = $menu_items['title'];
                }
            }
        }

        $this->params['unrelated_products'] = $this->model->getUnrelatedProducts();
    }

    /**
     * Главная страница панели администратора
     * @return void
     */
    public function main(){
        $page = (isset($_SESSION['admin']['id'])) ? 'orders' : 'login';
        header("Location: /admin/$page");
        exit();
    }

    /**
     * Страница настроек сайта
     * @return void
     */
    public function settings(){
        $show_add_btn = false;

        if ($_SERVER['REQUEST_METHOD'] == 'POST'){
            $save_result = $this->model->saveSettings($_POST);
            if ($save_result['error']) {
                $_SESSION['error'] = $save_result['error'];
            }
            else {
                $_SESSION['notice'] = $save_result['notice'];
            }

            header("Location: /admin/settings");
            exit();
        }

        CoreView::renderTemplate('Admin/settings.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => $show_add_btn,
            'settings' => $this->settings
        ]);
    }

    /**
     * Страницы сайта
     * @return void
     */
    public function pages(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        $get_pages = $this->model->getPages();
        $this->error .= ($get_pages['error']) ?: '';
        $pages = $get_pages['pages'];

        if ($act == 'add') {
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_page = $this->model->addPage($_POST);
                if ($add_page['error']) {
                    $this->error .= $add_page['error'];
                    CoreView::renderTemplate('Admin/page.html', [
                        'params' => $this->params,
                        'title' => 'Добавление страницы',
                        'notice' => $this->notice,
                        'error' => $this->error,
                        'show_add_btn' => $show_add_btn,
                        'add_btn_link' => "/admin/pages?act=add",
                        'page' => $_POST,
                        'pages' => $pages,
                        'act' => 'add'
                    ]);
                }
                else {
                    $_SESSION['notice'] = $get_pages['notice'];
                    header("Location: /admin/pages?act=edit&id={$add_page['page']['id']}");
                    exit();
                }
            }
            else {
                CoreView::renderTemplate('Admin/page.html', [
                    'params' => $this->params,
                    'title' => 'Добавление страницы',
                    'notice' => $this->notice,
                    'error' => $this->error,
                    'show_add_btn' => $show_add_btn,
                    'add_btn_link' => "/admin/pages?act=add",
                    'page' => '',
                    'pages' => $pages,
                    'act' => 'add'
                ]);
            }
        }
        else if ($act == 'edit') {
            $id = (int)$_GET['id'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $update_page = $this->model->updatePage($_POST);
                $this->error .= ($update_page['error']) ?: '';
                $this->notice .= ($update_page['notice']) ?: '';
                $page = $update_page['page'];
            }
            else {
                $get_page = $this->model->getPage($id);
                $this->error .= ($get_page['error']) ?: '';
                $page = $get_page['page'];
            }

            CoreView::renderTemplate('Admin/page.html', [
                'params' => $this->params,
                'title' => 'Редактирование страницы',
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/pages?act=add",
                'page' => $page,
                'pages' => $pages,
                'act' => 'edit'
            ]);
        }
        else if ($act == 'add_to_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('pages', $id, 1);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/pages');
            exit();
        }
        else if ($act == 'remove_from_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('pages', $id, 0);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/pages');
            exit();
        }
        else if ($act == 'delete') {
            $id = (int)$_GET['id'];
            $del_res = $this->model->deletePage($id);
            if ($del_res['error']) {
                $_SESSION['error'] = $del_res['error'];
            }
            if ($del_res['notice']) {
                $_SESSION['notice'] = $del_res['notice'];
            }
            header('Location: /admin/pages');
            exit();
        }
        else {
            $page_title = $this->page_title." (".count($pages)." шт.)";
            CoreView::renderTemplate('Admin/pages.html', [
                'params' => $this->params,
                'title' => $page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/pages?act=add",
                'pages' => $pages
            ]);
        }
    }

    /**
     * Товары сайта
     * @return void
     */
    public function products(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        $sales_pages = $this->model->getPageChilds(54);
        $sales_pages = $sales_pages['pages'];

        $get_products = $this->model->getProducts();
        $this->error .= ($get_products['error']) ?: '';
        $products = $get_products['products'];

        if ($act == 'add') {
            $product = array();
            if (isset($_GET['from_id'])) {
                $get_product = $this->model->getProduct($_GET['from_id']);
                $this->error .= ($get_product['error']) ?: '';
                $product = $get_product['product'];
                $product['id'] = 0;
                $product['qty'] = 0;
                $product['photos'] = array();
                $product['main_photo'] = array();
                $product['free_qty'] = 0;
                $product['url'] = '';
                $product['full_url'] = '';
            }

            $get_pages = $this->model->getPages();
            $this->error .= ($get_pages['error']) ?: '';
            $pages = $get_pages['pages'];

            $ref_chars = $this->model->getRefChars();
            $this->error .= ($ref_chars['error']) ?: '';
            $ref_chars = $ref_chars['ref_chars'];

            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_product = $this->model->addProduct($_POST);
                $product = $_POST;
                $product['chars'] = array();
                foreach($_POST['chars'] as $char_id => $value){
                    $product['chars'][$char_id]['value'] = $value;
                }
                if ($add_product['error']){
                    $this->error .= $add_product['error'];
                    CoreView::renderTemplate('Admin/product.html', [
                        'params' => $this->params,
                        'title' => "Добавление товара",
                        'notice' => $this->notice,
                        'error' => $this->error,
                        'show_add_btn' => $show_add_btn,
                        'add_btn_link' => "/admin/products?act=add",
                        'pages' => $pages,
                        'product' => $product,
                        'ref_chars' => $ref_chars,
                        'act' => 'add',
                        'sales_pages' => $sales_pages,
                        'products' => $products
                    ]);
                }
                else {
                    $_SESSION['notice'] = $add_product['notice'];
                    header("Location: /admin/products?act=edit&id={$add_product['product']['id']}");
                    exit();
                }
            }
            else {
                CoreView::renderTemplate('Admin/product.html', [
                    'params' => $this->params,
                    'title' => "Добавление товара",
                    'notice' => $this->notice,
                    'error' => $this->error,
                    'show_add_btn' => $show_add_btn,
                    'add_btn_link' => "/admin/products?act=add",
                    'pages' => $pages,
                    'product' => $product,
                    'ref_chars' => $ref_chars,
                    'act' => 'add',
                    'sales_pages' => $sales_pages,
                    'products' => $products
                ]);
            }
        }
        else if ($act == 'edit') {
            $id = (int)$_GET['id'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $update_product = $this->model->updateProduct($_POST);
                $this->error .= ($update_product['error']) ?: '';
                $this->notice .= ($update_product['notice']) ?: '';
                $product = $update_product['product'];
            }
            else {
                $get_product = $this->model->getProduct($id);
                $this->error .= ($get_product['error']) ?: '';
                $product = $get_product['product'];
            }

            $get_pages = $this->model->getPages();
            if ($get_pages['error']) {
                $this->error .= $get_pages['error'];
            }
            $pages = $get_pages['pages'];

            $ref_chars = $this->model->getRefChars();
            $this->error .= ($ref_chars['error']) ?: '';
            $ref_chars = $ref_chars['ref_chars'];

            unset($products[$product['id']]);

            CoreView::renderTemplate('Admin/product.html', [
                'params' => $this->params,
                'title' => 'Редактирование товара',
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/products?act=add",
                'pages' => $pages,
                'product' => $product,
                'ref_chars' => $ref_chars,
                'act' => 'edit',
                'sales_pages' => $sales_pages,
                'products' => $products
            ]);
        }
        else if ($act == 'add_to_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('products', $id, 1);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/products');
            exit();
        }
        else if ($act == 'remove_from_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('products', $id, 0);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/products');
            exit();
        }
        else if ($act == 'delete') {
            $id = (int)$_GET['id'];
            $del_res = $this->model->deleteProduct($id);
            if ($del_res['error']) {
                $_SESSION['error'] = $del_res['error'];
            }
            if ($del_res['notice']) {
                $_SESSION['notice'] = $del_res['notice'];
            }
            header('Location: /admin/products');
            exit();
        }
        else {
            $page_title = $this->page_title." (".count($products)." шт.)";
            CoreView::renderTemplate('Admin/products.html', [
                'params' => $this->params,
                'title' => $page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/products?act=add",
                'products' => $products
            ]);
        }
    }

    /**
     * Харатеристики товаров
     * @return void
     */
    public function ref_chars(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        if ($act == 'add') {
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_res = $this->model->addRefChar($_POST);
                if ($add_res['error']) {
                    $this->error .= $add_res['error'];

                    $ref_chars = $this->model->getRefChars();
                    $this->error .= ($ref_chars['error']) ?: '';
                    $ref_chars = $ref_chars['ref_chars'];

                    CoreView::renderTemplate('Admin/ref_char.html', [
                        'params' => $this->params,
                        'title' => "Добавление характеристики",
                        'notice' => $this->notice,
                        'error' => $this->error,
                        'show_add_btn' => $show_add_btn,
                        'add_btn_link' => "/admin/ref_chars?act=add",
                        'ref_chars' => $ref_chars,
                        'ref_char' => "",
                        'act' => 'add'
                    ]);
                }
                else {
                    $ref_char_id = $add_res['ref_char_id'];
                    header("Location: /admin/ref_chars?act=edit&id=$ref_char_id");
                    exit;
                }
            }
            else {
                $ref_chars = $this->model->getRefChars();
                $this->error .= ($ref_chars['error']) ?: '';
                $ref_chars = $ref_chars['ref_chars'];

                CoreView::renderTemplate('Admin/ref_char.html', [
                    'params' => $this->params,
                    'title' => "Добавление характеристики",
                    'notice' => $this->notice,
                    'error' => $this->error,
                    'show_add_btn' => $show_add_btn,
                    'add_btn_link' => "/admin/ref_chars?act=add",
                    'ref_chars' => $ref_chars,
                    'ref_char' => "",
                    'act' => 'add'
                ]);
            }
        }
        else if ($act == 'edit') {
            $id = (int)$_GET['id'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $ref_char = $this->model->updateRefChar($_POST);
                if ($ref_char['error']) {
                    $this->error = $ref_char['error'];
                    $ref_char = $_POST;
                }
                else if ($ref_char['notice']){
                    $_SESSION['notice'] = $ref_char['notice'];
                    header("Location: /admin/ref_chars?act=edit&id=$id");
                    exit;
                }
            }
            else {
                $ref_char = $this->model->getRefChar($id);
                $this->error .= ($ref_char['error']) ?: '';
                $ref_char = $ref_char['ref_char'];
            }

            $ref_chars = $this->model->getRefChars();
            $this->error .= ($ref_chars['error']) ?: '';
            $ref_chars = $ref_chars['ref_chars'];

            CoreView::renderTemplate('Admin/ref_char.html', [
                'params' => $this->params,
                'title' => 'Редактирование характеристики',
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/ref_chars?act=add",
                'ref_chars' => $ref_chars,
                'ref_char' => $ref_char,
                'act' => 'edit'
            ]);
        }
        else if ($act == 'add_to_achive') {
            $id = (int)$_GET['id'];
            $set = $this->model->setArchive('ref_chars', $id, 1);
            if ($set['error']) $_SESSION['error'] = $set['error'];
            if ($set['notice']) $_SESSION['notice'] = $set['notice'];
            header('Location: /admin/ref_chars');
            exit();
        }
        else if ($act == 'remove_from_achive') {
            $id = (int)$_GET['id'];
            $set = $this->model->setArchive('ref_chars', $id, 0);
            if ($set['error']) $_SESSION['error'] = $set['error'];
            if ($set['notice']) $_SESSION['notice'] = $set['notice'];
            header('Location: /admin/ref_chars');
            exit();
        }
        else if ($act == 'delete') {
            $id = (int)$_GET['id'];
            $delete = $this->model->deleteRefChars($id);
            if ($delete['error']) $_SESSION['error'] = $delete['error'];
            if ($delete['notice']) $_SESSION['notice'] = $delete['notice'];
            header('Location: /admin/ref_chars');
            exit();
        }
        else {
            $ref_chars = $this->model->getRefChars();
            $this->error .= ($ref_chars['error']) ?: '';
            $ref_chars = $ref_chars['ref_chars'];

            CoreView::renderTemplate('Admin/ref_chars.html', [
                'params' => $this->params,
                'title' => $this->page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/ref_chars?act=add",
                'ref_chars' => $ref_chars
            ]);
        }
    }

    /**
     * Значения харатеристик товаров
     * @return void
     */
    public function ref_chars_values(){
        $show_add_btn = false;

        $ref_chars_values = $this->model->getRefCharsValues();
        $this->error .= ($ref_chars_values['error']) ?: '';
        $ref_chars_values = $ref_chars_values['ref_chars_values'];

        CoreView::renderTemplate('Admin/ref_chars_values.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => $show_add_btn,
            'add_btn_link' => "",
            'ref_chars_values' => $ref_chars_values
        ]);
    }

    /**
     * Обработчик асинхронных запросов
     */
    public function ajax(){
        $act = $_POST['act'];
        $error = '';
        if ($act == 'get_ref_char_values_list') {
            $char_id = $_POST['char_id'];
            $value = $_POST['value'];

            $ref_char_values = $this->model->getRefCharValues($char_id, $value);

            $html = '';
            if ($ref_char_values['error']) {
                $error = $ref_char_values['error'];
            }
            else {
                foreach($ref_char_values as $ref_char_value) {
                    $html .= CoreView::returnTemplate('Admin/inc/check_value.html', [
                        "ref_char_value_id" => $ref_char_value['id'],
                        "ref_char_value_value" => $ref_char_value['value']
                    ]);
                }
            }

            $result = array();
            $result['error'] = $error;
            $result['html'] = $html;
            echo json_encode($result);
            exit();
        }

        if ($act == 'set_order_completed') {
            $id = (int)$_POST['id'];

            $order_info = $this->model->getOrder($id);
            if ($order_info['error']){
                $error = $order_info['error'];
            }
            else {
                foreach($order_info['order']['products'] as $order_product) {
                    $history_info = array();
                    $history_info['dir'] = 2;
                    $history_info['product_id'] = $order_product['product_id'];
                    $history_info['qty'] = $order_product['count'];
                    $history_info['order_id'] = $id;
                    $history_info['user_id'] = $_SESSION['admin']['id'];
                    $add_history = $this->model->addHistory($history_info);
                    if ($add_history['error']) {
                        $error = $add_history['error'];
                    }
                }

                if (!$error) {
                    $set_completed = $this->model->setOrderCompleted($id);
                    if ($set_completed['error']) {
                        $error = $set_completed['error'];
                    }
                }
            }

            $result = array();
            $result['error'] = $error;
            echo json_encode($result);
            exit();
        }

        if ($act == 'set_order_declined') {
            $id = (int)$_POST['id'];

            $set_declined = $this->model->setOrderDeclined($id);
            if ($set_declined['error']) {
                $error = $set_declined['error'];
            }

            $result = array();
            $result['error'] = $error;
            echo json_encode($result);
            exit();
        }

        if ($act == 'update_rate') {
            $error = '';
            $table = $_POST['table'];
            $array_rate = $_POST['array_rate'];
            if ($array_rate) {
                $update = $this->model->updateRate($table, $array_rate);
                if ($update['error']) {
                    $error = $update['error'];
                }
            }

            $result = array();
            $result['error'] = $error;
            echo json_encode($result);
            exit();
        }

        if ($act == 'edit_order'){
            $error = '';
            $order_id = $_POST['order_id'];
            $field = $_POST['field'];
            $value = $_POST['value'];
            $edit = $this->model->editOrderField($order_id, $field, $value);
            if ($edit['error']) {
                $error = $edit['error'];
            }

            $result = array();
            $result['error'] = $error;
            $result['success'] = 'Сохранено';
            echo json_encode($result);
            exit();
        }

        if ($act == 'add_order_product'){
            $error = '';
            $order_id = (int)$_POST['order_id'];

            $products = $this->model->getFullProducts();
            $products = $products['products'];

            $add = $this->model->addOrderProduct($order_id);
            $order_product_id = $add['order_product_id'];

            $product = array();
            $product['count'] = 0;
            $product['id'] = $order_product_id;
            $html = CoreView::returnTemplate('Admin/inc/order_item.html', [
                "loop_index" => 0,
                "product" => $product,
                "products" => $products,
                "can_edit" => 1,
                "can_edit_products" => 1
            ]);

            $result = array();
            $result['error'] = $error;
            $result['html'] = $html;
            echo json_encode($result);
            exit();
        }

        if ($act == 'update_order_product'){
            $error = '';
            $order_product_id = (int)$_POST['order_product_id'];
            $product_id = (int)$_POST['product_id'];
            $count = (int)$_POST['count'];

            $order_product_info = array();
            $change_count = 0;
            $update = $this->model->updateOrderProduct($order_product_id, $product_id, $count);
            if ($update['error']) {
                $error = $update['error'];
            }
            else {
                $order_product_info = $update['order_product_info'];
                $change_count = $update['change_count'];
            }

            $result = array();
            $result['error'] = $error;
            $result['order_product_info'] = $order_product_info;
            $result['change_count'] = $change_count;
            echo json_encode($result);
            exit();
        }

        if ($act == 'delete_order_product'){
            $error = '';
            $order_product_id = (int)$_POST['order_product_id'];

            $delete = $this->model->deleteOrderProduct($order_product_id);
            if ($delete['error']) {
                $error = $delete['error'];
            }

            $result = array();
            $result['error'] = $error;
            echo json_encode($result);
            exit();
        }

        if ($act == 'calc_order') {
            $error = '';
            $order_id = (int)$_POST['order_id'];

            $calc = $this->model->calcOrder($order_id);
            if ($calc['error']) {
                $error = $calc['error'];
            }

            $result = array();
            $result['error'] = $error;
            $result['delivery_cost'] = $calc['delivery_cost'];
            $result['cart_cost'] = $calc['cart_cost'];
            $result['total_cost'] = $calc['total_cost'];
            echo json_encode($result);
            exit();
        }

        if ($act == 'edit_product_field'){
            $error = '';
            $product_id = $_POST['product_id'];
            $field = $_POST['field'];
            $value = $_POST['value'];
            $edit = $this->model->editProductField($product_id, $field, $value);
            if ($edit['error']) {
                $error = $edit['error'];
            }

            $result = array();
            $result['error'] = $error;
            $result['success'] = 'Сохранено';
            echo json_encode($result);
            exit();
        }

        if ($act == 'edit_site_user_field'){
            $error = '';
            $site_user_id = $_POST['site_user_id'];
            $field = $_POST['field'];
            $value = $_POST['value'];
            $edit = $this->model->editSiteUserField($site_user_id, $field, $value);
            if ($edit['error']) {
                $error = $edit['error'];
            }

            $result = array();
            $result['error'] = $error;
            $result['success'] = 'Сохранено';
            echo json_encode($result);
            exit();
        }

        if ($act == 'send_edit_mail'){
            $error = '';
            $order_id = $_POST['order_id'];
            $type = $_POST['type'];
            $to = '';

            if ($type == 'user') {
                $get_order = $this->model->getOrder($order_id);
                if ($get_order['error']) {
                    $error = $get_order['error'];
                }
                else {
                    $order_info = $get_order['order']['order'];
                    $to = $order_info['email'];
                }
            }
            else {
                $get_settings = $this->model->getSettings();
                if ($get_settings['error']) {
                    $error = $get_settings['error'];
                }
                else {
                    $to = $this->settings['mailorders'];
                }
            }

            if ($to) {
                $subject = "Информация о заказе №$order_id на сайте {$_SERVER['SERVER_NAME']}";
                $send_order = $this->model->sendOrderMail($to, $subject, $order_id);
                if ($send_order['error']) {
                    $error = $send_order['error'];
                }
            }

            $result = array();
            $result['error'] = $error;
            $result['success'] = 'Письмо успешно отправлено';
            echo json_encode($result);
            exit();
        }

        if ($act == 'update_temp_statuses') {
            $statuses = $_POST['statuses'];
            $uid = $_SESSION['admin']['id'];
            $update = $this->model->updateTempStatuses($uid, $statuses);
            if ($update['error']) {
                $error = $update['error'];
            }

            $result = array();
            $result['error'] = $error;
            echo json_encode($result);
            exit();
        }

        if ($act == 'save_depot_product_ids') {
            echo json_encode($this->model->updateDepotProductIds($_POST['depot_products']));
            exit();
        }

        if ($act == 'create_depot_products') {
            //note создание товаров
            $depot_products = Depot::getAssortment();
            $products = $this->model->getProducts()['products'];

            foreach ($depot_products as $depot_key => $depot_product){
                foreach($products as $key => $product) {
                    if ($depot_product['id'] == $product['depot_id']) {
                        unset($depot_products[$depot_key]);
                    }
                }
            }

            $depot_pages = $this->model->getDepotPages();
            $products_to_create = array();
            foreach ($depot_products as $depot_product) {
                $product = Depot::getDepotProduct($depot_product['id']);
                if ($product['paymentItemType'] == 'GOOD') {
                    $parent_id = $depot_pages[$product['productFolder']['id']] ?? 0;
                    $products_to_create[] = array(
                        'qty' => ($depot_product['quantity'] > 0) ? $depot_product['quantity'] : 0,
                        'parent_id' => $parent_id,
                        'archived' => 1,
                        'title' => $product['name'],
                        'url' => CommonFunctions::translit($product['name']),
                        'price' => $product['salePrices'][0]['value'] / 100,
                        'barcode' => $product['barcodes'][0]['ean13'],
                        'ct' => ($product['uom']['name'] == 'шт') ? 1 : 2,
                        'depot_id' => $depot_product['id'],
                        'depot_title' => $product['name'],
                        'paymentItemType' => $product['paymentItemType']
                    );
                }
            }

            echo json_encode($this->model->createDepotProducts($products_to_create));
            exit();
        }

        if ($act == 'save_page_bounds') {
            echo json_encode($this->model->saveDepotPageBounds($_POST));
            exit();
        }

        if ($act == 'save_product_bounds') {
            echo json_encode($this->model->saveDepotProductBounds($_POST));
            exit();
        }

        if ($act == 'save_counterparty_bounds') {
            $result = array('error' => '', 'notice' => '');
            $edit = $this->model->editSiteUserField($_POST['site_user_id'], 'depot_id', $_POST['depot_id']);
            if ($edit['error']) {
                $result['error'] = $edit['error'];
            }
            $edit = $this->model->editSiteUserField($_POST['site_user_id'], 'depot_title', $_POST['depot_title']);
            if ($edit['error']) {
                $result['error'] = $edit['error'];
            }

            echo json_encode($result);
            exit();
        }

        if ($act == 'update_depot_products') {
            echo json_encode($this->model->updateDepotProducts());
            exit();
        }

        if ($act == 'create_depot_order') {
            $depot = new DepotIM();
            echo json_encode($depot->createDepotOrder($_POST['order_id']));
            exit();
        }
    }

    /**
     * Вход пользователя
     */
    public function login(){
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $login = $_POST['login'];
            $password = $_POST['password'];

            $check_user = $this->model->checkUser($login, $password);
            if ($check_user['check']) {
                $user = $check_user['user'];
                $_SESSION['admin']['id'] = $user['id'];
                $_SESSION['admin']['login'] = $login;

                $data = array();
                $data['log_code'] = 4;
                $data['user_id'] = $user['id'];
                $data['history'] = "Вход пользователя (ip ".$_SERVER['REMOTE_ADDR'].").";
                $data['mod_id'] = $user['id'];
                $add_log = $this->model->addLog($data);
                if ($add_log['error']) {
                    $error = $add_log['error'];
                }
                else {
                    header('Location: /admin');
                    exit;
                }
            }
            else {
                $error = "Введены неверные данные";
            }
        }
        else {
            $login = '';
            $password = '';
        }

        $params = array();
        $params['login'] = $login;
        $params['password'] = $password;
        $params['error'] = $error;
        CoreView::renderTemplate('Admin/login.html', $params);
    }

    /**
     * Выход пользователя
     */
    public function logout(){
        unset($_SESSION['admin']);
        header('Location: /admin/login');
    }

    /**
     * Пользователи
     */
    public function users(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        if ($act == 'add') {
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_user = $this->model->addUser($_POST);
                if ($add_user['error']) {
                    $this->error .= $add_user['error'];
                }
                else {
                    $_SESSION['notice'] = $add_user['notice'];
                    header("Location: /admin/users?act=edit&id={$add_user['user']['id']}");
                    exit();
                }
            }
            else {
                CoreView::renderTemplate('Admin/user.html', [
                    'params' => $this->params,
                    'title' => 'Редактирование пользователя',
                    'notice' => $this->notice,
                    'error' => $this->error,
                    'show_add_btn' => $show_add_btn,
                    'add_btn_link' => "/admin/users?act=add",
                    'user' => "",
                    'users' => "",
                    'act' => 'add'
                ]);
            }
        }
        else if ($act == 'edit') {
            $id = (int)$_GET['id'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $update_user = $this->model->updateUser($_POST);
                $this->error .= ($update_user['error']) ?: '';
                $this->notice .= ($update_user['notice']) ?: '';
                $user = $update_user['user'];
            }
            else {
                $get_user = $this->model->getUser($id);
                $this->error .= ($get_user['error']) ?: '';
                $user = $get_user['user'];
            }

            CoreView::renderTemplate('Admin/user.html', [
                'params' => $this->params,
                'title' => 'Редактирование пользователя',
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/users?act=add",
                'user' => $user,
                'users' => "",
                'act' => 'edit'
            ]);
        }
        else {
            $get_users = $this->model->getUsers();
            $this->error .= ($get_users['error']) ?: '';
            $users = $get_users['users'];

            $page_title = $this->page_title." (".count($users)." шт.)";
            CoreView::renderTemplate('Admin/users.html', [
                'params' => $this->params,
                'title' => $page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/users?act=add",
                'users' => $users
            ]);
        }
    }

    /**
     * Заказы
     */
    public function orders(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        if ($act == 'add') {
            $order = $this->model->createOrder(array('user_id' => 0));
            if ($order['error']) {
                $this->error = $order['error'];
                CoreView::renderTemplate('Admin/order.html', [
                    'params' => $this->params,
                    'title' => "Добавление заказа",
                    'notice' => $this->notice,
                    'error' => $this->error,
                    'show_add_btn' => $show_add_btn,
                    'add_btn_link' => "/admin/orders?act=add",
                    'orders' => "",
                    'order' => array(),
                    'products' => array()
                ]);
            }
            else {
                header("Location: /admin/orders?act=show&id={$order['order_id']}");
                exit();
            }
        }
        else if ($act == 'show') {
            $id = (int)$_GET['id'];

            $products = $this->model->getFullProducts();
            $products = $products['products'];

            $get_order = $this->model->getOrder($id);
            $this->error .= ($get_order['error']) ?: '';
            $order = $get_order['order'];
            $order['order']['logs'] = $this->model->getOrderLogs($id)['logs'];

            $delivery_time_items = $this->model->getDeliveryTimeItems();
            foreach($delivery_time_items as &$item){
                $item['default'] = false;
                if ($item['time'] == $order['order']['delivery_time']){
                    $item['default'] = true;
                }
            }

            //товарный чек
            $order['order']['receipt_link'] = '';
            if ($order['order']['depot_id']) {
                $receipt_link = Depot::getOrderReceiptLink($order['order']['depot_id']);
                $order['order']['receipt_link'] = $receipt_link['link'];
                $this->error .= $receipt_link['error'];
            }

            CoreView::renderTemplate('Admin/order.html', [
                'params' => $this->params,
                'title' => "Информация о заказе №$id",
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/orders?act=add",
                'orders' => "",
                'order' => $order,
                'products' => $products,
                'delivery_time_items' => $delivery_time_items
            ]);
        }
        else {
            $statuses = $this->model->getTempStatuses();
            $get_orders = $this->model->getOrders($statuses);
            $this->error .= ($get_orders['error']) ?: '';
            $orders = $get_orders['orders'];

            $page_title = $this->page_title." (".count($orders)." шт.)";
            CoreView::renderTemplate('Admin/orders.html', [
                'params' => $this->params,
                'title' => $page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/orders?act=add",
                'orders' => $orders,
                'statuses' => $statuses
            ]);
        }
    }

    /**
     * История
     */
    public function histories(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        if ($act == 'add') {
            $products = $this->model->getProducts();
            if ($products['error']) {
                $this->error .= $products['error'];
            }
            $products = $products['products'];

            $sort_products = array();
            foreach($products as $product){
                if ($product['archived'] == 0) {
                    $sort_products[$product['id']] = $product['full_title']." -> ".$product['title'];
                }
            }
            asort($sort_products);

            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $_POST['user_id'] = $_SESSION['admin']['id'];
                $add_history = $this->model->addHistory($_POST);
                if ($add_history['error']) {
                    $this->error .= $add_history['error'];
                    CoreView::renderTemplate('Admin/history.html', [
                        'params' => $this->params,
                        'title' => "Добавление движения",
                        'notice' => $this->notice,
                        'error' => $this->error,
                        'show_add_btn' => $show_add_btn,
                        'products' => $sort_products,
                        'history' => $_POST,
                        'act' => 'add'
                    ]);
                }
                else {
                    $_SESSION['notice'] = $add_history['notice'];
                    header("Location: /admin/histories");
                    exit();
                }
            }
            else {
                $history['product_id'] = (isset($_GET['product_id'])) ? $_GET['product_id'] : 0;
                $history['dir'] = (isset($_GET['product_id'])) ? 1 : 0;
                CoreView::renderTemplate('Admin/history.html', [
                    'params' => $this->params,
                    'title' => "Добавление движения",
                    'notice' => $this->notice,
                    'error' => $this->error,
                    'show_add_btn' => $show_add_btn,
                    'products' => $sort_products,
                    'history' => $history,
                    'act' => 'add'
                ]);
            }
        }
        else {
            $get_histories = $this->model->getHistories();
            $this->error .= ($get_histories['error']) ?: '';
            $histories = $get_histories['histories'];

            $page_title = $this->page_title." (".count($histories)." шт.)";
            CoreView::renderTemplate('Admin/histories.html', [
                'params' => $this->params,
                'title' => $page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/histories?act=add",
                'histories' => $histories
            ]);
        }
    }

    /**
     * Логи
     */
    public function logs(){
        $show_add_btn = false;

        $get_logs = $this->model->getLogs();
        $this->error .= ($get_logs['error']) ?: '';
        $logs = $get_logs['logs'];

        $page_title = $this->page_title." (".count($logs)." шт.)";
        CoreView::renderTemplate('Admin/logs.html', [
            'params' => $this->params,
            'title' => $page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => $show_add_btn,
            'logs' => $logs
        ]);
    }


    public function postacceptor(){
        $folder = $_SERVER['DOCUMENT_ROOT']."/images/uploads/";
        reset($_FILES);
        $temp = current($_FILES);
        if (is_uploaded_file($temp['tmp_name'])){
            if (preg_match("/([^\w\s\d\-_~,;:\[\]\(\).])|([\.]{2,})/", $temp['name'])) {
                header("HTTP/1.1 400 Invalid file name.");
                return;
            }

            $allowed_types = array('image/png', 'image/jpeg');
            if (!in_array($temp['type'], $allowed_types)){
                header("HTTP/1.1 400 Invalid extension.");
                return;
            }

            $file_extention = pathinfo($temp['name'], PATHINFO_EXTENSION);
            $file_name = time()."_".rand(0, 1000).".".$file_extention;
            $filetowrite = $folder.$file_name;

            $move = move_uploaded_file($temp['tmp_name'], $filetowrite);
            if ($move){
                echo json_encode(array('location' => "/images/uploads/".$file_name));
            }
            else {
                header("HTTP/1.1 400 Invalid extension.");
            }
        }
        else {
            header("HTTP/1.1 500 Server Error");
        }
    }

    /**
     * Баннеры
     * @return void
     */
    public function banners(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        if ($act == 'add') {
            $banner = array();
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_res = $this->model->addBanner($_POST);
                if ($add_res['error']) {
                    $this->error = $add_res['error'];
                    $banner = $_POST;
                }
                else {
                    $_SESSION['notice'] = $add_res['notice'];
                    header("Location: /admin/banners?act=edit&id={$add_res['id']}");
                    exit();
                }
            }

            CoreView::renderTemplate('Admin/banner.html', [
                'params' => $this->params,
                'title' => "Добавление баннера",
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/banners?act=add",
                'banner' => $banner,
                'act' => 'add'
            ]);
        }
        else if ($act == 'edit') {
            $id = (int)$_GET['id'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $banner = $this->model->updateBanner($_POST);
                if ($banner['error']) {
                    $this->error = $banner['error'];
                    $banner = $_POST;
                }
                else if ($banner['notice']){
                    $_SESSION['notice'] = $banner['notice'];
                    header("Location: /admin/banners?act=edit&id=$id");
                    exit;
                }
            }
            else {
                $banner = $this->model->getBanner($id);
                $this->error .= ($banner['error']) ?: '';
                $banner = $banner['banner'];
            }

            CoreView::renderTemplate('Admin/banner.html', [
                'params' => $this->params,
                'title' => 'Редактирование баннера',
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/banners?act=add",
                'banner' => $banner,
                'act' => 'edit'
            ]);
        }
        else if ($act == 'add_to_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('banners', $id, 1);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/banners');
            exit();
        }
        else if ($act == 'remove_from_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('banners', $id, 0);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/banners');
            exit();
        }
        else if ($act == 'delete') {
            $id = (int)$_GET['id'];
            $delete = $this->model->deleteBanner($id);
            if ($delete['error']) $_SESSION['error'] = $delete['error'];
            if ($delete['notice']) $_SESSION['notice'] = $delete['notice'];
            header('Location: /admin/banners');
            exit();
        }
        else {
            $banners = $this->model->getBanners();
            $this->error .= ($banners['error']) ?: '';
            $banners = $banners['banners'];

            CoreView::renderTemplate('Admin/banners.html', [
                'params' => $this->params,
                'title' => $this->page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/banners?act=add",
                'banners' => $banners
            ]);
        }
    }

    /**
     * Баннеры каталога
     * @return void
     */
    public function banners_catalog(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        $site_pages = $this->model->getSitePages();

        if ($act == 'add') {
            $banner = array();
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_res = $this->model->addBannerCatalog($_POST);
                if ($add_res['error']) {
                    $this->error = $add_res['error'];
                    $banner = $_POST;
                }
                else {
                    $_SESSION['notice'] = $add_res['notice'];
                    header("Location: /admin/banners_catalog?act=edit&id={$add_res['id']}");
                    exit();
                }
            }

            CoreView::renderTemplate('Admin/banner_catalog.html', [
                'params' => $this->params,
                'title' => "Добавление баннера",
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/banners_catalog?act=add",
                'banner' => $banner,
                'act' => 'add',
                'site_pages' => $site_pages
            ]);
        }
        else if ($act == 'edit') {
            $id = (int)$_GET['id'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $banner = $this->model->updateBannerCatalog($_POST);
                if ($banner['error']) {
                    $this->error = $banner['error'];
                    $banner = $_POST;
                }
                else if ($banner['notice']){
                    $_SESSION['notice'] = $banner['notice'];
                    header("Location: /admin/banners_catalog?act=edit&id=$id");
                    exit;
                }
            }
            else {
                $banner = $this->model->getBannerCatalog($id);
                $this->error .= ($banner['error']) ?: '';
                $banner = $banner['banner'];
            }

            CoreView::renderTemplate('Admin/banner_catalog.html', [
                'params' => $this->params,
                'title' => 'Редактирование баннера',
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/banners_catalog?act=add",
                'banner' => $banner,
                'act' => 'edit',
                'site_pages' => $site_pages
            ]);
        }
        else if ($act == 'add_to_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('banners_catalog', $id, 1);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/banners_catalog');
            exit();
        }
        else if ($act == 'remove_from_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('banners_catalog', $id, 0);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/banners_catalog');
            exit();
        }
        else if ($act == 'delete') {
            $id = (int)$_GET['id'];
            $delete = $this->model->deleteBannerCatalog($id);
            if ($delete['error']) $_SESSION['error'] = $delete['error'];
            if ($delete['notice']) $_SESSION['notice'] = $delete['notice'];
            header('Location: /admin/banners_catalog');
            exit();
        }
        else {
            $banners = $this->model->getBannersCatalog();
            $this->error .= ($banners['error']) ?: '';
            $banners = $banners['banners'];

            CoreView::renderTemplate('Admin/banners_catalog.html', [
                'params' => $this->params,
                'title' => $this->page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/banners_catalog?act=add",
                'banners' => $banners
            ]);
        }
    }

    /**
     * Бренды
     * @return void
     */
    public function brands(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        $brand_char_values = $this->model->getBrandsCharsValues();
        if ($brand_char_values['error']) {
            $this->error = $brand_char_values['error'];
        }
        $brand_char_values = $brand_char_values['values'];

        if ($act == 'add') {
            $brand = array();
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_res = $this->model->addBrand($_POST);
                if ($add_res['error']) {
                    $this->error = $add_res['error'];
                    $brand = $_POST;
                }
                else {
                    $_SESSION['notice'] = $add_res['notice'];
                    header("Location: /admin/brands?act=edit&id={$add_res['id']}");
                    exit();
                }
            }

            CoreView::renderTemplate('Admin/brand.html', [
                'params' => $this->params,
                'title' => "Добавление бренда",
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/banners?act=add",
                'brand' => $brand,
                'act' => 'add',
                'brand_char_values' => $brand_char_values
            ]);
        }
        else if ($act == 'edit') {
            $id = (int)$_GET['id'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $brand = $this->model->updateBrand($_POST);
                if ($brand['error']) {
                    $this->error = $brand['error'];
                    $brand = $_POST;
                }
                else if ($brand['notice']){
                    $_SESSION['notice'] = $brand['notice'];
                    header("Location: /admin/brands?act=edit&id=$id");
                    exit;
                }
            }
            else {
                $brand = $this->model->getBrand($id);
                $this->error .= ($brand['error']) ?: '';
                $brand = $brand['brand'];
            }

            CoreView::renderTemplate('Admin/brand.html', [
                'params' => $this->params,
                'title' => 'Редактирование бренда',
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/brands?act=add",
                'brand' => $brand,
                'act' => 'edit',
                'brand_char_values' => $brand_char_values
            ]);
        }
        else if ($act == 'add_to_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('brands', $id, 1);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/brands');
            exit();
        }
        else if ($act == 'remove_from_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('brands', $id, 0);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/brands');
            exit();
        }
        else if ($act == 'delete') {
            $id = (int)$_GET['id'];
            $delete = $this->model->deleteBrand($id);
            if ($delete['error']) $_SESSION['error'] = $delete['error'];
            if ($delete['notice']) $_SESSION['notice'] = $delete['notice'];
            header('Location: /admin/brands');
            exit();
        }
        else {
            $brands = $this->model->getBrands();
            $this->error .= ($brands['error']) ?: '';
            $brands = $brands['brands'];

            CoreView::renderTemplate('Admin/brands.html', [
                'params' => $this->params,
                'title' => $this->page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/brands?act=add",
                'brands' => $brands
            ]);
        }
    }

    /**
     * Пользователи сайтв
     * @return void
     */
    public function site_users(){
        $show_add_btn = false;

        $site_users = $this->model->getSiteUsers();
        $this->error .= ($site_users['error']) ?: '';
        $site_users = $site_users['site_users'];

        CoreView::renderTemplate('Admin/site_users.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => $show_add_btn,
            'site_users' => $site_users
        ]);
    }

    /**
     * Экспорт
     */
    public function export(){
        $show_add_btn = false;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        if ($act == 'get_xls') {
            $this->model->exportProducts();
        }
        else {
            CoreView::renderTemplate('Admin/export.html', [
                'params' => $this->params,
                'title' => $this->page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => ""
            ]);
        }
    }

    /**
     * Сео
     * @return void
     */
    public function seo(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        if ($act == 'add') {
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_res = $this->model->addSeoItem($_POST);
                if ($add_res['error']) {
                    $this->error .= $add_res['error'];

                    $site_pages = $this->model->getSitePageWithoutSeo();

                    CoreView::renderTemplate('Admin/seo_item.html', [
                        'params' => $this->params,
                        'title' => "Добавление",
                        'notice' => $this->notice,
                        'error' => $this->error,
                        'show_add_btn' => $show_add_btn,
                        'add_btn_link' => "/admin/seo?act=add",
                        'site_pages' => $site_pages,
                        'seo_item' => "",
                        'act' => 'add'
                    ]);
                }
                else {
                    $id = $add_res['id'];
                    $_SESSION['notice'] = $add_res['notice'];
                    header("Location: /admin/seo?act=edit&id=$id");
                    exit;
                }
            }
            else {
                $site_pages = $this->model->getSitePageWithoutSeo();

                CoreView::renderTemplate('Admin/seo_item.html', [
                    'params' => $this->params,
                    'title' => "Добавление",
                    'notice' => $this->notice,
                    'error' => $this->error,
                    'show_add_btn' => $show_add_btn,
                    'add_btn_link' => "/admin/seo?act=add",
                    'site_pages' => $site_pages,
                    'seo_item' => "",
                    'item' => "",
                    'act' => 'add'
                ]);
            }
        }
        else if ($act == 'edit') {
            $id = (int)$_GET['id'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $seo_item = $this->model->updateSeoItem($_POST);
                if ($seo_item['error']) {
                    $this->error = $seo_item['error'];
                    $seo_item = $_POST;
                }
                else if ($seo_item['notice']){
                    $_SESSION['notice'] = $seo_item['notice'];
                    header("Location: /admin/seo?act=edit&id=$id");
                    exit;
                }
            }
            else {
                $seo_item = $this->model->getSeoItem($id);
                $this->error .= ($seo_item['error']) ?: '';
                $seo_item = $seo_item['item'];
            }

            $site_pages = $this->model->getSitePageWithoutSeo($seo_item['id']);

            CoreView::renderTemplate('Admin/seo_item.html', [
                'params' => $this->params,
                'title' => 'Редактирование',
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/seo?act=add",
                'site_pages' => $site_pages,
                'seo_item' => $seo_item,
                'act' => 'edit'
            ]);
        }
        else if ($act == 'delete') {
            $id = (int)$_GET['id'];
            $delete = $this->model->deleteSeoItem($id);
            if ($delete['error']) $_SESSION['error'] = $delete['error'];
            if ($delete['notice']) $_SESSION['notice'] = $delete['notice'];
            header('Location: /admin/seo');
            exit();
        }
        else {
            $seo_items = $this->model->getSeoItems();
            $this->error .= ($seo_items['error']) ?: '';
            $seo_items = $seo_items['items'];

            CoreView::renderTemplate('Admin/seo_items.html', [
                'params' => $this->params,
                'title' => $this->page_title." (".count($seo_items)." шт.)",
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/seo?act=add",
                'seo_items' => $seo_items
            ]);
        }
    }

    /**
     * Ед. измерения
     * @return void
     */
    public function ref_counters(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        if ($act == 'add') {
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_res = $this->model->addRefCounter($_POST);
                if ($add_res['error']) {
                    $this->error .= $add_res['error'];
                }
                else {
                    header("Location: /admin/ref_counters");
                    exit;
                }
            }

            CoreView::renderTemplate('Admin/ref_counter.html', [
                'params' => $this->params,
                'title' => "Добавление ед. измерения",
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/ref_counters?act=add",
                'ref_counter' => $_POST,
                'act' => 'add'
            ]);
        }
        else {
            $ref_counters = $this->model->getRefCounters();
            $this->error .= ($ref_counters['error']) ?: '';
            $ref_counters = $ref_counters['counters'];

            CoreView::renderTemplate('Admin/ref_counters.html', [
                'params' => $this->params,
                'title' => $this->page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/ref_counters?act=add",
                'ref_counters' => $ref_counters
            ]);
        }
    }

    /**
     * ПВЗ
     * @return void
     */
    public function pvz(){
        $show_add_btn = true;
        $act = (isset($_GET['act'])) ? $_GET['act'] : '';

        if ($act == 'add') {
            $pvz_item = array();
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $add_res = $this->model->addPvzItem($_POST);
                if ($add_res['error']) {
                    $this->error = $add_res['error'];
                    $pvz_item = $_POST;
                }
                else {
                    $_SESSION['notice'] = $add_res['notice'];
                    header("Location: /admin/pvz?act=edit&id={$add_res['id']}");
                    exit();
                }
            }

            CoreView::renderTemplate('Admin/pvz_item.html', [
                'params' => $this->params,
                'title' => "Добавление пункта выдачи",
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/pvz?act=add",
                'pvz_item' => $pvz_item,
                'act' => 'add'
            ]);
        }
        else if ($act == 'edit') {
            $id = (int)$_GET['id'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST'){
                $pvz_item = $this->model->updatePvzItem($_POST);
                if ($pvz_item['error']) {
                    $this->error = $pvz_item['error'];
                    $pvz_item = $_POST;
                }
                else if ($pvz_item['notice']){
                    $_SESSION['notice'] = $pvz_item['notice'];
                    header("Location: /admin/pvz?act=edit&id=$id");
                    exit;
                }
            }
            else {
                $pvz_item = $this->model->getPvzItem($id);
                $this->error .= ($pvz_item['error']) ?: '';
                $pvz_item = $pvz_item['pvz'];
            }

            CoreView::renderTemplate('Admin/pvz_item.html', [
                'params' => $this->params,
                'title' => 'Редактирование пункта выдачи',
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/pvz?act=add",
                'pvz_item' => $pvz_item,
                'act' => 'edit'
            ]);
        }
        else if ($act == 'add_to_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('pvz', $id, 1);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/pvz');
            exit();
        }
        else if ($act == 'remove_from_achive') {
            $id = (int)$_GET['id'];
            $set_res = $this->model->setArchive('pvz', $id, 0);
            if ($set_res['error']) {
                $_SESSION['error'] = $set_res['error'];
            }
            if ($set_res['notice']) {
                $_SESSION['notice'] = $set_res['notice'];
            }
            header('Location: /admin/pvz');
            exit();
        }
        else {
            $pvz_items = $this->model->getPvzItems();
            $this->error .= ($pvz_items['error']) ?: '';
            $pvz_items = $pvz_items['pvz'];

            CoreView::renderTemplate('Admin/pvz_items.html', [
                'params' => $this->params,
                'title' => $this->page_title,
                'notice' => $this->notice,
                'error' => $this->error,
                'show_add_btn' => $show_add_btn,
                'add_btn_link' => "/admin/pvz?act=add",
                'pvz_items' => $pvz_items
            ]);
        }
    }

    /**
     * Меню
     */
    public function menu(){
        $menu = $this->model->getMenuItems();
        CoreView::renderTemplate('Admin/menu.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => false,
            'add_btn_link' => "",
            'menu_items' => $menu
        ]);
    }

    /**
     * Мой склад (ассортимент)
     */
    public function depot_products(){
        //note товары склада
        $depot_products_data = Depot::getAssortment();
        usort($depot_products_data, function($a, $b){
            return $a['name'] > $b['name'];
        });

        $products = $this->model->getProducts()['products'];
        usort($products, function($a, $b){
            return $a['title'] > $b['title'];
        });
        $count_products = count($products);
        $count_depot_products = count($depot_products_data);

        $depot_products = array();
        $i = 0;
        foreach ($depot_products_data as $depot_key => $depot_product){
            $product_info = Depot::getDepotProduct($depot_product['id']);
            if ($product_info['paymentItemType'] == 'GOOD') {
                $depot_ct = ($product_info['uom']['name'] == 'шт') ? 1 : 2;
                $depot_price = $product_info['salePrices'][0]['value'] / 100;
                $depot_products[$depot_key] = array(
                    'depot_id' => $depot_product['id'],
                    'qty' => $depot_product['quantity'],
                    'stock' => $depot_product['stock'],
                    'reserve' => $depot_product['reserve'],
                    'title' => $product_info['name'],
                    'price' => $depot_price,
                    'ct' => $depot_ct
                );

                foreach ($products as $key => $product) {
                    if ($depot_product['id'] == $product['depot_id']) {
                        $title = array();
                        if ($depot_ct == $product['ct']) {
                            if ($depot_price != $product['price']) {
                                $title[] = "Ед. измерения совпадают, но НЕ совпадают цены.";
                            }
                            if ($depot_product['quantity'] != $product['qty']) {
                                $title[] = "Не совпадает количество.";
                            }
                        }
                        else {
                            if (!$product['count_part']) {
                                $title[] = "Ед. измерения НЕ совпадают и НЕ указана делимость.";
                            }
                            else if ($depot_price != $product['price'] / $product['count_part']) {
                                $title[] = "Ед. измерения НЕ совпадают и НЕ совпадают цены с учетом конвертации.";
                            }

                            if ($depot_product['quantity'] / $product['count_part'] != $product['qty']) {
                                $title[] = "Не совпадает количество с учетом конвертации.";
                            }
                        }

                        $depot_products[$depot_key]['style_color'] = count($title) ? "#ff4141" : '';
                        $depot_products[$depot_key]['style_title'] = implode(' ', $title);
                        $depot_products[$depot_key]['site_product'] = $product;
                        $count_products--;
                        $count_depot_products--;
                        break;
                    }
                }
            }

            $i++;
        }

        $info = "Всего позиций на складе: ".count($depot_products)." шт., не сопоставлено на складе: $count_depot_products шт.<br>";
        $info .= "Всего позиций на сайте: $count_products шт., не сопоставлено на сайте: ".count($products)." шт.<br>";

        CoreView::renderTemplate('Admin/depot/depot_products.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => false,
            'add_btn_link' => "",
            'depot_products' => $depot_products,
            'products' => $products,
            'info' => $info
        ]);
    }

    /**
     * Мой склад (группы товаров)
     */
    public function depot_folders(){
        //note страницы склада
        $depot_folders = Depot::getProductFolders();
        usort($depot_folders, function($a, $b){
            return $a['name'] > $b['name'];
        });

        $catalog_pages = $this->model->getCatalogPages();
        $depot_pages = $this->model->getDepotPages();

        $count_depot_folders = count($depot_folders);
        $count_catalog_pages = count($catalog_pages);

        foreach ($depot_folders as $depot_key => $depot_page){
            if (isset($depot_pages[$depot_page['id']])) {
                $depot_folders[$depot_key]['page_id'] = $depot_pages[$depot_page['id']];
            }
        }

        $info = "Всего папок на складе: ".count($depot_folders)." шт., не сопоставлено на складе: $count_depot_folders шт.<br>";
        $info .= "Всего страниц каталога на сайте: $count_catalog_pages шт., не сопоставлено на сайте: ".count($catalog_pages)." шт.<br>";

        CoreView::renderTemplate('Admin/depot/depot_folders.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => false,
            'add_btn_link' => "",
            'depot_folders' => $depot_folders,
            'catalog_pages' => $catalog_pages,
            'info' => $info
        ]);
    }

    /**
     * Мой склад (заказы)
     */
    public function depot_orders(){
        //$depot_orders = Depot::getOrders();
        $depot_orders = array();
        $info = "Всего заказов на складе: н/д шт.<br>";

        CoreView::renderTemplate('Admin/depot/depot_orders.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => false,
            'add_btn_link' => "",
            'depot_orders' => $depot_orders,
            'info' => $info
        ]);
    }

    /**
     * Мой склад (контрагенты)
     */
    public function depot_counterparty(){
        $depot_counterparty = Depot::getCounterparty();
        usort($depot_counterparty, function($a, $b){
            return $a['name'] > $b['name'];
        });
        $site_users = $this->model->getSiteUsers()['site_users'];
        usort($site_users, function($a, $b){
            return $a['name'] > $b['name'];
        });

        $count_depot_counterparty = count($depot_counterparty);
        $count_site_users = count($site_users);

        foreach ($depot_counterparty as $counterparty_key => $counterparty){
            foreach($site_users as $key => $site_user) {
                if ($counterparty['id'] == $site_user['depot_id']) {
                    $depot_counterparty[$counterparty_key]['site_user'] = $site_user;
                    $count_depot_counterparty--;
                    break;
                }
            }
        }

        $info = "Всего контрагентов на складе: ".count($depot_counterparty)." шт., не сопоставлено на складе: $count_depot_counterparty шт.<br>";
        $info .= "Всего пользователей на сайте: $count_site_users шт., не сопоставлено на сайте: ".count($site_users)." шт.<br>";

        CoreView::renderTemplate('Admin/depot/depot_counterparty.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => false,
            'add_btn_link' => "",
            'depot_counterparty' => $depot_counterparty,
            'site_users' => $site_users,
            'info' => $info
        ]);
    }

    /**
     * Мой склад (склады)
     */
    public function depot_stores(){
        $depot_stores = Depot::getStores();

        $info = "Всего складов: ".count($depot_stores)." шт.<br>";

        CoreView::renderTemplate('Admin/depot/depot_stores.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => false,
            'add_btn_link' => "",
            'depot_stores' => $depot_stores,
            'info' => $info
        ]);
    }

    /**
     * Мой склад (организации)
     */
    public function depot_organizations(){
        $depot_organizations = Depot::getOrganizations();

        $info = "Всего организаций: ".count($depot_organizations)." шт.<br>";

        CoreView::renderTemplate('Admin/depot/depot_organizations.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => false,
            'add_btn_link' => "",
            'depot_organizations' => $depot_organizations,
            'info' => $info
        ]);
    }

    /**
     * Мой склад (точки продаж)
     */
    public function depot_retailstore(){
        $depot_retailstore = Depot::getRetailstore();
        $info = "Всего точек продаж: ".count($depot_retailstore)." шт.<br>";

        CoreView::renderTemplate('Admin/depot/depot_retailstore.html', [
            'params' => $this->params,
            'title' => $this->page_title,
            'notice' => $this->notice,
            'error' => $this->error,
            'show_add_btn' => false,
            'add_btn_link' => "",
            'depot_retailstore' => $depot_retailstore,
            'info' => $info
        ]);
    }
}