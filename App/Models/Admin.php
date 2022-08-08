<?php
namespace App\Models;

use Core\Model as CoreModel;
use PDO;
use Core\Error;
use Core\CommonFunctions;
use PDOException;


class Admin extends CoreModel{
    /**
     * Меню админки
     * @return array
     */
    public function getMenu():array{
        $menu = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM admin_menu ORDER BY rate DESC");
            $res->execute();
            while ($menu_item = $res->fetch(PDO::FETCH_ASSOC)){
                if (!isset($menu[$menu_item['section']])) {
                    $menu[$menu_item['section']] = array();
                }
                if (!isset($menu[$menu_item['section']]['items'])) {
                    $menu[$menu_item['section']]['items'] = array();
                }
                if (!isset($menu[$menu_item['section']]['items'][$menu_item['id']])) {
                    $menu[$menu_item['section']]['items'][$menu_item['id']] = array();
                }
                $menu[$menu_item['section']]['items'][$menu_item['id']]['url'] = $menu_item['url'];
                $menu[$menu_item['section']]['items'][$menu_item['id']]['title'] = $menu_item['title'];
                $menu[$menu_item['section']]['items'][$menu_item['id']]['active'] = "";
            }
        }
        catch (PDOException $e){
            Error::logError($e);
        }
        return $menu;
    }

    /**
     * Меню админки
     * @return array
     */
    public function getMenuItems():array{
        $menu = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM admin_menu ORDER BY rate DESC");
            $res->execute();
            $menu = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
        }
        return $menu;
    }

    /**
     * Сохранить настройки сайта
     * @param $settings
     * @return array
     */
    public function saveSettings($settings):array{
        $result = array();
        try{
            $path = $settings['banner_path'];
            if (isset($_FILES['banner']) && $_FILES['banner']['name']) {
                $path_info = pathinfo($_FILES['banner']['name']);
                $extension = $path_info['extension'];
                $path = time().".".$extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['banner']['tmp_name'])){
                    if (in_array($_FILES['banner']['type'], $types)){
                        $banner_path = $_SERVER['DOCUMENT_ROOT'].'/images/banners/'.$path;
                        move_uploaded_file($_FILES['banner']['tmp_name'], $banner_path);
                    }
                }
            }

            $db = static::getDB();
            $res = $db->prepare("	UPDATE settings 
                                            SET 
                                                site_name = :site_name, 
                                                logo_title = :logo_title, 
                                                logo_title2 = :logo_title2, 
                                                phone = :phone, 
                                                mail = :mail, 
                                                address = :address, 
                                                city = :city,
                                                mailsend = :mailsend,
                                                mailorders = :mailorders,
                                                copyright = :copyright,
                                                soc_vk = :soc_vk,
                                                soc_ins = :soc_ins,
                                                delivery_cost = :delivery_cost,
                                                delivery_sum_free = :delivery_sum_free,
                                                banner = :banner,
                                                banner_title = :banner_title,
                                                banner_title2 = :banner_title2
                                            WHERE id = 1");
            $res->bindParam(':site_name', $settings['site_name']);
            $res->bindParam(':logo_title', $settings['logo_title']);
            $res->bindParam(':logo_title2', $settings['logo_title2']);
            $res->bindParam(':phone', $settings['phone']);
            $res->bindParam(':mail', $settings['mail']);
            $res->bindParam(':address', $settings['address']);
            $res->bindParam(':city', $settings['city']);
            $res->bindParam(':mailsend', $settings['mailsend']);
            $res->bindParam(':mailorders', $settings['mailorders']);
            $res->bindParam(':copyright', $settings['copyright']);
            $res->bindParam(':soc_vk', $settings['soc_vk']);
            $res->bindParam(':soc_ins', $settings['soc_ins']);
            $res->bindParam(':delivery_cost', $settings['delivery_cost']);
            $res->bindParam(':delivery_sum_free', $settings['delivery_sum_free']);
            $res->bindParam(':banner', $path);
            $res->bindParam(':banner_title', $settings['banner_title']);
            $res->bindParam(':banner_title2', $settings['banner_title2']);
            $res->execute();

            $result['notice'] = 'Запись успешно отредактирована!';
        }
        catch (PDOException $e){
            Error::logError($e);
            $result['error'] = 'Ошибка сохранения данных';
        }
        return $result;
    }

    /**
     * Страницы сайта
     * @return bool|mixed
     */
    public function getPages(){
        $pages = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM pages ORDER BY rate DESC");
            $res->execute();
            while ($page = $res->fetch(PDO::FETCH_ASSOC)){
                $page_images = $this->getItemImages($page['id'], 'page');
                $page['image'] = $page_images[0]['path_middle'];

                $page_info = $this->getPageLinkInfo($page['id']);
                $page['full_url'] = $page_info['url'];
                $page['full_title'] = $page_info['title'];
                $pages[] = $page;
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['pages'] = $pages;
        $result['error'] = $error;
        return $result;
    }

    public function getSitePages():array{
        $site_pages = array();
        $pages = $this->getPages();
        $pages = $pages['pages'];
        $i = 0;
        foreach($pages as $page) {
            if ($page['archived'] == '1') continue;
            $site_pages[$i]['full_title'] =  $page['full_title'];
            $site_pages[$i]['id'] =  $page['id'];
            $site_pages[$i]['table'] =  'pages';
            $site_pages[$i]['data'] =  "pages_".$page['id'];
            $i++;
        }

        $products = $this->getProducts();
        $products = $products['products'];
        foreach($products as $product) {
            if ($product['archived'] == '1') continue;
            $site_pages[$i]['full_title'] =  $product['full_title']." -> ".$product['title'];
            $site_pages[$i]['id'] =  $product['id'];
            $site_pages[$i]['table'] =  'products';
            $site_pages[$i]['data'] =  "products_".$product['id'];
            $i++;
        }
        return $site_pages;
    }

    public function getSitePageWithoutSeo($seo_id = 0):array{
        $site_pages = $this->getSitePages();
        foreach($site_pages as $key => $site_page){
            $seo_data = $this->getSeoData($site_page['id'], $site_page['table']);
            if (!$seo_data['error'] && count($seo_data['seo'])) {
                if (!$seo_id || ($seo_id != $seo_data['seo']['id'])) {
                    unset($site_pages[$key]);
                }
            }
        }
        return $site_pages;
    }

    /**
     * Добавить страницу
     * @param $page_info
     * @return array
     */
    public function addPage(array $page_info):array{
        $error = '';
        $notice = '';
        try{
            $show_menu = (isset($page_info['show_menu'])) ? 1 : 0;
            $show_index_block = (isset($page_info['show_index_block'])) ? 1 : 0;
            $title = $page_info['title'];
            $parent_id = $page_info['parent_id'];
            $title_menu = $page_info['title_menu'];
            $url = CommonFunctions::translit(mb_strtolower($page_info['title'], 'utf8'));
            $description = $page_info['description'];
            $text = $page_info['text'];
            $rate = $page_info['rate'];

            $check_url = $this->checkUrlExists('pages', $url);
            if ($check_url['error']) {
                $error = $check_url['error'];
            }
            else {
                $db = static::getDB();
                $res = $db->prepare("INSERT INTO pages 
                                          SET
                                            show_menu = :show_menu,
                                            show_index_block = :show_index_block,
                                            parent_id = :parent_id,
                                            title = :title,
                                            title_menu = :title_menu,
                                            url = :url,
                                            description = :description,
                                            text = :text,
                                            rate = :rate");
                $res->bindValue(':show_menu', $show_menu, PDO::PARAM_INT);
                $res->bindValue(':show_index_block', $show_index_block, PDO::PARAM_INT);
                $res->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
                $res->bindValue(':title', $title);
                $res->bindValue(':title_menu', $title_menu);
                $res->bindValue(':url', $url);
                $res->bindValue(':description', $description);
                $res->bindValue(':text', $text);
                $res->bindValue(':rate', $rate, PDO::PARAM_INT);
                $res->execute();

                $page_id = $db->lastInsertId();
                $page_info['id'] = $page_id;

                $update_photos = array();
                $update_photos['img'] = (isset($page_info['img'])) ? $page_info['img'] : array();
                $update_photos['img_id'] = (isset($page_info['img_id'])) ? $page_info['img_id'] : array();
                $update_photos['img_alt'] = (isset($page_info['img_alt'])) ? $page_info['img_alt'] : array();
                $update_photos['img_rate'] = (isset($page_info['img_rate'])) ? $page_info['img_rate'] : array();
                $save_photos_res = $this->savePhotos($page_id, 'page', $_FILES, $update_photos);
                $error .= ($save_photos_res['error']) ?: '';

                $notice = 'Страница успешно добавлена';
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка сохранения информации о странице';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['page'] = $page_info;
        return $result;
    }

    /**
     * Обновить информацию о странице
     * @param $page_info
     * @return array
     */
    public function updatePage(array $page_info):array{
        $error = '';
        $notice = '';
        try{
            $id = $page_info['id'];
            $show_menu = (isset($page_info['show_menu'])) ? 1 : 0;
            $show_index_block = (isset($page_info['show_index_block'])) ? 1 : 0;
            $parent_id = $page_info['parent_id'];
            $title = $page_info['title'];
            $title_menu = $page_info['title_menu'];
            $description = $page_info['description'];
            $text = $page_info['text'];
            $url = $page_info['url'] ?: CommonFunctions::translit(mb_strtolower($page_info['title'], 'utf8'));
            $rate = $page_info['rate'];

            $check_url = $this->checkUrlExists('pages', $url, $id);
            if ($check_url['error']) {
                $error = $check_url['error'];
            }
            else {
                $db = static::getDB();
                $res = $db->prepare("UPDATE pages 
                                          SET
                                            show_menu = :show_menu,
                                            show_index_block = :show_index_block,
                                            parent_id = :parent_id,
                                            title = :title,
                                            title_menu = :title_menu,                                            
                                            url = :url,
                                            description = :description,
                                            text = :text,
                                            rate = :rate
                                          WHERE id = :id");
                $res->bindValue(':id', $id, PDO::PARAM_INT);
                $res->bindValue(':show_menu', $show_menu, PDO::PARAM_INT);
                $res->bindValue(':show_index_block', $show_index_block, PDO::PARAM_INT);
                $res->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
                $res->bindValue(':title', $title);
                $res->bindValue(':title_menu', $title_menu);
                $res->bindValue(':url', $url);
                $res->bindValue(':description', $description);
                $res->bindValue(':text', $text);
                $res->bindValue(':rate', $rate, PDO::PARAM_INT);
                $res->execute();

                $update_photos = array();
                $update_photos['img'] = (isset($page_info['img'])) ? $page_info['img'] : array();
                $update_photos['img_id'] = (isset($page_info['img_id'])) ? $page_info['img_id'] : array();
                $update_photos['img_alt'] = (isset($page_info['img_alt'])) ? $page_info['img_alt'] : array();
                $update_photos['img_rate'] = (isset($page_info['img_rate'])) ? $page_info['img_rate'] : array();
                $save_photos_res = $this->savePhotos($id, 'page', $_FILES, $update_photos);
                $error .= ($save_photos_res['error']) ?: '';

                $page_info['show_menu'] = $show_menu;
                $page_info['show_index_block'] = $show_index_block;
                $page_info['url'] = $url;
                $page_info['photos'] = $this->getItemImages($id, 'page');
                $notice = 'Страница успешно отредактирована';
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о странице';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['page'] = $page_info;
        return $result;
    }

    /**
     * Поместить страницу в архив/убрать страницу из архива
     * @param string $table таблица
     * @param int $id идентификатор таблицы
     * @param bool $value добавить/убрать
     * @return array
     */
    public function setArchive(string $table, int $id, bool $value):array{
        $notice = '';
        $error = '';
        try{
            $db = static::getDB();
            if ($table == 'pages') {
                $res = $db->prepare("UPDATE pages SET archived = :value WHERE id = :id");
            }
            else if ($table == 'products') {
                $res = $db->prepare("UPDATE products SET archived = :value WHERE id = :id");
            }
            else if ($table == 'ref_chars') {
                $res = $db->prepare("UPDATE ref_chars SET archived = :value WHERE id = :id");
            }
            else if ($table == 'banners') {
                $res = $db->prepare("UPDATE banners SET archived = :value WHERE id = :id");
            }
            else if ($table == 'banners_catalog') {
                $res = $db->prepare("UPDATE banners_catalog SET archived = :value WHERE id = :id");
            }
            else if ($table == 'brands') {
                $res = $db->prepare("UPDATE brands SET archived = :value WHERE id = :id");
            }
            else if ($table == 'pvz') {
                $res = $db->prepare("UPDATE pvz SET archived = :value WHERE id = :id");
            }
            else {
                $error = 'Ошибка передачи необходимых параметров';
            }

            if (!$error) {
                $res->bindValue(':id', $id, PDO::PARAM_INT);
                $res->bindValue(':value', $value, PDO::PARAM_INT);
                $res->execute();

                $notice = ($value == 1) ? 'Запись успешно добавлена в архив' : 'Запись успешно восстановлена из архива';
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = ($value == 1) ? 'Ошибка добавления записи в архив' : 'Ошибка восстановления записи из архива';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Удалить страницу
     * @param $page_id
     * @return array
     */
    public function deletePage(int $page_id):array{
        $notice = '';
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("DELETE FROM pages WHERE id = :page_id");
            $res->bindValue(':page_id', $page_id, PDO::PARAM_INT);
            $res->execute();

            $notice = 'Страница успешно удалена';
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка удаления страницы';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Получить все товары
     * @return array
     */
    public function getProducts(){
        $error = '';
        $products = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM products ORDER BY rate DESC");
            $res->execute();
            while ($product = $res->fetch(PDO::FETCH_ASSOC)){
                $product_images = $this->getItemImages($product['id'], 'product');
                $product['image'] = $product_images[0]['path_small'];

                $product['full_title'] = '';
                $product['full_url'] = $product['url'];
                if ($product['parent_id']) {
                    $parent_info = $this->getPageLinkInfo($product['parent_id']);
                    $product['full_title'] = $parent_info['title'];
                    $product['full_url'] = $parent_info['url']."/".$product['url'];
                }

                $product['promocodes'] = explode(",", $product['apply_promo']);

                $products[$product['id']] = $product;
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['products'] = $products;
        return $result;
    }

    /**
     * Получить все товары с деталями
     * @return array
     */
    public function getFullProducts(){
        $error = '';
        $products = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM products ORDER BY rate DESC");
            $res->execute();
            while ($product = $res->fetch(PDO::FETCH_ASSOC)){
                $get_product = $this->getProduct($product['id']);
                if ($get_product['error']){
                    $error .=  $get_product['error'];
                }
                else {
                    $product = $get_product['product'];
                    $products[] = $product;
                }
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['products'] = $products;
        return $result;
    }

    /**
     * Добавить товар
     * @return array
     */
    public function addProduct($product_info){
        //note добавление товара
        $error = '';
        $notice = '';
        try{
            $title = $product_info['title'];
            $popular = (isset($product_info['popular'])) ? 1 : 0;
            $new = (isset($product_info['new'])) ? 1 : 0;
            $parent_id = $product_info['parent_id'] ?? '';
            $url = CommonFunctions::translit(mb_strtolower($product_info['title'], 'utf8'));
            $description = $product_info['description'] ?? '';
            $text = $product_info['text'] ?? '';
            $price = $product_info['price'];
            $price_sale = $product_info['price_sale'] ?? '';
            $rate = $product_info['rate'] ?? '';
            $barcode = $product_info['barcode'];
            $sales = isset($product_info['sales']) ? implode(',', $product_info['sales']) : "";
            $goods = isset($product_info['goods']) ? implode(',', $product_info['goods']) : "";
            $depot_id = $product_info['depot_id'] ?? "";
            $depot_title = $product_info['depot_title'] ?? "";
            $qty = $product_info['qty'] ?? 0;
            $archived = $product_info['archived'] ?? 0;
            $ct = $product_info['ct'] ?? 1;

            $check_url = $this->checkUrlExists('products', $url);
            if ($check_url['error']) {
                $error = $check_url['error'];
            }
            else {
                $db = static::getDB();
                $res = $db->prepare("INSERT INTO products 
                                          SET
                                            popular = :popular,
                                            new = :new,
                                            parent_id = :parent_id,
                                            title = :title,
                                            url = :url,
                                            description = :description,
                                            text = :text,
                                            price = :price,
                                            price_sale = :price_sale,
                                            rate = :rate,
                                            sales = :sales,
                                            goods = :goods,
                                            barcode = :barcode,
                                            depot_id = :depot_id,
                                            depot_title = :depot_title,
                                            qty = :qty,
                                            archived = :archived,
                                            ct = :ct");
                $res->bindValue(':popular', $popular, PDO::PARAM_INT);
                $res->bindValue(':new', $new, PDO::PARAM_INT);
                $res->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
                $res->bindValue(':title', $title);
                $res->bindValue(':url', $url);
                $res->bindValue(':description', $description);
                $res->bindValue(':text', $text);
                $res->bindValue(':price', $price, PDO::PARAM_INT);
                $res->bindValue(':price_sale', $price_sale, PDO::PARAM_INT);
                $res->bindValue(':rate', $rate, PDO::PARAM_INT);
                $res->bindValue(':sales', $sales);
                $res->bindValue(':barcode', $barcode);
                $res->bindValue(':goods', $goods);
                $res->bindValue(':depot_id', $depot_id);
                $res->bindValue(':depot_title', $depot_title);
                $res->bindValue(':qty', $qty, PDO::PARAM_INT);
                $res->bindValue(':archived', $archived, PDO::PARAM_INT);
                $res->bindValue(':ct', $ct, PDO::PARAM_INT);
                $res->execute();

                $product_id = $db->lastInsertId();

                $update_photos = array();
                $update_photos['img'] = (isset($product_info['img'])) ? $product_info['img'] : array();
                $update_photos['img_id'] = (isset($product_info['img_id'])) ? $product_info['img_id'] : array();
                $update_photos['img_alt'] = (isset($product_info['img_alt'])) ? $product_info['img_alt'] : array();
                $update_photos['img_rate'] = (isset($product_info['img_rate'])) ? $product_info['img_rate'] : array();
                $save_photos_res = $this->savePhotos($product_id, 'product', $_FILES, $update_photos);
                $error .= ($save_photos_res['error']) ?: '';

                $save_chars_res = $this->saveProductChars($product_id, $_POST['chars']);
                $error .= ($save_chars_res['error']) ?: '';

                $notice = (!$error) ? 'Товар успешно добавлен' : '';

                $product_info['popular'] = $popular;
                $product_info['new'] = $new;
                $product_info['id'] = $product_id;
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка сохранения информации о товаре';
        }

        if (!$error) {
            $history = "Добавлен товар \"$title\". ";
            $history .= ($depot_id) ? "Данные склада: guid = \"$depot_id\", title = \"$depot_title\". " : "";
            $history .= "Цена: $price руб. ";
            $history .= "Количество: $qty ".$this->ref_counters[$ct]['name'].".";
            $this->addLog(array(
                'log_code' => 9,
                'user_id' => $_SESSION['admin']['id'],
                'history' => $history,
                'mod_id' => $product_id
            ));
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['product'] = $product_info;
        return $result;
    }

    /**
     * Обновить информацию о товаре
     * @return array
     */
    public function updateProduct($product_info){
        $error = '';
        $notice = '';
        try{
            $id = $product_info['id'];
            $parent_id = $product_info['parent_id'];
            $popular = (isset($product_info['popular'])) ? 1 : 0;
            $new = (isset($product_info['new'])) ? 1 : 0;
            $title = $product_info['title'];
            $url = $product_info['url'] ?: CommonFunctions::translit(mb_strtolower($product_info['title'], 'utf8'));
            $description = $product_info['description'];
            $text = $product_info['text'];
            $price = $product_info['price'];
            $price_sale = $product_info['price_sale'];
            $rate = $product_info['rate'];
            $barcode = $product_info['barcode'];
            $sales = isset($product_info['sales']) ? implode(',', $product_info['sales']) : "";
            $goods = isset($product_info['goods']) ? implode(',', $product_info['goods']) : "";

            $check_url = $this->checkUrlExists('products', $url, $id);
            if ($check_url['error']) {
                $error = $check_url['error'];
            }
            else {
                $db = static::getDB();
                $res = $db->prepare("UPDATE products 
                                          SET                                            
                                            parent_id = :parent_id,
                                            popular = :popular,
                                            new = :new,
                                            title = :title,                                           
                                            url = :url,
                                            description = :description,
                                            text = :text,
                                            price = :price,
                                            price_sale = :price_sale,
                                            rate = :rate,
                                            sales = :sales,
                                            barcode = :barcode,
                                            goods = :goods
                                          WHERE id = :id");
                $res->bindValue(':id', $id, PDO::PARAM_INT);
                $res->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
                $res->bindValue(':popular', $popular, PDO::PARAM_INT);
                $res->bindValue(':new', $new, PDO::PARAM_INT);
                $res->bindValue(':title', $title);
                $res->bindValue(':url', $url);
                $res->bindValue(':description', $description);
                $res->bindValue(':text', $text);
                $res->bindValue(':price', $price, PDO::PARAM_INT);
                $res->bindValue(':price_sale', $price_sale, PDO::PARAM_INT);
                $res->bindValue(':rate', $rate, PDO::PARAM_INT);
                $res->bindValue(':sales', $sales);
                $res->bindValue(':barcode', $barcode);
                $res->bindValue(':goods', $goods);
                $res->execute();

                $update_photos = array();
                $update_photos['img'] = (isset($product_info['img'])) ? $product_info['img'] : array();
                $update_photos['img_id'] = (isset($product_info['img_id'])) ? $product_info['img_id'] : array();
                $update_photos['img_alt'] = (isset($product_info['img_alt'])) ? $product_info['img_alt'] : array();
                $update_photos['img_rate'] = (isset($product_info['img_rate'])) ? $product_info['img_rate'] : array();
                $save_photos_res = $this->savePhotos($id, 'product', $_FILES, $update_photos);
                $error .= ($save_photos_res['error']) ?: '';

                $save_chars_res = $this->saveProductChars($id, $_POST['chars']);
                $error .= ($save_chars_res['error']) ?: '';

                $notice = (!$error) ? 'Товар успешно отредактирован' : '';

                $product_info['chars'] = $this->getProductChars($id)['chars'];
                $product_info['photos'] = $this->getItemImages($id, 'product');
                $product_info['popular'] = $popular;
                $product_info['new'] = $new;
                $product_info['url'] = $url;
                $product_info['sales'] = ($sales) ? explode(',', $sales) : '';
                $product_info['goods_array'] = ($goods) ? explode(',', $goods) : '';
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о товаре';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['product'] = $product_info;
        return $result;
    }

    /**
     * Работе с галереей
     * @param $item_id
     * @param $item_type
     * @param $photos
     * @param $update_photos
     * @return array
     */
    private function savePhotos($item_id, $item_type, $photos, $update_photos = array()){
        $error = '';
        $db = static::getDB();
        $folder_type = ($item_type == 'product') ? "gallery" : "pages";
        $folder = $_SERVER['DOCUMENT_ROOT']."/images/$folder_type/$item_id";
        if (count($photos) && $photos['photos']['name'][0]) {
            if (!file_exists($folder) && !mkdir($folder)) {
                $error = 'Ошибка создания директории';
            }
            else {
                if ($item_type == 'product') {
                    $res = $db->prepare("INSERT INTO gallery 
                                                    SET
                                                      type = :type,
                                                      page_id = :page_id,
                                                      path = :path,
                                                      path_small = :path_small,
                                                      path_middle = :path_middle,
                                                      path_large = :path_large");
                    $res->bindParam(':type', $item_type);
                    $res->bindParam(':page_id', $item_id);
                    $res->bindParam(':path', $path);
                    $res->bindParam(':path_small', $path_small);
                    $res->bindParam(':path_middle', $path_middle);
                    $res->bindParam(':path_large', $path_large);

                    $count = time();
                    $num = count($photos['photos']['name']);
                    for($i = 0; $i < $num; $i++){
                        $count++;
                        $filename = $photos['photos']['name'][$i];
                        $path_info = pathinfo($filename);
                        $extension = $path_info['extension'];
                        $path = $count.".".$extension;

                        $path_small = $count."_small.".$extension;
                        $path_middle = $count."_middle.".$extension;
                        $path_large = $count."_large.".$extension;

                        if (is_uploaded_file($photos['photos']['tmp_name'][$i])) {
                            move_uploaded_file($photos['photos']['tmp_name'][$i], $folder.'/'.$path);

                            CommonFunctions::resizeImage($folder.'/'.$path, $folder.'/'.$path_small, 122, 122);
                            CommonFunctions::resizeImage($folder.'/'.$path, $folder.'/'.$path_middle, 188, 188);
                            CommonFunctions::resizeImage($folder.'/'.$path, $folder.'/'.$path_large, 410, 410);

                            try {
                                $res->execute();
                            }
                            catch (PDOException $e){
                                Error::logError($e);
                                $error = 'Ошибка сохранения изображений';
                            }
                        }
                    }
                }
                else {
                    $res = $db->prepare("INSERT INTO gallery 
                                                    SET
                                                      type = :type,
                                                      page_id = :page_id,
                                                      path = :path,
                                                      path_middle = :path_middle");
                    $res->bindParam(':type', $item_type);
                    $res->bindParam(':page_id', $item_id);
                    $res->bindParam(':path', $path);
                    $res->bindParam(':path_middle', $path_middle);

                    $count = time();
                    $num = count($photos['photos']['name']);
                    for($i = 0; $i < $num; $i++) {
                        $count++;
                        $filename = $photos['photos']['name'][$i];
                        $path_info = pathinfo($filename);
                        $extension = $path_info['extension'];
                        $path = $count . "." . $extension;

                        $path_middle = $count . "_middle." . $extension;

                        if (is_uploaded_file($photos['photos']['tmp_name'][$i])) {
                            move_uploaded_file($photos['photos']['tmp_name'][$i], $folder . '/' . $path);
                            CommonFunctions::resizeImage($folder.'/'.$path, $folder.'/'.$path_middle, 160, 160);

                            try {
                                $res->execute();
                            } catch (PDOException $e) {
                                Error::logError($e);
                                $error = 'Ошибка сохранения изображений';
                            }
                        }
                    }
                }
            }
        }

        $img_ids = $update_photos['img_id'];
        $img_alts = $update_photos['img_alt'];
        $img_rates = $update_photos['img_rate'];
        if(count($img_ids)){
            $i = 0;
            try {
                $res = $db->prepare("UPDATE gallery SET alt=:alt, rate=:rate WHERE id=:id");
                $res->bindParam(':id', $param_id, PDO::PARAM_INT);
                $res->bindParam(':alt', $param_alt);
                $res->bindParam(':rate', $param_rate, PDO::PARAM_INT);
                foreach($img_ids as $key => $photo_id){
                    $param_id = $photo_id;
                    $param_alt = $img_alts[$i];
                    $param_rate = $img_rates[$i];
                    $res->execute();
                    $i++;
                }
            }
            catch (PDOException $e){
                Error::logError($e);
                $error = 'Ошибка обновления метаданных изображений';
            }
        }

        $img = $update_photos['img'];
        if (count($img)){
            try {
                $res = $db->prepare("SELECT * FROM gallery WHERE id=:id");
                $res->bindParam(':id', $photo_id, PDO::PARAM_INT);

                $res_gal = $db->prepare("DELETE FROM gallery WHERE id=:id");
                $res_gal->bindParam(':id', $photo_id, PDO::PARAM_INT);

                foreach ($img as $key => $photo_id){
                    $res->execute();
                    $row = $res->fetch();

                    unlink($folder."/".trim($row['path']));
                    unlink($folder."/".trim($row['path_small']));

                    $res_gal->execute();
                }
            }
            catch (PDOException $e){
                Error::logError($e);
                $error = 'Ошибка удаления выбранных изображений';
            }
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Сохранить характеристики товара
     * @param $product_id
     * @param $update_product_chars
     * @return array
     */
    private function saveProductChars($product_id, $update_product_chars){
        $error = '';
        $product_chars = $this->getProductChars($product_id);
        if ($product_chars['error']) {
            $error = $product_chars['error'];
        }
        $product_chars = $product_chars['chars'];

        try{
            $db = self::getDB();
            $res_update = $db->prepare("UPDATE characteristics
                                              SET
                                                char_value_id = :char_value_id 
                                              WHERE 
                                                product_id = :product_id 
                                                AND char_id = :char_id");
            $res_update->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $res_update->bindParam(':char_id', $product_char_id, PDO::PARAM_INT);
            $res_update->bindParam(':char_value_id', $product_char_value_id, PDO::PARAM_INT);

            $db = self::getDB();
            $res_delete = $db->prepare("DELETE FROM characteristics                                             
                                              WHERE 
                                                product_id = :product_id 
                                                AND char_id = :char_id");
            $res_delete->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $res_delete->bindParam(':char_id', $product_char_id, PDO::PARAM_INT);

            $res_insert = $db->prepare("INSERT INTO characteristics
                                              SET
                                                product_id = :product_id,
                                                char_id = :char_id,
                                                char_value_id = :char_value_id");
            $res_insert->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $res_insert->bindParam(':char_id', $product_char_id, PDO::PARAM_INT);
            $res_insert->bindParam(':char_value_id', $product_char_value_id, PDO::PARAM_INT);

            foreach($update_product_chars as $product_char_id => $product_char_value){
                $product_char_value = trim($product_char_value);
                if ($product_char_value){
                    $ref_char_values = $this->getRefCharValues($product_char_id);
                    $product_char_value_id = 0;
                    foreach ($ref_char_values as $ref_char_value){
                        if ($ref_char_value['value'] == $product_char_value){
                            $product_char_value_id = $ref_char_value['id'];
                        }
                    }

                    if (!$product_char_value_id) {
                        $add_char_value_res = $this->addRefCharValue($product_char_id, $product_char_value);
                        if ($add_char_value_res['error']) {
                            $error = $add_char_value_res['error'];
                        }
                        else {
                            $product_char_value_id = $add_char_value_res['ref_char_value_id'];
                        }
                    }

                    if ($product_char_value_id) {
                        if (isset($product_chars[$product_char_id])){
                            if ($product_chars[$product_char_id]['id'] != $product_char_value_id){
                                $res_update->execute();
                            }
                        }
                        else {
                            $res_insert->execute();
                        }
                    }
                }
                else {
                    $res_delete->execute();
                }
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка';
        }


        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Удалить товар
     * @param $id int идентификатор товара
     * @return array
     */
    public function deleteProduct($id){
        $notice = '';
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("DELETE FROM products WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();

            $notice = 'Товар успешно удален';
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка удаления товара';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Получить все характеристики
     * @return bool|mixed     *
     */
    public function getRefChars(){
        $error = '';
        $ref_chars = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM ref_chars ORDER BY rate DESC");
            $res->execute();
            while ($ref_char = $res->fetch(PDO::FETCH_ASSOC)){
                $ref_chars[] = $ref_char;
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['ref_chars'] = $ref_chars;
        return $result;
    }

    /**
     * Получить характеристику
     * @return bool|mixed     *
     */
    public function getRefChar($id){
        $error = '';
        $ref_char = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM ref_chars WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $ref_char = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['ref_char'] = $ref_char;
        return $result;
    }

    /**
     * Добавить характеристику
     * @param $ref_char_info
     * @return array
     */
    public function addRefChar($ref_char_info){
        $notice = '';
        $error = '';
        try{
            $title = $ref_char_info['title'];
            $rate = $ref_char_info['rate'];

            $db = static::getDB();
            $res = $db->prepare("INSERT INTO ref_chars 
                                          SET
                                            title = :title,
                                            rate = :rate");
            $res->bindValue(':title', $title);
            $res->bindValue(':rate', $rate, PDO::PARAM_INT);
            $res->execute();

            $ref_char_id = $db->lastInsertId();
            $notice = 'Характеристика успешно добавлена';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о характеристике';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['ref_char_info'] = $ref_char_info;
        $result['ref_char_id'] = $ref_char_id;
        return $result;
    }

    /**
     * Получить все значения характеристик
     * @return bool|mixed     *
     */
    public function getRefCharsValues(){
        $error = '';
        $ref_chars_values = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT 
                                          ref_chars_values.id, 
                                          ref_chars.title as char_title, 
                                          ref_chars_values.value as value_title,
                                          ref_chars_values.rate
                                        FROM ref_chars
                                        LEFT JOIN ref_chars_values ON char_id = ref_chars.id
                                        ORDER BY ref_chars.id ASC, ref_chars_values.rate DESC");
            $res->execute();
            while ($ref_char_values = $res->fetch(PDO::FETCH_ASSOC)){
                $ref_chars_values[] = $ref_char_values;
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['ref_chars_values'] = $ref_chars_values;
        return $result;
    }

    /**
     * Добавить значение характеристики
     * @return array
     */
    public function addRefCharValue($char_id, $char_value){
        $error = '';
        $ref_char_value_id = 0;
        try{
            $db = static::getDB();
            $res = $db->prepare("INSERT INTO ref_chars_values
                                          SET
                                            char_id = :char_id,
                                            value = :value");
            $res->bindValue(':char_id', $char_id, PDO::PARAM_INT);
            $res->bindValue(':value', $char_value);
            $res->execute();

            $ref_char_value_id = $db->lastInsertId();

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления значения характеристики';
        }

        $result = array();
        $result['error'] = $error;
        $result['ref_char_value_id'] = $ref_char_value_id;
        return $result;
    }

    /**
     * Обновить характеристику
     * @param $ref_char_info
     * @return array
     */
    public function updateRefChar($ref_char_info){
        $notice = '';
        $error = '';
        try{
            $id = $ref_char_info['id'];
            $title = $ref_char_info['title'];
            $rate = $ref_char_info['rate'];

            $db = static::getDB();
            $res = $db->prepare("UPDATE ref_chars 
                                          SET  
                                            title = :title,
                                            rate = :rate
                                          WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->bindValue(':title', $title);
            $res->bindValue(':rate', $rate, PDO::PARAM_INT);
            $res->execute();

            $notice = 'Характеристика успешно отредактирована';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о характеристике';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Удалить характеристику
     * @param int $id идентификатор
     * @return array
     */
    public function deleteRefChars($id){
        $notice = '';
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("DELETE FROM ref_chars WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();

            $notice = 'Характеристика успешно удалена';
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка удаления товара';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Получить значения характеристики (при указании value значения будут отфильтрованы по совпадению с искомой фразой)
     * @param $char_id
     * @param bool $value
     * @return array
     */
    public function getRefCharValues($char_id, $value = false){
        $ref_chars = array();
        try{
            $db = static::getDB();
            if ($value) {
                $res = $db->prepare("SELECT * 
                                              FROM ref_chars_values 
                                              WHERE 
                                                char_id = :char_id 
                                                AND value LIKE :value");
                $res->bindValue(':char_id', $char_id, PDO::PARAM_INT);
                $res->bindValue(':value', "%$value%");
            }
            else {
                $res = $db->prepare("SELECT * FROM ref_chars_values WHERE char_id = :char_id");
                $res->bindValue(':char_id', $char_id, PDO::PARAM_INT);
            }
            $res->execute();
            while ($ref_char = $res->fetch(PDO::FETCH_ASSOC)){
                $ref_chars[] = $ref_char;
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $ref_chars['error'] = 'Ошибка получения данных';
        }

        return $ref_chars;
    }

    /**
     * Проверить логин и пароль
     * @param $login
     * @param $password
     * @return array
     */
    public function checkUser($login, $password){
        $db = static::getDB();
        $check = false;
        $res = $db->prepare("SELECT * FROM users WHERE login = :login");
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

        $result = array();
        $result['check'] = $check;
        $result['user'] = $row;
        return $result;
    }

    /**
     * Пользователи
     * @return bool|mixed
     */
    public function getUsers(){
        $users = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM users ORDER BY id DESC");
            $res->execute();
            $users = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['users'] = $users;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Пользователь
     * @param $id
     * @return array
     */
    public function getUser($id){
        $user = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $res->bindValue(":id", $id, PDO::PARAM_INT);
            $res->execute();
            $user = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['user'] = $user;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Обновить информацию о пользователе
     * @param $user_info
     * @return array
     */
    public function updateUser($user_info){
        $error = '';
        $notice = '';
        try{
            $id = $user_info['id'];
            $login = $user_info['login'];
            $password = $user_info['password'];

            $db = static::getDB();
            $password = md5($password);
            $salt = md5(rand());
            $password = md5($password.$salt);

            $res = $db->prepare("UPDATE users SET login = :login, password = :password, salt = :salt WHERE id=:id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->bindValue(':login', $login);
            $res->bindValue(':password', $password);
            $res->bindValue(':salt', $salt);
            $res->execute();

            $notice = 'Пользователь успешно отредактирован';
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о пользователе';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['user'] = $user_info;
        return $result;
    }

    /**
     * Добавить пользователя
     * @param $user_info
     * @return array
     */
    public function addUser($user_info){
        $error = '';
        $notice = '';
        try{
            $login = $user_info['login'];
            $password = $user_info['password'];

            $db = static::getDB();
            $password = md5($password);
            $salt = md5(rand());
            $password = md5($password.$salt);

            $res = $db->prepare("INSERT INTO users SET login = :login, password = :password, salt = :salt");
            $res->bindValue(':login', $login);
            $res->bindValue(':password', $password);
            $res->bindValue(':salt', $salt);
            $res->execute();

            $user_info['id'] = $db->lastInsertId();

            $notice = 'Пользователь успешно добавлен';
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления пользователя';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['user'] = $user_info;
        return $result;
    }

    /**
     * Заказы
     * @param string $status
     * @return array
     */
    public function getOrders($status){
        $orders = array();
        $error = '';
        try{
            $db = static::getDB();
            $params = array();
            if ($status){
                $status = explode(',', $status);
                $format_params = $this->getQuestionMarkPlaceholders($status);
                $where_status = " AND o.status IN ({$format_params['params']}) ";
                $params = array_merge($params, $status);
            }
            else {
                $where_status = '';
            }

            $res = $db->prepare("SELECT 
                                              o.*, 
                                              rpt.title as payment_type_title,
                                              rr.name as region_name
                                            FROM orders o
                                            LEFT JOIN ref_payment_types rpt ON rpt.id = o.payment_type
                                            LEFT JOIN ref_regions rr ON rr.id = o.region
                                            WHERE 1 $where_status
                                            ORDER BY o.id DESC");
            $res->execute($params);
            while($order = $res->fetch(PDO::FETCH_ASSOC)){
                $orders[$order['id']] = $order;
                $orders[$order['id']]['address_title'] = $this->getFormatAddress($order);
                $orders[$order['id']]['pvz_title'] = ($order['pvz_id']) ? $this->getPvzItem($order['pvz_id'])['title'] : '';
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['orders'] = $orders;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Логи заказа
     * @param $order_id
     * @return array
     */
    public function getOrderLogs($order_id){
        $logs = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT 
                                            l.*, 
                                            u.login as user_login
                                        FROM log l
                                        LEFT JOIN users u ON u.id = l.user_id
                                        WHERE 
                                            mod_id = :order_id 
                                            AND log_code = 5
                                        ORDER BY l.time DESC, l.id DESC");
            $res->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $res->execute();
            $logs = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['logs'] = $logs;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Выполнить заказ
     * @param $id
     * @return array
     */
    public function setOrderCompleted($id) {
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("UPDATE orders
                                          SET
                                            status = 4, 
                                            complete_date = :complete_date
                                          WHERE id = :id");
            $complete_date = time();
            $res->bindValue(":complete_date", $complete_date, PDO::PARAM_INT);
            $res->bindValue(":id", $id, PDO::PARAM_INT);
            $res->execute();

            $add_log = $this->addLog(array(
                'log_code' => 5,
                'user_id' => $_SESSION['admin']['id'],
                'history' => 'Заказ выполнен.',
                'mod_id' => $id
            ));
            if ($add_log['error']) {
                $error = $add_log['error'];
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка изменения статуса заказа';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Отклонить заказ
     * @param $id
     * @return array
     */
    public function setOrderDeclined($id) {
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("UPDATE orders
                                          SET
                                            status = 5, 
                                            decline_date = :decline_date
                                          WHERE id = :id");
            $decline_date = time();
            $res->bindValue(":decline_date", $decline_date, PDO::PARAM_INT);
            $res->bindValue(":id", $id, PDO::PARAM_INT);
            $res->execute();

            $add_log = $this->addLog(array(
                'log_code' => 5,
                'user_id' => $_SESSION['admin']['id'],
                'history' => 'Заказ отклонен.',
                'mod_id' => $id
            ));
            if ($add_log['error']) {
                $error .= $add_log['error'];
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка изменения статуса заказа';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Проверить существование url
     * @param $table
     * @param $url
     * @param $id
     * @return array
     */
    public function checkUrlExists($table, $url, $id = 0){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT NULL
                                          FROM $table                                       
                                          WHERE 
                                            url = :url 
                                            AND id != :id");
            $res->bindValue(":url", $url);
            $res->bindValue(":id", $id, PDO::PARAM_INT);
            $res->execute();
            $count = $res->rowCount();
            if ($count) {
                $error = "Такой url уже существует в базе";
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка выборки информации';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Движения
     * @return array
     */
    public function getHistories(){
        $histories = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT 
                                          h.*, 
                                          rd.title as dir_title,
                                          u.login,
                                          p.title as product_title
                                        FROM history h
                                        LEFT JOIN users u On u.id = h.user_id
                                        LEFT JOIN products p ON p.id = h.product_id
                                        LEFT JOIN ref_dir rd ON rd.id = h.dir
                                        ORDER BY h.time DESC");
            $res->execute();
            $histories = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['histories'] = $histories;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Добавить движение
     * @param $history_info
     * @return array
     */
    public function addHistory($history_info):array{
        $notice = '';
        $error = '';
        try{
            if (!$history_info['product_id']) {
                $error = "Не выбран продукт";
            }
            else if (!$history_info['dir']) {
                $error = "Не указан тип движения";
            }
            else if (!$history_info['qty'] || (int)$history_info['qty'] <= 0) {
                $error = "Не указано количество";
            }
            else {
                $time = time();
                $doc_date = (isset($history_info['doc_date']) && $history_info['doc_date']) ? strtotime($history_info['doc_date']) : 0;
                $doc_name = (isset($history_info['doc_name'])) ? $history_info['doc_name'] : '';
                $doc_num = (isset($history_info['doc_num'])) ? $history_info['doc_num'] : '';
                $supp_title = (isset($history_info['supp_title'])) ? $history_info['supp_title'] : '';
                $supp_inn = (isset($history_info['supp_inn'])) ? $history_info['supp_inn'] : '';
                $db = static::getDB();
                $res = $db->prepare("INSERT INTO history 
                                          SET
                                            time = :time,
                                            product_id = :product_id,
                                            user_id = :user_id,
                                            dir = :dir,
                                            doc_name = :doc_name,
                                            doc_date = :doc_date,
                                            doc_num = :doc_num,
                                            qty = :qty,
                                            supp_title = :supp_title,
                                            supp_inn = :supp_inn");

                $res->bindValue(":time", $time, PDO::PARAM_INT);
                $res->bindValue(":product_id", $history_info['product_id'], PDO::PARAM_INT);
                $res->bindValue(":user_id", $history_info['user_id'], PDO::PARAM_INT);
                $res->bindValue(":dir", $history_info['dir']);
                $res->bindValue(":doc_name", $doc_name);
                $res->bindValue(":doc_date", $doc_date, PDO::PARAM_INT);
                $res->bindValue(":doc_num", $doc_num);
                $res->bindValue(":qty", (int)$history_info['qty'], PDO::PARAM_INT);
                $res->bindValue(":supp_title", $supp_title);
                $res->bindValue(":supp_inn", $supp_inn);
                $res->execute();

                $history_id = $db->lastInsertId();

                //запись логов
                if ($history_info['dir'] == 1) {
                    $data = array();
                    $data['log_code'] = 1;
                    $data['user_id'] = $history_info['user_id'];
                    $history = "Добавлен приход (id прихода = $history_id). {$history_info['doc_name']} №{$history_info['doc_num']} от ".date('d.m.Y', $doc_date).".";
                    $history .= "Количество: {$history_info['qty']} ед.";
                    $data['history'] = $history;
                    $data['mod_id'] = $history_info['product_id'];
                    $add_log = $this->addLog($data);
                    if ($add_log['error']) {
                        $error = $add_log['error'];
                    }
                }
                else if ($history_info['dir'] == 2) {
                    $data = array();
                    $data['log_code'] = 2;
                    $data['user_id'] = $history_info['user_id'];
                    $history = "Добавлена выдача (id выдачи = $history_id). Количество: {$history_info['qty']} ед.";
                    $data['history'] = $history;
                    $data['mod_id'] = $history_info['product_id'];
                    $add_log = $this->addLog($data);
                    if ($add_log['error']) {
                        $error = $add_log['error'];
                    }
                }

                //изменение количества товара
                $update_info = array();
                $update_info['product_id'] = $history_info['product_id'];
                $update_info['dir'] = $history_info['dir'];
                $update_info['qty'] = $history_info['qty'];
                $update_info['history_id'] = $history_id;
                $update_info['user_id'] = $history_info['user_id'];
                $update_count = $this->updateProductCount($update_info);
                if ($update_count['error']){
                    $error = $update_count['error'];
                }

                $notice = 'Движение успешно добавлено';
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о движении';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    private function updateProductCount($update_info){
        $error = '';
        try{
            if ($update_info['dir'] == 1){
                $get_product = $this->getProduct($update_info['product_id']);
                if ($get_product['error']){
                    $error = $get_product['error'];
                }
                else {
                    $product = $get_product['product'];

                    $db = static::getDB();
                    $res = $db->prepare("UPDATE products SET qty = qty + :qty WHERE id = :id");
                    $res->bindValue(":qty", $update_info['qty'], PDO::PARAM_INT);
                    $res->bindValue(":id", $update_info['product_id'], PDO::PARAM_INT);
                    $res->execute();

                    $data = array();
                    $data['log_code'] = 3;
                    $data['user_id'] = $update_info['user_id'];
                    $history = "Добавлено {$update_info['qty']} ед. в связи с приходом (id прихода = {$update_info['history_id']}). ";
                    $history .= "Текущий баланс: ".($product['qty']+$update_info['qty'])." ед.";
                    $data['history'] = $history;
                    $data['mod_id'] = $update_info['product_id'];
                    $add_log = $this->addLog($data);
                    if ($add_log['error']) {
                        $error = $add_log['error'];
                    }
                }
            }
            else if ($update_info['dir'] == 2){
                $get_product = $this->getProduct($update_info['product_id']);
                if ($get_product['error']){
                    $error = $get_product['error'];
                }
                else {
                    $product = $get_product['product'];

                    $db = static::getDB();
                    $res = $db->prepare("UPDATE products SET qty = qty - :qty WHERE id = :id");
                    $res->bindValue(":qty", $update_info['qty'], PDO::PARAM_INT);
                    $res->bindValue(":id", $update_info['product_id'], PDO::PARAM_INT);
                    $res->execute();

                    $data = array();
                    $data['log_code'] = 3;
                    $data['user_id'] = $update_info['user_id'];
                    $history = "Списано {$update_info['qty']} ед. в связи с выдачей (id выдачи = {$update_info['history_id']}). ";
                    $history .= "Текущий баланс: ".($product['qty'] - $update_info['qty'])." ед.";
                    $data['history'] = $history;
                    $data['mod_id'] = $update_info['product_id'];
                    $add_log = $this->addLog($data);
                    if ($add_log['error']) {
                        $error = $add_log['error'];
                    }
                }
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о движении';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Логи
     * @return array
     */
    public function getLogs(){
        $logs = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT 
                                          l.*, 
                                          lc.name as log_code_name,
                                          p.title as product_title,
                                          pages.title as page_title,
                                          u.login
                                        FROM log l
                                        LEFT JOIN log_codes lc On lc.id = l.log_code
                                        LEFT JOIN products p ON p.id = l.mod_id
                                        LEFT JOIN users u ON u.id = l.user_id
                                        LEFT JOIN pages  ON pages.id = l.mod_id
                                        ORDER BY l.time DESC, l.id DESC");
            $res->execute();
            while ($res_logs = $res->fetch(PDO::FETCH_ASSOC)){
                $logs[$res_logs['id']] = $res_logs;

                $mod_link = "";
                $mod_title = "";
                if ($res_logs['log_code'] == 5) {
                    $mod_link = "/admin/orders?act=show&id={$res_logs['mod_id']}";
                    $mod_title = "Заказ №{$res_logs['mod_id']}";
                }
                else if ($res_logs['log_code'] == 7) {
                    $mod_link = "/admin/site_users";
                    $mod_title = $res_logs['mod_id'];
                }
                else if ($res_logs['log_code'] == 8) {
                    $mod_link = "/admin/pages?act=edit&id={$res_logs['mod_id']}";
                    $mod_title = $res_logs['page_title'];
                }
                else if ($res_logs['log_code'] != 4) {
                    $mod_link = "/admin/products?act=edit&id={$res_logs['mod_id']}";
                    $mod_title = $res_logs['product_title'];
                }
                $logs[$res_logs['id']]['mod_link'] = $mod_link;
                $logs[$res_logs['id']]['mod_title'] = $mod_title;
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['logs'] = $logs;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Обновить рейтинг
     * @param $table
     * @param $array_rate
     * @return array
     */
    public function updateRate($table, $array_rate){
        $error = '';
        try{
            $db = static::getDB();
            if ($table == 'pages') {
                $res = $db->prepare("UPDATE pages SET rate=:rate WHERE id=:id");
            }
            else if ($table == 'products') {
                $res = $db->prepare("UPDATE products SET rate=:rate WHERE id=:id");
            }
            else if ($table == 'ref_chars') {
                $res = $db->prepare("UPDATE ref_chars SET rate=:rate WHERE id=:id");
            }
            else if ($table == 'banners') {
                $res = $db->prepare("UPDATE banners SET rate=:rate WHERE id=:id");
            }
            else if ($table == 'banners_catalog') {
                $res = $db->prepare("UPDATE banners_catalog SET rate=:rate WHERE id=:id");
            }
            else if ($table == 'ref_chars_values') {
                $res = $db->prepare("UPDATE ref_chars_values SET rate=:rate WHERE id=:id");
            }
            else if ($table == 'brands') {
                $res = $db->prepare("UPDATE brands SET rate=:rate WHERE id=:id");
            }
            else if ($table == 'pvz') {
                $res = $db->prepare("UPDATE pvz SET rate=:rate WHERE id=:id");
            }
            else if ($table == 'admin_menu') {
                $res = $db->prepare("UPDATE admin_menu SET rate=:rate WHERE id=:id");
            }
            else {
                $error = 'Ошибка передачи таблицы';
            }

            if (!$error){
                $res->bindParam(":id", $key, PDO::PARAM_INT);
                $res->bindParam(":rate", $value, PDO::PARAM_INT);
                foreach ($array_rate as $key => $value){
                    $res->execute();
                }
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления рейтинга';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Добавить товар
     * @param $order_id
     * @return array
     */
    public function addOrderProduct($order_id){
        $error = '';
        $order_product_id = 0;
        try{
            $db = static::getDB();
            $res = $db->prepare("INSERT INTO order_products SET order_id = :order_id");
            $res->bindValue(":order_id", $order_id, PDO::PARAM_INT);
            $res->execute();
            $order_product_id = $db->lastInsertId();
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления товара';
        }

        $result = array();
        $result['error'] = $error;
        $result['order_product_id'] = $order_product_id;
        return $result;
    }

    /**
     * Изменить товар
     * @param $order_id
     * @return array
     */
    public function updateOrderProduct($order_product_id, $product_id, $count){
        $error = '';
        $order_product_info = array();
        $change_count = 0;
        try{
            $product = $this->getProductPriceAndQty($product_id);
            if ($product['error']){
                $error = $product['error'];
            }
            else {
                $product = $product['product'];

                $order_product = $this->getOrderProductCount($order_product_id);
                if ($order_product['error']){
                    $error = $order_product['error'];
                }
                else {
                    $free_count = $product['free_qty'] + $order_product['count'];
                    if ($free_count < $count) {
                        $count = $free_count;
                        $change_count = 1;
                    }
                    $cost = $product['price'] * $count;
                    $db = static::getDB();
                    $res = $db->prepare("UPDATE order_products 
                                          SET 
                                            product_id = :product_id,
                                            price = :price,
                                            count = :count,
                                            ct = :ct,
                                            cost = :cost
                                          WHERE id = :id");
                    $res->bindValue(":product_id", $product_id, PDO::PARAM_INT);
                    $res->bindValue(":price", $product['price'], PDO::PARAM_INT);
                    $res->bindValue(":count", $count, PDO::PARAM_INT);
                    $res->bindValue(":ct", $product['ct'], PDO::PARAM_INT);
                    $res->bindValue(":cost", $cost, PDO::PARAM_INT);
                    $res->bindValue(":id", $order_product_id, PDO::PARAM_INT);
                    $res->execute();

                    $order_product_info['price'] = $product['price'];
                    $order_product_info['cost'] = $cost;
                    $order_product_info['count'] = $count;
                }
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления товара';
        }

        $result = array();
        $result['error'] = $error;
        $result['order_product_info'] = $order_product_info;
        $result['change_count'] = $change_count;
        return $result;
    }

    /**
     * Получить цену и свободное кол-во товара
     * @param $id int идентификатор товара
     * @return array
     */
    public function getProductPriceAndQty($id){
        $error = '';
        $product = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT IF(price_sale, price_sale, price) as price, qty, ct FROM products WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $product = $res->fetch(PDO::FETCH_ASSOC);

            $reserved_count_record = $this->getReservedCount($id);
            if ($reserved_count_record['error']) {
                $error = $reserved_count_record['error'];
            }
            $reserved_count = $reserved_count_record['reserved_count'];
            $product['free_qty'] = $product['qty'] - $reserved_count;
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных о товаре';
        }

        $result = array();
        $result['error'] = $error;
        $result['product'] = $product;
        return $result;
    }

    /**
     * Получить зарезервированное количество единицы заказа
     * @param $id int идентификатор записи в таблице заказов
     * @return array
     */
    public function getOrderProductCount($id){
        $error = '';
        $count = 0;
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT count FROM order_products WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $count = $res->fetch(PDO::FETCH_ASSOC);
            $count = $count['count'];
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных о позиции заказа';
        }

        $result = array();
        $result['error'] = $error;
        $result['count'] = $count;
        return $result;
    }

    /**
     * Удалить единицу заказа
     * @param $id int идентификатор записи в таблице заказов
     * @return array
     */
    public function deleteOrderProduct($id){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("DELETE FROM order_products WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных о позиции заказа';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Обновить стоимость
     * @param $id int идентификатор записи в таблице заказов
     * @return array
     */
    public function calcOrder($id){
        $error = '';
        $total_cost = 0;
        $delivery_cost = 0;
        $cart_cost = 0;
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT IFNULL(SUM(cost), 0) as cart_cost 
                                          FROM order_products 
                                          WHERE order_id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $cart_cost = $res->fetch(PDO::FETCH_ASSOC);
            $cart_cost = $cart_cost['cart_cost'];

            $get_order = $this->getOrder($id);
            if ($get_order['error']) {
                $error = $get_order['error'];
            }
            else {
                $order = $get_order['order'];
                $total_cost = $cart_cost + $order['order']['delivery_cost'];

                $res = $db->prepare("UPDATE orders 
                                              SET
                                                cart_cost = :cart_cost,
                                                total_cost = :total_cost
                                              WHERE id = :id");
                $res->bindValue(':id', $id, PDO::PARAM_INT);
                $res->bindValue(':cart_cost', $cart_cost, PDO::PARAM_INT);
                $res->bindValue(':total_cost', $total_cost, PDO::PARAM_INT);
                $res->execute();
            }

            /*$get_settings = $this->getSettings();
            if ($get_settings['error']) {
                $error = $get_settings['error'];
            }
            else {
                $settings = $get_settings['settings'];
                $delivery_cost = ($cart_cost >= $settings['delivery_sum_free']) ? 0 : $settings['delivery_cost'];
                $total_cost = $cart_cost + $delivery_cost;

                $res = $db->prepare("UPDATE orders
                                              SET
                                                cart_cost = :cart_cost,
                                                delivery_cost = :delivery_cost,
                                                total_cost = :total_cost
                                              WHERE id = :id");
                $res->bindValue(':id', $id, PDO::PARAM_INT);
                $res->bindValue(':cart_cost', $cart_cost, PDO::PARAM_INT);
                $res->bindValue(':delivery_cost', $delivery_cost, PDO::PARAM_INT);
                $res->bindValue(':total_cost', $total_cost, PDO::PARAM_INT);
                $res->execute();
            }*/

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных о позиции заказа';
        }

        $result = array();
        $result['error'] = $error;
        $result['cart_cost'] = $cart_cost;
        $result['delivery_cost'] = $delivery_cost;
        $result['total_cost'] = $total_cost;
        return $result;
    }

    /**
     * Получить баннер
     * @return bool|mixed
     */
    public function getBanner($id){
        $error = '';
        $banner = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM banners WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $banner = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['banner'] = $banner;
        return $result;
    }

    /**
     * Обновить баннер
     * @param $banner_info
     * @return array
     */
    public function updateBanner($banner_info){
        $notice = '';
        $error = '';
        try{
            $id = $banner_info['id'];
            $text1 = $banner_info['text1'];
            $text2 = $banner_info['text2'];
            $alt = $banner_info['alt'];
            $rate = $banner_info['rate'];
            $url = $banner_info['url'];

            $path = $banner_info['path'];
            if (isset($_POST['del_file'])) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/banners/".$path);
                $path = "";
            }
            if (isset($_FILES['banner']) && $_FILES['banner']['name']) {
                if ($path) {
                    unlink($_SERVER['DOCUMENT_ROOT']."/images/banners/".$path);
                }

                $path_info = pathinfo($_FILES['banner']['name']);
                $extension = $path_info['extension'];
                $path = time() . "." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['banner']['tmp_name'])){
                    if (in_array($_FILES['banner']['type'], $types)){
                        $banner_path = $_SERVER['DOCUMENT_ROOT'].'/images/banners/'.$path;
                        move_uploaded_file($_FILES['banner']['tmp_name'], $banner_path);
                    }
                }
            }

            $path2 = $banner_info['path2'];
            if (isset($_POST['del_file2'])) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/banners/".$path2);
                $path2 = "";
            }
            if (isset($_FILES['banner2']) && $_FILES['banner2']['name']) {
                if ($path2) {
                    unlink($_SERVER['DOCUMENT_ROOT']."/images/banners/".$path2);
                }

                $path_info = pathinfo($_FILES['banner2']['name']);
                $extension = $path_info['extension'];
                $path2 = time() . "_small." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['banner2']['tmp_name'])){
                    if (in_array($_FILES['banner2']['type'], $types)){
                        $banner_path2 = $_SERVER['DOCUMENT_ROOT'].'/images/banners/'.$path2;
                        move_uploaded_file($_FILES['banner2']['tmp_name'], $banner_path2);
                    }
                }
            }

            $db = static::getDB();
            $res = $db->prepare("UPDATE banners
                                          SET
                                            text1 = :text1,
                                            text2 = :text2,
                                            alt = :alt,
                                            rate = :rate,                                         
                                            path = :path,                                         
                                            path2 = :path2,                                           
                                            url = :url                                           
                                          WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->bindValue(':text1', $text1);
            $res->bindValue(':text2', $text2);
            $res->bindValue(':alt', $alt);
            $res->bindValue(':rate', $rate, PDO::PARAM_INT);
            $res->bindValue(':path', $path);
            $res->bindValue(':path2', $path2);
            $res->bindValue(':url', $url);
            $res->execute();

            $notice = 'Баннер успешно отредактирован';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о баннере';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Обновить баннер каталога
     * @param $banner_info
     * @return array
     */
    public function updateBannerCatalog($banner_info){
        $notice = '';
        $error = '';
        try{
            $id = $banner_info['id'];
            $alt = $banner_info['alt'];
            $rate = $banner_info['rate'];
            $url = $banner_info['url'];

            $path = $banner_info['path'];
            if (isset($_POST['del_file'])) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/banners_catalog/".$path);
                $path = "";
            }
            if (isset($_FILES['banner']) && $_FILES['banner']['name']) {
                if ($path) {
                    unlink($_SERVER['DOCUMENT_ROOT']."/images/banners_catalog/".$path);
                }

                $path_info = pathinfo($_FILES['banner']['name']);
                $extension = $path_info['extension'];
                $path = time() . "." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['banner']['tmp_name'])){
                    if (in_array($_FILES['banner']['type'], $types)){
                        $banner_path = $_SERVER['DOCUMENT_ROOT'].'/images/banners_catalog/'.$path;
                        move_uploaded_file($_FILES['banner']['tmp_name'], $banner_path);
                    }
                }
            }

            $path2 = $banner_info['path2'];
            if (isset($_POST['del_file2'])) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/banners_catalog/".$path2);
                $path2 = "";
            }
            if (isset($_FILES['banner2']) && $_FILES['banner2']['name']) {
                if ($path2) {
                    unlink($_SERVER['DOCUMENT_ROOT']."/images/banners_catalog/".$path2);
                }

                $path_info = pathinfo($_FILES['banner2']['name']);
                $extension = $path_info['extension'];
                $path2 = time() . "_small." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['banner2']['tmp_name'])){
                    if (in_array($_FILES['banner2']['type'], $types)){
                        $banner_path2 = $_SERVER['DOCUMENT_ROOT'].'/images/banners_catalog/'.$path2;
                        move_uploaded_file($_FILES['banner2']['tmp_name'], $banner_path2);
                    }
                }
            }

            $db = static::getDB();
            $res = $db->prepare("UPDATE banners_catalog
                                          SET
                                            alt = :alt,
                                            rate = :rate,                                         
                                            path = :path,                                         
                                            path2 = :path2,                                           
                                            url = :url                                           
                                          WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->bindValue(':alt', $alt);
            $res->bindValue(':rate', $rate, PDO::PARAM_INT);
            $res->bindValue(':path', $path);
            $res->bindValue(':path2', $path2);
            $res->bindValue(':url', $url);
            $res->execute();

            $res = $db->prepare("DELETE FROM banner_pages WHERE banner_id = :banner_id");
            $res->bindParam(':banner_id', $id, PDO::PARAM_INT);
            $res->execute();
            if ($_POST['pages']) {
                $res = $db->prepare("INSERT INTO banner_pages
                                                  SET
                                                    page_id = :page_id,
                                                    page_table = :page_table,                                         
                                                    banner_id = :banner_id");
                $res->bindParam(':page_id', $page_id, PDO::PARAM_INT);
                $res->bindParam(':page_table', $page_table);
                $res->bindParam(':banner_id', $id, PDO::PARAM_INT);

                foreach ($_POST['pages'] as $page) {
                    $page_info = explode('_', $page);
                    $page_table = $page_info[0];
                    $page_id = $page_info[1];
                    $res->execute();
                }
            }

            $notice = 'Баннер успешно отредактирован';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о баннере';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Добавить баннер
     * @param $banner_info
     * @return array
     */
    public function addBanner($banner_info){
        $notice = '';
        $error = '';
        $id = 0;
        try{
            $text1 = $banner_info['text1'];
            $text2 = $banner_info['text2'];
            $alt = $banner_info['alt'];
            $rate = $banner_info['rate'];
            $url = $banner_info['url'];

            if (isset($_FILES['banner']) && $_FILES['banner']['name']) {
                $path_info = pathinfo($_FILES['banner']['name']);
                $extension = $path_info['extension'];
                $path = time() . "." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['banner']['tmp_name'])){
                    if (in_array($_FILES['banner']['type'], $types)){
                        $banner_path = $_SERVER['DOCUMENT_ROOT'].'/images/banners/'.$path;
                        move_uploaded_file($_FILES['banner']['tmp_name'], $banner_path);
                    }
                }
            }

            if (isset($_FILES['banner2']) && $_FILES['banner2']['name']) {
                $path_info = pathinfo($_FILES['banner2']['name']);
                $extension = $path_info['extension'];
                $path2 = time() . "_small." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['banner2']['tmp_name'])){
                    if (in_array($_FILES['banner2']['type'], $types)){
                        $banner_path2 = $_SERVER['DOCUMENT_ROOT'].'/images/banners/'.$path2;
                        move_uploaded_file($_FILES['banner2']['tmp_name'], $banner_path2);
                    }
                }
            }

            $db = static::getDB();
            $res = $db->prepare("INSERT INTO banners
                                          SET
                                            text1 = :text1,
                                            text2 = :text2,
                                            alt = :alt,
                                            rate = :rate,                                         
                                            path = :path,
                                            path2 = :path2,
                                            url = :url");
            $res->bindValue(':text1', $text1);
            $res->bindValue(':text2', $text2);
            $res->bindValue(':alt', $alt);
            $res->bindValue(':rate', $rate, PDO::PARAM_INT);
            $res->bindValue(':path', $path);
            $res->bindValue(':path2', $path2);
            $res->bindValue(':url', $url);
            $res->execute();

            $id = $db->lastInsertId();
            $notice = 'Баннер успешно добавлен';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления баннера';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['id'] = $id;
        return $result;
    }

    /**
     * Добавить баннер каталога
     * @param $banner_info
     * @return array
     */
    public function addBannerCatalog($banner_info){
        $notice = '';
        $error = '';
        $id = 0;
        try{
            $alt = $banner_info['alt'];
            $rate = $banner_info['rate'];
            $url = $banner_info['url'];

            if (isset($_FILES['banner']) && $_FILES['banner']['name']) {
                $path_info = pathinfo($_FILES['banner']['name']);
                $extension = $path_info['extension'];
                $path = time() . "." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['banner']['tmp_name'])){
                    if (in_array($_FILES['banner']['type'], $types)){
                        $banner_path = $_SERVER['DOCUMENT_ROOT'].'/images/banners_catalog/'.$path;
                        move_uploaded_file($_FILES['banner']['tmp_name'], $banner_path);
                    }
                }
            }

            if (isset($_FILES['banner2']) && $_FILES['banner2']['name']) {
                $path_info = pathinfo($_FILES['banner2']['name']);
                $extension = $path_info['extension'];
                $path2 = time() . "_small." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['banner2']['tmp_name'])){
                    if (in_array($_FILES['banner2']['type'], $types)){
                        $banner_path2 = $_SERVER['DOCUMENT_ROOT'].'/images/banners_catalog/'.$path2;
                        move_uploaded_file($_FILES['banner2']['tmp_name'], $banner_path2);
                    }
                }
            }

            $db = static::getDB();
            $res = $db->prepare("INSERT INTO banners_catalog
                                          SET
                                            alt = :alt,
                                            rate = :rate,                                         
                                            path = :path,
                                            path2 = :path2,
                                            url = :url");
            $res->bindValue(':alt', $alt);
            $res->bindValue(':rate', $rate, PDO::PARAM_INT);
            $res->bindValue(':path', $path);
            $res->bindValue(':path2', $path2);
            $res->bindValue(':url', $url);
            $res->execute();

            $id = $db->lastInsertId();

            $res = $db->prepare("DELETE FROM banner_pages WHERE banner_id = :banner_id");
            $res->bindParam(':banner_id', $id, PDO::PARAM_INT);
            $res->execute();
            if ($_POST['pages']) {
                $res = $db->prepare("INSERT INTO banner_pages
                                                  SET
                                                    page_id = :page_id,
                                                    page_table = :page_table,                                         
                                                    banner_id = :banner_id");
                $res->bindParam(':page_id', $page_id, PDO::PARAM_INT);
                $res->bindParam(':page_table', $page_table);
                $res->bindParam(':banner_id', $id, PDO::PARAM_INT);

                foreach ($_POST['pages'] as $page) {
                    $page_info = explode('_', $page);
                    $page_table = $page_info[0];
                    $page_id = $page_info[1];
                    $res->execute();
                }
            }

            $notice = 'Баннер успешно добавлен';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления баннера';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['id'] = $id;
        return $result;
    }

    /**
     * Удалить баннер
     * @param $id
     * @return array
     */
    public function deleteBanner($id){
        $notice = '';
        $error = '';
        try{
            $banner = $this->getBanner($id);
            if ($banner['banner']['path']) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/banners/".$banner['banner']['path']);
            }
            if ($banner['banner']['path2']) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/banners/".$banner['banner']['path2']);
            }

            $db = static::getDB();
            $res = $db->prepare("DELETE FROM banners WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();

            $notice = 'Баннер успешно удален';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка удаления баннера';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Удалить баннер каталога
     * @param $id
     * @return array
     */
    public function deleteBannerCatalog($id){
        $notice = '';
        $error = '';
        try{
            $banner = $this->getBannerCatalog($id);
            if ($banner['banner']['path']) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/banners_catalog/".$banner['banner']['path']);
            }
            if ($banner['banner']['path2']) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/banners_catalog/".$banner['banner']['path2']);
            }

            $db = static::getDB();
            $res = $db->prepare("DELETE FROM banners_catalog WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();

            $res = $db->prepare("DELETE FROM banner_pages WHERE banner_id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();

            $notice = 'Баннер успешно удален';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка удаления баннера';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Добавить бренд
     * @param $info
     * @return array
     */
    public function addBrand($info){
        $notice = '';
        $error = '';
        $id = 0;
        try{
            $title = $info['title'];
            $rate = $info['rate'];
            $char_value_id = $info['char_value_id'];
            $url = ($info['url']) ?: CommonFunctions::translit(mb_strtolower($title, 'utf8'));

            if (isset($_FILES['logo']) && $_FILES['logo']['name']) {
                $path_info = pathinfo($_FILES['logo']['name']);
                $extension = $path_info['extension'];
                $path = time() . "." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['logo']['tmp_name'])){
                    if (in_array($_FILES['logo']['type'], $types)){
                        $logo_path = $_SERVER['DOCUMENT_ROOT'].'/images/brands/'.$path;
                        move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path);
                    }
                }
            }

            $db = static::getDB();
            $res = $db->prepare("INSERT INTO brands
                                          SET
                                            title = :title,
                                            rate = :rate,                                         
                                            path = :path,
                                            char_value_id = :char_value_id,
                                            url = :url");
            $res->bindValue(':title', $title);
            $res->bindValue(':rate', $rate, PDO::PARAM_INT);
            $res->bindValue(':path', $path);
            $res->bindValue(':char_value_id', $char_value_id, PDO::PARAM_INT);
            $res->bindValue(':url', $url);
            $res->execute();

            $id = $db->lastInsertId();
            $notice = 'Бренд успешно добавлен';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления бренда';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['id'] = $id;
        return $result;
    }

    /**
     * Получить бренд
     * @return bool|mixed
     */
    public function getBrand($id){
        $error = '';
        $brand = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM brands WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $brand = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['brand'] = $brand;
        return $result;
    }

    /**
     * Обновить бренд
     * @param $info
     * @return array
     */
    public function updateBrand($info){
        $notice = '';
        $error = '';
        try{
            $id = $info['id'];
            $title = $info['title'];
            $char_value_id = $info['char_value_id'];
            $rate = $info['rate'];
            $url = ($info['url']) ?: CommonFunctions::translit(mb_strtolower($title, 'utf8'));

            $path = $info['path'];
            if (isset($_POST['del_file'])) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/brands/".$path);
                $path = "";
            }
            if (isset($_FILES['logo']) && $_FILES['logo']['name']) {
                if ($path) {
                    unlink($_SERVER['DOCUMENT_ROOT']."/images/brands/".$path);
                }

                $path_info = pathinfo($_FILES['logo']['name']);
                $extension = $path_info['extension'];
                $path = time() . "." . $extension;
                $types = array('image/png', 'image/jpeg', 'image/svg+xml');
                if(is_uploaded_file($_FILES['logo']['tmp_name'])){
                    if (in_array($_FILES['logo']['type'], $types)){
                        $logo_path = $_SERVER['DOCUMENT_ROOT'].'/images/brands/'.$path;
                        move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path);
                    }
                }
            }

            $db = static::getDB();
            $res = $db->prepare("UPDATE brands
                                          SET
                                            title = :title,
                                            char_value_id = :char_value_id,
                                            rate = :rate,                                         
                                            path = :path,                                        
                                            url = :url                                        
                                          WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->bindValue(':title', $title);
            $res->bindValue(':char_value_id', $char_value_id, PDO::PARAM_INT);
            $res->bindValue(':rate', $rate, PDO::PARAM_INT);
            $res->bindValue(':path', $path);
            $res->bindValue(':url', $url);
            $res->execute();

            $notice = 'Бренд успешно отредактирован';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о бренде';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Удалить бренд
     * @param $id
     * @return array
     */
    public function deleteBrand($id){
        $notice = '';
        $error = '';
        try{
            $banner = $this->getBrand($id);
            if ($banner['brand']['path']) {
                unlink($_SERVER['DOCUMENT_ROOT']."/images/brands/".$banner['brand']['path']);
            }

            $db = static::getDB();
            $res = $db->prepare("DELETE FROM brands WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();

            $notice = 'Бренд успешно удален';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка удаления бренда';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Обновить данные товара
     * @param $product_id
     * @param $field
     * @param $value
     * @return array
     */
    public function editProductField($product_id, $field, $value){
        $error = '';
        $product_info = $this->getProductFields($product_id);

        try{
            $db = static::getDB();
            $history = '';
            $field_type = PDO::PARAM_INT;
            if ($field == 'qty') {
                $res = $db->prepare("UPDATE products SET qty=:field WHERE id=:id");
                $history = "Отредактировано количество. Было {$product_info['product']['qty']}, стало $value.";
            }
            else if ($field == 'price') {
                $res = $db->prepare("UPDATE products SET price=:field WHERE id=:id");
                $history = "Отредактирована цена. Была {$product_info['product']['price']}, стала $value.";
            }
            else if ($field == 'depot_id') {
                $field_type = PDO::PARAM_STR;
                $res = $db->prepare("UPDATE products SET depot_id=:field WHERE id=:id");
                $history = "Отредактирован складской код. Был \"{$product_info['product']['depot_id']}\", стал \"$value\".";
            }
            else if ($field == 'depot_title') {
                $field_type = PDO::PARAM_STR;
                $res = $db->prepare("UPDATE products SET depot_title=:field WHERE id=:id");
                $history = "Отредактировано складское название. Было \"{$product_info['product']['depot_title']}\", стало \"$value\".";
            }
            else if ($field == 'ct') {
                $res = $db->prepare("UPDATE products SET ct=:field WHERE id=:id");
                $history = "Отредактированы ед. измерения. ";
                $history .= "Были \"{$this->ref_counters[$product_info['product']['ct']]['name']}\", ";
                $history .= "стали \"{$this->ref_counters[$value]['name']}\".";
            }
            else if ($field == 'count_part') {
                $res = $db->prepare("UPDATE products SET count_part=:field WHERE id=:id");
                $history = "Отредактировано количество делимости. Было {$product_info['product']['count_part']}, стало $value.";
            }
            else if ($field == 'count_type_part') {
                $res = $db->prepare("UPDATE products SET count_type_part=:field WHERE id=:id");
                $history = "Отредактированы ед. измерения делимости. ";
                $history .= "Были \"{$this->ref_counters[$product_info['product']['count_type_part']]['name']}\", ";
                $history .= "стали \"{$this->ref_counters[$value]['name']}\".";
            }
            else if ($field == 'apply_promo') {
                $field_type = PDO::PARAM_STR;
                $value = implode(',', $value);
                $res = $db->prepare("UPDATE products SET apply_promo=:field WHERE id=:id");
                $history = "Изменение промокодов: было {$product_info['product']['apply_promo']}, стало $value.";
            }
            else {
                $error = 'Ошибка передачи поля';
            }

            if (!$error){
                $res->bindParam(":id", $product_id, PDO::PARAM_INT);
                $res->bindParam(":field", $value, $field_type);
                $res->execute();

                $data = array();
                $data['log_code'] = 6;
                $data['user_id'] = $_SESSION['admin']['id'];
                $data['mod_id'] = $product_id;
                $data['history'] = $history;
                $add_log = $this->addLog($data);
                if ($add_log['error']){
                    $error = $add_log['error'];
                }
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о товаре';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Обновить данные пользователя
     * @param $user_id
     * @param $field
     * @param $value
     * @return array
     */
    public function editSiteUserField($user_id, $field, $value){
        $error = '';
        $user_info = $this->getSiteUser($user_id);
        $value = trim($value);
        try{
            $db = static::getDB();
            $history = '';
            $field_type = PDO::PARAM_INT;
            if ($field == 'depot_id') {
                $field_type = PDO::PARAM_STR;
                $res = $db->prepare("UPDATE site_users SET depot_id=:field WHERE id=:id");
                $history = "Отредактирован складской код. Был \"{$user_info['user']['depot_id']}\", стал \"$value\".";
            }
            else if ($field == 'depot_title') {
                $field_type = PDO::PARAM_STR;
                $res = $db->prepare("UPDATE site_users SET depot_title=:field WHERE id=:id");
                $history = "Отредактировано складское имя. Было \"{$user_info['user']['depot_title']}\", стало \"$value\".";
            }
            else {
                $error = 'Ошибка передачи поля';
            }

            if (!$error){
                $res->bindParam(":id", $user_id, PDO::PARAM_INT);
                $res->bindParam(":field", $value, $field_type);
                $res->execute();

                $data = array();
                $data['log_code'] = 7;
                $data['user_id'] = $_SESSION['admin']['id'];
                $data['mod_id'] = $user_id;
                $data['history'] = $history;
                $add_log = $this->addLog($data);
                if ($add_log['error']){
                    $error = $add_log['error'];
                }
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации о пользователе';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Получить пользователей сайта
     * @return bool|mixed
     */
    public function getSiteUsers(){
        $error = '';
        $site_users = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT id, time, name, login, subscribe, phone, confirm, email, depot_id 
                                        FROM site_users 
                                        ORDER BY id ASC");
            $res->execute();
            $site_users = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['site_users'] = $site_users;
        return $result;
    }

    /**
     * Экспорт
     */
    public function exportProducts(){
        require_once(dirname($_SERVER['DOCUMENT_ROOT'])."/vendor/PHPExcel/Classes/PHPExcel.php");

        require_once(dirname($_SERVER['DOCUMENT_ROOT'])."/vendor/PHPExcel/Classes/PHPExcel/Writer/Excel5.php");

        $xls = new \PHPExcel();

        $xls->setActiveSheetIndex(0);

        $sheet = $xls->getActiveSheet();

        $sheet->setTitle('Выгрузка');

        $error = "";

        //Первая строка
        $row = 1;
        $col = 0;
        $sheet->setCellValueByColumnAndRow($col++, $row, "id"); //* - обязательное
        $sheet->setCellValueByColumnAndRow($col++, $row, "Статус товара");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Доставка");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Стоимость доставки");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Срок доставки");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Самовывоз");
        //$sheet->setCellValueByColumnAndRow($col++, $row, "Стоимость самовывоза");
        //$sheet->setCellValueByColumnAndRow($col++, $row, "Срок самовывоза");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Купить в магазине без заказа");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Ссылка на товар на сайте магазина"); //* - обязательное
        $sheet->setCellValueByColumnAndRow($col++, $row, "Производитель");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Название"); //* - обязательное
        $sheet->setCellValueByColumnAndRow($col++, $row, "Категория"); //* - обязательное
        $sheet->setCellValueByColumnAndRow($col++, $row, "Цена"); //* - обязательное
        //$sheet->setCellValueByColumnAndRow($col++, $row, "Цена без скидки");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Валюта"); //* - обязательное
        $sheet->setCellValueByColumnAndRow($col++, $row, "Ссылка на картинку"); //* - обязательное
        $sheet->setCellValueByColumnAndRow($col++, $row, "Описание");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Характеристики товара");
        /*$sheet->setCellValueByColumnAndRow($col++, $row, "Условия продажи");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Гарантия производителя");*/
        $sheet->setCellValueByColumnAndRow($col++, $row, "Страна происхождения");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Штрихкод");
        /*$sheet->setCellValueByColumnAndRow($col++, $row, "bid");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Уцененный товар");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Причина уценки");
        $sheet->setCellValueByColumnAndRow($col++, $row, "Кредитная программа");*/

        //Товары
        $products = $this->getFullProducts();
        $products = $products['products'];

        $row = 2;
        foreach($products as $product){
            if ($product['archived'] == '1') {
                continue;
            }

            $chars_str = "";
            $manufacturer = "";
            $country = "";
            $chars = $this->getProductChars($product['id']);
            if ($chars['error']) {
                $error .= $chars['error']."<br>";
            }
            else {
                $chars = $chars['chars'];
                $chars_array = array();
                foreach($chars as $char) {
                    if ($char['char_value_title'] == 'iD') {
                        $manufacturer = "iD";
                        $country = "Бельгия";
                    }
                    else if ($char['char_value_title'] == 'seni') {
                        $manufacturer = "seni";
                        $country = "Польша";
                    }
                    else if ($char['char_value_title'] == 'TENA') {
                        $manufacturer = "TENA";
                        $country = "Швеция";
                    }

                    $chars_array[] = $char['char_title'] . "|" . $char['char_value_title'];
                    $chars_str = implode(';', $chars_array);
                }
            }

            $col = 0;
            $sheet->setCellValueByColumnAndRow($col++, $row, $product['id']); //ID
            $sheet->setCellValueByColumnAndRow($col++, $row, ($product['free_qty'] > 0) ? "В наличии" : "На заказ"); //Статус товара
            $sheet->setCellValueByColumnAndRow($col++, $row, "Есть"); //Доставка
            $sheet->setCellValueByColumnAndRow($col++, $row, "200"); //Стоимость доставки
            $sheet->setCellValueByColumnAndRow($col++, $row, "0-2"); //Срок доставки
            $sheet->setCellValueByColumnAndRow($col++, $row, "Нет"); //Самовывоз
            //$sheet->setCellValueByColumnAndRow($col++, $row, ""); //Стоимость самовывоза
            //$sheet->setCellValueByColumnAndRow($col++, $row, ""); //Срок самовывоза
            $sheet->setCellValueByColumnAndRow($col++, $row, "Нельзя"); //Купить в магазине без заказа
            $sheet->setCellValueByColumnAndRow($col++, $row, "https://".$_SERVER['SERVER_NAME']."/".$product['full_url']); //Ссылка на товар
            $sheet->setCellValueByColumnAndRow($col++, $row, $manufacturer); //Производитель
            $sheet->setCellValueByColumnAndRow($col++, $row, $product['title']); //Название

            $parent_title = "";
            $parent_info = $this->getPage($product['parent_id']);
            if ($parent_info['error']) {
                $error .= $parent_info['error']."<br>";
            }
            else {
                $parent_info = $parent_info['page'];
                $parent_title = $parent_info['title'];
            }
            $sheet->setCellValueByColumnAndRow($col++, $row, $parent_title); //Категория товара

            $sheet->setCellValueByColumnAndRow($col++, $row, $product['price']); //Цена
            //$sheet->setCellValueByColumnAndRow($col++, $row, ""); //Цена без скидки
            $sheet->setCellValueByColumnAndRow($col++, $row, "RUR"); //Валюта

            $main_photo = "";
            if (count($product['main_photo'])) {
                $main_photo = "https://".$_SERVER['SERVER_NAME']."/images/gallery/".$product['id']."/".$product['main_photo']['path'];
            }
            $sheet->setCellValueByColumnAndRow($col++, $row, $main_photo); //Ссылка на картинку товара

            $sheet->setCellValueByColumnAndRow($col++, $row, $product['description']); //Описание

            $sheet->setCellValueByColumnAndRow($col++, $row, $chars_str);  //Характеристики товара

            /*$sheet->setCellValueByColumnAndRow($col++, $row, ""); //Условия продажи
            $sheet->setCellValueByColumnAndRow($col++, $row, ""); //Гарантия производителя*/

            $sheet->setCellValueByColumnAndRow($col++, $row, $country); //Страна происхождения

            $sheet->setCellValueByColumnAndRow($col++, $row, $product['barcode']); //Штрихкод
            /*$sheet->setCellValueByColumnAndRow($col++, $row, ""); //bid
            $sheet->setCellValueByColumnAndRow($col++, $row, ""); //Уцененный товар
            $sheet->setCellValueByColumnAndRow($col++, $row, ""); //Причина уценки
            $sheet->setCellValueByColumnAndRow($col++, $row, ""); //Кредитная программа*/

            $row++;
        }

        foreach(range('A','AB') as $columnID){
            $xls->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
        }

        if ($error) {
            echo $error."<br>";
        }
        else {
            $file_name = "export_".date("d.m.Y_H:i").".xls";
            header ( "Expires: Mon, 1 Apr 1974 05:00:00 GMT" );
            header ( "Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT" );
            header ( "Cache-Control: no-cache, must-revalidate" );
            header ( "Pragma: no-cache" );
            header ( "Content-type: application/vnd.ms-excel" );
            header ( "Content-Disposition: attachment; filename=$file_name");

            $objWriter = new \PHPExcel_Writer_Excel5($xls);
            $objWriter->save('php://output');
        }
    }

    /**
     * Получить все записи блока сео
     * @return bool|mixed     *
     */
    public function getSeoItems(){
        $error = '';
        $items = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM seo ORDER BY id DESC");
            $res->execute();
            while ($item = $res->fetch(PDO::FETCH_ASSOC)){
                if ($item['table_name'] == 'pages') {
                    $pages_info = $this->getPage($item['item_id']);
                    $pages_full_info = $this->getPageInfo($pages_info['page']['url']);

                    if ($pages_full_info['page']['full_title']) {
                        $item['page_full_title'] = $pages_full_info['page']['full_title'];
                        $item['page_full_url'] = $pages_full_info['page']['full_url'];
                    }
                    else {
                        $item['page_full_title'] = $pages_info['page']['title'];
                        $item['page_full_url'] = $pages_info['page']['url'];
                    }
                }
                else if ($item['table_name'] == 'products') {
                    $product_info = $this->getProduct($item['item_id']);
                    $product_info = $this->getProductInfo($product_info['product']['url']);
                    $item['page_full_title'] = $product_info['product']['full_title'];
                    $item['page_full_url'] = $product_info['product']['full_url'];
                }

                $items[] = $item;
            }
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['items'] = $items;
        return $result;
    }

    /**
     * Добавить сео
     * @param $info
     * @return array
     */
    public function addSeoItem($info):array{
        $notice = '';
        $error = '';
        try{
            $title = $info['title'];
            $description = $info['description'];
            $keywords = $info['keywords'];

            if ($info['page']) {
                $item_info = explode('_', $info['page']);
                $table_name = $item_info[0];
                $item_id = $item_info[1];
            }
            else {
                $table_name = '';
                $item_id = 0;
            }

            $db = static::getDB();
            $res = $db->prepare("INSERT INTO seo 
                                          SET
                                            item_id = :item_id,
                                            table_name = :table_name,
                                            title = :title,
                                            description = :description,
                                            keywords = :keywords
                                            ");
            $res->bindValue(':item_id', $item_id, PDO::PARAM_INT);
            $res->bindValue(':table_name', $table_name);
            $res->bindValue(':title', $title);
            $res->bindValue(':description', $description);
            $res->bindValue(':keywords', $keywords);
            $res->execute();

            $id = $db->lastInsertId();
            $notice = 'Успешно добавлено';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['item'] = $info;
        $result['id'] = $id;
        return $result;
    }

    /**
     * Получить сео
     * @param $id
     * @return array
     */
    public function getSeoItem($id):array{
        $error = '';
        $item = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM seo WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $item = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['item'] = $item;
        return $result;
    }

    /**
     * Обновить сео
     * @param $info
     * @return array
     */
    public function updateSeoItem($info):array{
        $notice = '';
        $error = '';
        try{
            $id = $info['id'];
            $title = $info['title'];
            $description = $info['description'];
            $keywords = $info['keywords'];

            if ($info['page']) {
                $item_info = explode('_', $info['page']);
                $table_name = $item_info[0];
                $item_id = $item_info[1];
            }
            else {
                $table_name = '';
                $item_id = 0;
            }

            $db = static::getDB();
            $res = $db->prepare("UPDATE seo 
                                          SET
                                            item_id = :item_id,
                                            table_name = :table_name,
                                            title = :title,
                                            description = :description,
                                            keywords = :keywords
                                          WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->bindValue(':item_id', $item_id, PDO::PARAM_INT);
            $res->bindValue(':table_name', $table_name."😝");
            $res->bindValue(':title', $title."😝");
            $res->bindValue(':description', $description."😝");
            $res->bindValue(':keywords', $keywords."😝");
            $res->execute();

            $notice = 'Успешно отредактировано';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Удалить сео
     * @param $id
     * @return array
     */
    public function deleteSeoItem($id){
        $notice = '';
        $error = '';
        try{
            $db = static::getDB();
            $id = (int)$id;
            $res = $db->prepare("DELETE FROM seo WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();

            $notice = 'Успешно удалено';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Получить статусы пользователя
     * @return string
     */
    public function getTempStatuses(){
        $statuses = "";
        try{
            $db = static::getDB();
            $res = $db->query("SELECT status_ids 
                                        FROM temp_user_orders_statuses 
                                        WHERE uid = {$_SESSION['admin']['id']}");
            $res->execute();
            $statuses = $res->fetch(PDO::FETCH_ASSOC);
            $statuses = $statuses['status_ids'];
        }
        catch (PDOException $e){
            Error::logError($e);
        }
        return $statuses;
    }

    /**
     * Обновить статусы пользователя
     * @param $uid
     * @param $statuses
     * @return mixed
     */
    public function updateTempStatuses($uid, $statuses){
        $error = "";
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT NULL 
                                          FROM temp_user_orders_statuses 
                                          WHERE uid = :uid");
            $res->execute([":uid" => $uid]);
            $check_statuses = $res->fetch(PDO::FETCH_ASSOC);
            if ($check_statuses) {
                $res = $db->prepare("UPDATE temp_user_orders_statuses 
                                              SET status_ids = :status_ids 
                                              WHERE uid = :uid");
            }
            else {
                $res = $db->prepare("INSERT INTO temp_user_orders_statuses 
                                              SET 
                                                status_ids = :status_ids, 
                                                uid = :uid");
            }
            $res->execute([":uid" => $uid, ":status_ids" => $statuses]);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = "Ошибка";
        }

        return array('error' => $error);
    }

    /**
     * Добавить ед. измерения
     * @param $ref_counter_info
     * @return array
     */
    public function addRefCounter($ref_counter_info){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("INSERT INTO ref_counters
                                          SET
                                            name = :name,
                                            names = :names");
            $res->bindValue(':name', $ref_counter_info['name']);
            $res->bindValue(':names', $ref_counter_info['names']);
            $res->execute();

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка добавления ед. измерения';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Обновить пвз
     * @param $info
     * @return array
     */
    public function updatePvzItem($info){
        $notice = '';
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("UPDATE pvz
                                          SET
                                            title = :title,
                                            coords = :coords,
                                            phone = :phone,
                                            worktime = :worktime,
                                            rate = :rate,                                         
                                            text = :text                                        
                                          WHERE id = :id");
            $res->bindValue(':id', $info['id'], PDO::PARAM_INT);
            $res->bindValue(':title', $info['title']);
            $res->bindValue(':coords', $info['coords']);
            $res->bindValue(':phone', $info['phone']);
            $res->bindValue(':worktime', $info['worktime']);
            $res->bindValue(':rate', $info['rate'], PDO::PARAM_INT);
            $res->bindValue(':text', $info['text']);
            $res->execute();

            $notice = 'Отредактировано успешно';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Добавить пвз
     * @param $info
     * @return array
     */
    public function addPvzItem($info):array{
        $notice = '';
        $error = '';
        $id = 0;
        try{
            $db = static::getDB();
            $res = $db->prepare("INSERT INTO pvz
                                          SET
                                            title = :title,
                                            coords = :coords,
                                            phone = :phone,
                                            worktime = :worktime,
                                            rate = :rate,                                         
                                            text = :text");
            $res->bindValue(':title', $info['title']);
            $res->bindValue(':coords', $info['coords']);
            $res->bindValue(':phone', $info['phone']);
            $res->bindValue(':worktime', $info['worktime']);
            $res->bindValue(':rate', $info['rate'], PDO::PARAM_INT);
            $res->bindValue(':text', $info['text']);
            $res->execute();

            $notice = 'Отредактировано успешно';
            $id = $db->lastInsertId();

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        $result['id'] = $id;
        return $result;
    }

    /**
     * Получить пункты выдачи заказов
     * @return array
     */
    public function getPvzItems():array{
        $pvz = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM pvz ORDER BY rate DESC");
            $pvz = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['pvz'] = $pvz;
        return $result;
    }

    /**
     * Запомнить сопоставление со складом
     * @param $depot_products
     * @return array
     */
    public function updateDepotProductIds($depot_products):array{
        $notice = '';
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("UPDATE products 
                                        SET 
                                            depot_id = :depot_id,
                                            depot_title = :depot_title 
                                        WHERE id = :id");
            $res->bindParam(':id', $product_id, PDO::PARAM_INT);
            $res->bindParam(':depot_id', $depot_id);
            $res->bindParam(':depot_title', $depot_title);
            foreach($depot_products as $product_id => $depot_info) {
                $depot_id = trim($depot_info['depot_id']);
                $depot_title = trim($depot_info['depot_title']);
                $res->execute();
            }
            $notice = 'Сопоставлено успешно';

        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Создать складские товары
     * @param $products_to_create
     * @return array
     */
    public function createDepotProducts($products_to_create):array{
        //note создание складских товаров
        $notice = '';
        $error = '';
        try{
            foreach($products_to_create as $product) {
                $add_result = $this->addProduct($product);
                if ($add_result['error']) {
                    $error .= $add_result['error']."<br>";
                }
                //mytodo DO убрать ограничение на создание одного товара
                break;
            }
            if (!$error) $notice = 'Успешно';
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Связать папку на складе и категорию на сайте
     * @param $page_data
     * @return array
     */
    public function saveDepotPageBounds($page_data):array{
        //note связь страниц
        $notice = '';
        $error = '';
        try{
            $db = self::getDB();
            $res = $db->prepare("DELETE FROM depot_pages WHERE depot_id = :depot_id");
            $res->bindParam(':depot_id', $depot_id);
            $res->execute();

            $res = $db->prepare("INSERT INTO depot_pages 
                                        SET 
                                            depot_id = :depot_id,
                                            page_id = :page_id");
            $res->bindParam(':depot_id', $page_data['depot_id']);
            $res->bindParam(':page_id', $page_data['page_id'], PDO::PARAM_INT);
            $res->execute();

            $data = array();
            $data['log_code'] = 8;
            $data['user_id'] = $_SESSION['admin']['id'];
            $data['mod_id'] = $page_data['page_id'];
            $data['history'] = "Добавлена связь страницы {$page_data['page_id']} с категорией на складе \"{$page_data['depot_title']}\".";
            $add_log = $this->addLog($data);
            if ($add_log['error']){
                $error = $add_log['error'];
            }

            $notice = 'Успешно';
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Связать товар на складе и товар на сайте
     * @param array $product_data
     * @return array
     */
    public function saveDepotProductBounds(array $product_data):array{
        //note связь товаров
        $notice = '';
        $error = '';

        try{
            $db = self::getDB();

            $res = $db->prepare("SELECT id 
                                        FROM products                                         
                                        WHERE
                                            depot_id = :depot_id
                                            AND id != :product_id");
            $res->bindParam(':depot_id', $product_data['depot_id']);
            $res->bindParam(':product_id', $product_data['product_id'], PDO::PARAM_INT);
            $res->execute();
            while ($product = $res->fetch(PDO::FETCH_ASSOC)){
                $edit = $this->editProductField($product['id'], 'depot_id', 0);
                if ($edit['error']) $error .= $edit['error']."<br>";
                $edit = $this->editProductField($product['id'], 'depot_title', '');
                if ($edit['error']) $error .= $edit['error']."<br>";
            }

            if ($product_data['product_id']) {
                $edit = $this->editProductField($product_data['product_id'], 'depot_id', $product_data['depot_id']);
                if ($edit['error']) $error .= $edit['error']."<br>";
                $edit = $this->editProductField($product_data['product_id'], 'depot_title', $product_data['depot_title']);
                if ($edit['error']) $error .= $edit['error']."<br>";
            }

            if (!$error) $notice = 'Успешно';
        }
        catch (PDOException $e){
            Error::logError($e);
            $error = 'Ошибка обновления информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Запомнить сопоставление со складом
     * @param $depot_users
     * @return array
     */
    public function updateDepotSiteUserIds($depot_users):array{
        $error = '';
        foreach($depot_users as $user_id => $user_info) {
            $edit = $this->editSiteUserField($user_id, 'depot_id', $user_info['depot_id']);
            if ($edit['error']) {
                $error = $edit['error'];
                break;
            }
        }
        $notice = (!$error) ? 'Сопоставлено успешно' : '';

        $result = array();
        $result['error'] = $error;
        $result['notice'] = $notice;
        return $result;
    }

    /**
     * Обновить остатки и цены
     * @return array
     */
    public function updateDepotProducts():array{
        //note обновление данных товаров со склада
        $error = '';

        $depot_products = Depot::getAssortment();
        $site_products = $this->getProducts()['products'];

        $products_to_update = array();
        foreach ($depot_products as $depot_key => $depot_product){
            foreach($site_products as $key => $site_product) {
                if ($depot_product['id'] == $site_product['depot_id']) {
                    $product = Depot::getDepotProduct($depot_product['id']);
                    if ($product['paymentItemType'] == 'GOOD') {
                        $products_to_update[$site_product['id']] = array();

                        $depot_ct = ($product['uom']['name'] == 'шт') ? 1 : 2;
                        $qty = ($depot_product['quantity'] > 0) ? $depot_product['quantity'] : 0;
                        $price = $product['salePrices'][0]['value'] / 100;
                        if ($depot_ct != $site_product['ct']) {
                            if (!$site_product['count_part']) {
                                $error .= "У товара {$site_product['title']} не указана делимость!<br>";
                                $qty = $site_product['qty'];
                                $price = $site_product['price'];
                            }
                            else {
                                $qty /= $site_product['count_part'];
                                $price *= $site_product['count_part'];
                            }
                        }

                        if ($qty != $site_product['qty']) {
                            $qty = ($qty >= 0) ? $qty : 0;
                            $products_to_update[$site_product['id']]['qty'] = $qty;
                        }
                        if ($price != $site_product['price']) {
                            $products_to_update[$site_product['id']]['price'] = $price;
                        }

                        if (!count($products_to_update[$site_product['id']])) {
                            unset($products_to_update[$site_product['id']]);
                        }
                    }
                }
            }
        }

        if (!$error) {
            foreach ($products_to_update as $product_id => $product) {
                foreach($product as $field => $value) {
                    $this->editProductField($product_id, $field, $value);
                }
            }
        }

        $result = array();
        $result['error'] = $error;
        $result['notice'] = (!$error) ? "Успешно обновлено ".count($products_to_update)." товаров" : "";
        return $result;
    }

    /**
     * Получить все страницы каталога
     * @param array $pages
     * @param int $parent_id
     * @return array
     */
    public function getCatalogPages($pages = array(), $parent_id = 19):array{
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM pages WHERE parent_id = :parent_id");
            $res->bindValue(":parent_id", $parent_id, PDO::PARAM_INT);
            $res->execute();
            while ($page = $res->fetch(PDO::FETCH_ASSOC)){
                $pages[$page['id']] = $this->getPageLinkInfo($page['id']);
                $pages[$page['id']]['id'] = $page['id'];
                $pages = $this->getCatalogPages($pages, $page['id']);
            }
        }
        catch (PDOException $e){
            Error::logError($e);
        }

        return $pages;
    }

    /**
     * Получить связи страниц
     * @return array
     */
    public function getDepotPages():array{
        $pages = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM depot_pages");
            while ($page = $res->fetch(PDO::FETCH_ASSOC)){
                $pages[$page['depot_id']] = $page['page_id'];
            }
        }
        catch (PDOException $e){
            Error::logError($e);
        }

        return $pages;
    }

    /**
     * Получить кол-во несопоставленных товаров
     * @return int
     */
    public function getUnrelatedProducts():int{
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM products WHERE depot_id IN ('0', '')");
            $res->execute();
            return count($res->fetchAll(PDO::FETCH_ASSOC));
        }
        catch (PDOException $e){
            Error::logError($e);
        }

        return 0;
    }
}