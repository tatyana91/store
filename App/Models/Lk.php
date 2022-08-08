<?php
namespace App\Models;

use Core\Model as CoreModel;
use PDO;
use Core\Error;
use Core\CommonFunctions;


class Lk extends CoreModel{
    /**
     * Типы связи
     * @return array
     */
    public function getConnectTypes(){
        $connect_types = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM ref_connect_types");
            $res->execute();
            $connect_types = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['connect_types'] = $connect_types;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Сохранить основную информацию лк
     * @param $info
     * @return array
     */
    public function saveMainData($info){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("  UPDATE site_users
                                            SET
                                              name = :name,
                                              phone = :phone,
                                              connect_type = :connect_type,
                                              subscribe = :subscribe
                                            WHERE id = :id");
            $res->bindValue(":id", $_SESSION['user']['id'], PDO::PARAM_INT);
            $res->bindValue(":name", $info['name']);
            $res->bindValue(":phone", $info['phone']);
            $res->bindValue(":connect_type", $info['connect_type'], PDO::PARAM_INT);
            $res->bindValue(":subscribe", $info['subscribe'], PDO::PARAM_INT);
            $res->execute();
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Обновить адресс
     * @param $data
     * @return array
     */
    public function updateAddress($data){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("UPDATE site_user_addresses 
                                          SET
                                            region = :region,
                                            city = :city,
                                            street = :street,
                                            house = :house,
                                            corpus = :corpus,
                                            building = :building,
                                            flat = :flat,
                                            entrance = :entrance,
                                            floor = :floor
                                          WHERE id = :id");
            $res->bindValue(":region", $data['region'], PDO::PARAM_INT);
            $res->bindValue(":city", $data['city']);
            $res->bindValue(":street", $data['street']);
            $res->bindValue(":house", $data['house']);
            $res->bindValue(":corpus", $data['corpus']);
            $res->bindValue(":building", $data['building']);
            $res->bindValue(":flat", $data['flat']);
            $res->bindValue(":entrance", $data['entrance']);
            $res->bindValue(":floor", $data['floor']);
            $res->bindValue(":id", $data['id'], PDO::PARAM_INT);
            $res->execute();
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['data'] = $data;
        return $result;
    }

    /**
     * Удалить адресс
     * @param $id
     * @return array
     */
    public function deleteAddress($id){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("DELETE FROM site_user_addresses
                                          WHERE 
                                            id = :id
                                            AND user_id = :user_id");
            $res->bindValue(":id", $id, PDO::PARAM_INT);
            $res->bindValue(":user_id", $_SESSION['user']['id'], PDO::PARAM_INT);
            $res->execute();
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка удаления данных';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Заказы пользователя
     * @return array
     */
    public function getSiteUserOrders(){
        $orders = array();
        $error = '';
        try{
            $db = static::getDB();

            $res = $db->prepare("SELECT email FROM site_users WHERE id = :user_id");
            $res->bindValue(":user_id", $_SESSION['user']['id'], PDO::PARAM_INT);
            $res->execute();
            $user_info = $res->fetch(PDO::FETCH_ASSOC);

            $params = array(":user_id" => $_SESSION['user']['id']);
            $where = "";
            if ($user_info['email']) {
                $where = " OR email = :email";
                $params[':email'] = $user_info['email'];
            }

            $res = $db->prepare("  SELECT * 
                                            FROM orders
                                            WHERE 
                                                  user_id = :user_id
                                                  $where  
                                            ORDER BY id DESC");
            $res->execute($params);
            while ($order = $res->fetch(PDO::FETCH_ASSOC)){
                $order_record = $this->getOrder($order['id']);
                if ($order_record['error']) {
                    $error = $order_record['error'];
                }
                else {
                    $orders[$order['id']] = $order_record['order'];
                }
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['orders'] = $orders;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Добавить пользователя
     * @param $email
     * @param $phone
     * @param $password
     * @param $subscribe
     * @return array
     */
    public function addSiteUser($email, $phone, $password, $subscribe){
        $error = '';
        $confirm_hash = '';
        try{
            $db = static::getDB();

            $check_user = $this->getSiteUserByMail($email);
            if ($check_user['user']) {
                $error = 'Пользователь с такой почтой уже зарегистрирован';
            }
            else {
                $password = md5($password);
                $salt = md5(rand());
                $password = md5($password.$salt);
                $confirm_hash = md5(rand());
                $time = time();

                $res = $db->prepare("INSERT INTO site_users 
                                          SET 
                                            login = :login, 
                                            password = :password,                                            
                                            salt = :salt,
                                            email = :email, 
                                            phone = :phone,
                                            confirm_hash = :confirm_hash,
                                            time = :time,
                                            subscribe = :subscribe");
                $res->bindValue(':login', $email);
                $res->bindValue(':password', $password);
                $res->bindValue(':salt', $salt);
                $res->bindValue(':email', $email);
                $res->bindValue(':phone', $phone);
                $res->bindValue(':confirm_hash', $confirm_hash);
                $res->bindValue(':time', $time, PDO::PARAM_INT);
                $res->bindValue(':subscribe', $subscribe, PDO::PARAM_INT);
                $res->execute();
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления пользователя';
        }

        $result = array();
        $result['error'] = $error;
        $result['confirm_hash'] = $confirm_hash;
        return $result;
    }

    /**
     * Смена пароля
     * @param $old_password
     * @param $new_password
     * @return array
     */
    public function changeSiteUserPassword($old_password, $new_password){
        $error = '';
        $user = $this->getSiteUser($_SESSION['user']['id']);
        if ($user['error']) {
            $error = $user['error'];
        }
        else {
            $user = $user['user'];
            $old_password = md5(md5($old_password).$user['salt']);
            if ($old_password != $user['password']){
                $error = "Указан неверный старый пароль";
            }
            else {
                try {
                    $new_password = md5(md5($new_password).$user['salt']);
                    $db = static::getDB();
                    $res = $db->prepare("UPDATE site_users 
                                                  SET password = :password 
                                                  WHERE id = :id");
                    $res->bindParam(':password', $new_password);
                    $res->bindParam(':id', $_SESSION['user']['id'], PDO::PARAM_INT);
                    $res->execute();
                }
                catch (\PDOException $e){
                    Error::logError($e);
                    $error = "Ошибка обновления пароля";
                }
            }
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Смена пароля при восстановлении доступа
     * @param $user_id
     * @param $new_password
     * @return array
     */
    public function setSiteUserPassword($user_id, $new_password){
        $error = '';
        $user = $this->getSiteUser($user_id);
        if ($user['error']) {
            $error = $user['error'];
        }
        else {
            $user = $user['user'];
            try {
                $new_password = md5(md5($new_password).$user['salt']);
                $db = static::getDB();
                $res = $db->prepare("UPDATE site_users 
                                              SET password = :password 
                                              WHERE id = :id");
                $res->bindParam(':password', $new_password);
                $res->bindParam(':id', $user_id, PDO::PARAM_INT);
                $res->execute();
            }
            catch (\PDOException $e){
                Error::logError($e);
                $error = "Ошибка обновления пароля";
            }
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Проверить логин и пароль
     * @param $login
     * @param $password
     * @return array
     */
    public function checkSiteUser($login, $password){
        $check = false;
        try {
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM site_users WHERE login = :login");
            $res->bindParam(':login', $login);
            $res->execute();
            $count = $res->rowCount();
            $row = $res->fetch(PDO::FETCH_ASSOC);
            if ($count == 1) {
                $password = md5($password);
                $salt = $row['salt'];
                $password = md5($password.$salt);
                if ($password == $row['password']){
                    $check = true;
                }
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $check = false;
        }

        $result = array();
        $result['check'] = $check;
        $result['user'] = $row;
        return $result;
    }

    /**
     * Вход пользователя
     */
    public function login($login, $password){
        $error = '';
        $check_user = $this->getSiteUserByMail($login);
        if (!$check_user['user']) {
            $error = 'Пользователь с такой почтой еще не зарегистрирован';
        }
        else {
            $check_user = $this->checkSiteUser($login, $password);
            if ($check_user['check']) {
                $user = $check_user['user'];
                if ($user['confirm'] == '1') {
                    $_SESSION['user']['id'] = $user['id'];
                    $_SESSION['user']['login'] = $user['name'];
                }
                else {
                    $error = "Почтовый адрес не подтвержден";
                }
            }
            else {
                $error = "Указан неверный пароль";
            }
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Подтвердить регистрацию
     * @return array
     */
    public function confirmUser($u){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("UPDATE site_users
                                          SET confirm = '1'
                                          WHERE confirm_hash = :confirm_hash");
            $res->bindValue(":confirm_hash", $u);
            $res->execute();
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка подтверждения регистрации';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }
}