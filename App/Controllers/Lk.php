<?php
namespace App\Controllers;

use Core\CommonFunctions;
use Core\Controller as CoreController;
use Core\View as CoreView;
use App\Models\Lk as LkModel;

class Lk extends CoreController{
    private $model;
    public function __construct(){
        parent::__construct();
        $this->model = new LkModel();
    }

    /**
     * Главная страница лк
     * @return void
     */
    public function index(){
        if (!isset($_SESSION['user'])) {
            header("Location: /");
            exit();
        }

        $page = $this->model->getPageInfo('lk');

        $user_record = $this->model->getSiteUser($_SESSION['user']['id']);
        if ($user_record['error']) {
            $this->error = $user_record['error'];
        }
        $user = $user_record['user'];

        $connect_types_records = $this->model->getConnectTypes();
        if ($connect_types_records['error']) {
            $this->error = $connect_types_records['error'];
        }
        $connect_types = $connect_types_records['connect_types'];

        $address_records = $this->model->getSiteUserAddresses();
        if ($address_records['error']) {
            $this->error = $address_records['error'];
        }
        $addresses = $address_records['addresses'];

        $orders_records = $this->model->getSiteUserOrders();
        if ($orders_records['error']) {
            $this->error = $orders_records['error'];
        }
        $orders = $orders_records['orders'];

        CoreView::renderTemplate('Lk/index.html', [
            'settings' => $this->settings,
            'header_pages' => $this->header_pages,
            'catalog_menu_pages' => $this->catalog_menu_pages,
            'page' => $page['page'],
            'user' => $user,
            'connect_types' => $connect_types,
            'addresses' => $addresses,
            'orders' => $orders,
            'catalog_menu' => $this->catalog_menu,
            'params' => $this->params
        ]);
    }

    /**
     * Обработчик асинхронных запросов
     */
    public function ajax(){
        $act = $_POST['act'];
        $error = '';

        if ($act == 'req') {
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $password = $_POST['password'];
            $subscribe = $_POST['subscribe'];

            $add_user = $this->model->addSiteUser($email, $phone, $password, $subscribe);
            if ($add_user['error']) {
                $error = $add_user['error'];
            }
            else {
                $subject = "Подтверждение регистрации на сайте {$_SERVER['SERVER_NAME']}";
                $message = "Для подтверждения регистрации перейдите по ";
                $message .= "<a href=\"http://{$_SERVER['HTTP_HOST']}/lk/confirm?u={$add_user['confirm_hash']}\">ссылке</a>.";
                $send_mail = $this->model->sendMail($email, $subject, $message);
                if (!$send_mail){
                    $error = "Ошибка отправки сообщения";
                }
            }

            $result = array();
            $result['error'] = $error;
            $result['result'] = "Регистрация прошла успешно. Для подтверждения регистрации перейдите по ссылке, которая была отправлена на указанную эл. почту.";
            echo json_encode($result);
            exit();
        }

        if ($act == 'login') {
            $email = $_POST['email'];
            $password = $_POST['password'];

            $login_res = $this->model->login($email, $password);
            if ($login_res['error']) {
                $error = $login_res['error'];
            }

            $result = array();
            $result['error'] = $error;
            echo json_encode($result);
            exit();
        }

        if ($act == 'change_password') {
            $old_password = $_POST['old_password'];
            $new_password = $_POST['new_password'];

            $change_password = $this->model->changeSiteUserPassword($old_password, $new_password);
            if ($change_password['error']) {
                $error = $change_password['error'];
            }

            $result = array();
            $result['error'] = $error;
            $result['result'] = ($error) ? '' : "Пароль успешно изменен";
            echo json_encode($result);
            exit();
        }

        if ($act == 'save_lk_main_data') {
            $save = $this->model->saveMainData($_POST['data']);
            if ($save['error']) {
                $error = $save['error'];
            }

            $result = array();
            $result['error'] = $error;
            $result['post'] = $_POST;
            echo json_encode($result);
            exit();
        }

        if ($act == 'add_address_block') {
            $html = CoreView::returnTemplate('inc/address.html', array(
                'params' => $this->params
            ));
            $result = array();
            $result['html'] = $html;
            echo json_encode($result);
            exit();
        }

        if ($act == 'save_addresses') {
            $addresses = $_POST['addresses'];
            foreach($addresses as $key => $address) {
                if ($address['id']) {
                    $update = $this->model->updateAddress($address);
                    if ($update['error']) {
                        $error = $update['error'];
                    }
                }
                else {
                    $add = $this->model->addAddress($address);
                    if ($add['error']) {
                        $error = $add['error'];
                    }
                    else {
                        $addresses[$key]['id'] = $add['address_id'];
                    }
                }
            }

            $result = array();
            $result['error'] = $error;
            $result['addresses'] = $addresses;
            echo json_encode($result);
            exit();
        }

        if ($act == 'delete_address') {
            $id = (int)$_POST['id'];
            $remove = $this->model->deleteAddress($id);
            if ($remove['error']) {
                $error = $remove['error'];
            }

            $result = array();
            $result['error'] = $error;
            echo json_encode($result);
            exit();
        }

        if ($act == 'reset') {
            $email = $_POST['email'];
            $success = '';

            $check_user = $this->model->getSiteUserByMail($email);
            if (!$check_user['user']) {
                $error = 'Пользователь с такой почтой еще не зарегистрирован';
            }
            else {
                $user_info = $check_user['user']['id'];
                $password = CommonFunctions::genPassword();

                $set_password = $this->model->setSiteUserPassword($user_info['id'], $password);
                if ($set_password['error']) {
                    $error = $set_password['error'];
                }
                else {
                    $to = $email;
                    $subject = "Восстановление пароля на сайте {$_SERVER['SERVER_NAME']}";
                    $send_mail = $this->model->sendResetMail($to, $subject, $password);
                    if ($send_mail['error']) {
                        $error = $send_mail['error'];
                    }
                    else {
                        $success = "На указанную эл.почту было отправлено письмо для восстановления доступа";
                    }
                }
            }

            $result = array();
            $result['error'] = $error;
            $result['success'] = $success;
            echo json_encode($result);
            exit();
        }
    }

    /**
     * Выход пользователя
     */
    public function logout(){
        unset($_SESSION['user']);
        header('Location: /');
        exit();
    }

    /**
     * Подтверждение регистрации
     */
    public function confirm(){
        $u = $_GET['u'];
        $confirm = $this->model->confirmUser($u);
        if ($confirm['error']) {
            $confirm_result = $confirm['error'];
        }
        else {
            $confirm_result = "Регистрация успешно подтверждена.";
        }

        $page = $this->model->getPageInfo('confirm');

        CoreView::renderTemplate('Lk/confirm.html', [
            'settings' => $this->settings,
            'header_pages' => $this->header_pages,
            'catalog_menu_pages' => $this->catalog_menu_pages,
            'page' => $page['page'],
            'confirm_result' => $confirm_result,
            'catalog_menu' => $this->catalog_menu,
            'params' => $this->params
        ]);
    }
}