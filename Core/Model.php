<?php
namespace Core;

use App\Config;
use App\Models\Depot;
use Core\Error;
use PDO;
use Core\View;

class Model
{
    protected $order_statuses = array();
    protected $regions = array();
    protected $settings = array();
    protected $ref_counters = array();
    public function __construct() {
        $this->order_statuses = $this->getRefOrderStatuses()['statuses'];
        $this->regions = $this->getRefRegions()['regions'];
        $this->settings = $this->getSettings()['settings'];
        $this->ref_counters = $this->getRefCounters()['counters'];
    }

    /**
     * Получить PDO для работы с БД
     * @return null|PDO
     */
    public static function getDB(){
        static $db = null;
        if ($db === null){
            try {
                $host = Config::get('DB_HOST');
                $dbname = Config::get('DB_NAME');
                $username = Config::get('DB_USERNAME');
                $passwd = Config::get('DB_PASSWORD');

                $dns = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ];
                $db = new PDO($dns, $username, $passwd, $options);
                return $db;
            }
            catch (\PDOException $e) {
                Error::logError($e);
                View::renderTemplate('connect_db_error.html');
                exit();
            }
        }

        return $db;
    }

    /**
     * Добавить логи
     * @param $message
     */
    public function log($message){
        $file = dirname($_SERVER['DOCUMENT_ROOT'])."/logs/models.log";
        $error = date('d.m.Y H:i:s')." $message \r\n";
        file_put_contents($file, $error, FILE_APPEND);
    }

    /**
     * Получить id дочерних страниц
     * @param $parent_id
     * @return array|\PDOStatement
     */
    public function getChildIds($parent_id){
        $db = static::getDB();
        $child_ids = array();
        $child_ids[] = $parent_id;
        $child_items = $db->prepare("SELECT id
                                              FROM pages
                                              WHERE parent_id = :parent_id");
        $child_items->bindValue(":parent_id", $parent_id, PDO::PARAM_INT);
        $child_items->execute();
        while($child_item = $child_items->fetch(PDO::FETCH_ASSOC)){
            $child_ids[] = $child_item['id'];
            $sub_child_ids = $this->getChildIds($child_item['id']);
            foreach($sub_child_ids as $key => $sub_child_id) {
                $child_ids[] = $sub_child_id;
            }
        }

        array_unique($child_ids);
        return $child_ids;
    }

    /**
     * Получить id родительский страниц
     * @param $id
     * @param $table
     * @return array
     */
    public function getParentIds($id, $table){
        $db = static::getDB();
        $parent_ids = array();
        $parent_ids[$id]['table'] = $table;

        if ($table == 'pages') {
            $query = "SELECT parent_id FROM pages WHERE id = :id";
        }
        else {
            $query = "SELECT parent_id FROM products WHERE id = :id";
        }
        $parent_item = $db->prepare($query);
        $parent_item->bindValue(":id", $id, PDO::PARAM_INT);
        $parent_item->execute();
        $parent_item = $parent_item->fetch(PDO::FETCH_ASSOC);
        if ($parent_item['parent_id']) {
            $parent_ids[$parent_item['parent_id']]['table'] = 'pages';
            $sub_parent_ids = $this->getParentIds($parent_item['parent_id'], 'pages');
            foreach($sub_parent_ids as $sub_parent_id => $sub_parent_info) {
                $parent_ids[$sub_parent_id]['table'] = 'pages';
            }
        }
        return $parent_ids;
    }

    /**
     * Получить информацию о товаре
     * @param $id int идентификатор товара
     * @return array
     */
    public function getProduct($id, $goods = true){
        $error = '';
        $product = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM products WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $product = $res->fetch(PDO::FETCH_ASSOC);

            $product['sales'] = ($product['sales']) ? explode(',', $product['sales']) : array();

            $product['photos'] = $this->getItemImages($id, 'product');
            $product['main_photo'] = $product['photos'][0];
            $product['image'] = $product['photos'][0]['path_middle'];

            $page_link_info = $this->getPageLinkInfo($product['parent_id']);
            $product['full_title'] = $page_link_info['title']." -> ".$product['title'];
            $product['full_url'] = $page_link_info['url']."/".$product['url'];

            $chars = $this->getProductChars($id);
            if ($chars['error']) {
                $error = $chars['error'];
            }
            $product['chars'] = $chars['chars'];

            $get_reserved_count = $this->getReservedCount($id);
            if ($get_reserved_count['error']) {
                $error = $get_reserved_count['error'];
            }
            $reserved_count = $get_reserved_count['reserved_count'];
            $product['free_qty'] = $product['qty'] - $reserved_count;

            if ($goods) {
                $product['goods_info'] = $this->getGoods($product['goods']);
                $product['goods_array'] = ($product['goods']) ? explode(',', $product['goods']) : array();
            }

            $product['promocodes'] = explode(",", $product['apply_promo']);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных о товаре';
        }

        $result = array();
        $result['error'] = $error;
        $result['product'] = $product;
        return $result;
    }

    /**
     * C эти товаром покупают
     * @param $goods_str
     * @param $block
     * @return array
     */
    public function getGoods($goods_str, $block = 0){
        $error = '';
        $goods = array();
        $items_count = 0;

        if ($goods_str) {
            try {
                $goods_ids = explode(',', $goods_str);
                $items_count = count($goods_ids);

                $block++;
                $block = $block * 5;
                $goods_ids = array_filter($goods_ids, function ($key) use ($block) {
                    return ($key >= ($block - 5) && $key < $block);
                }, ARRAY_FILTER_USE_KEY);
                foreach ($goods_ids as $key => $good_id) {
                    $get = $this->getProduct($good_id, false);
                    $goods[$good_id] = $get['product'];
                }
            } catch (\PDOException $e) {
                Error::logError($e);
                $error = 'Ошибка получения данных';
            }
        }

        $result = array();
        $result['error'] = $error;
        $result['items_str'] = $goods_str;
        $result['items'] = $goods;
        $result['items_count'] = $items_count;
        return $result;
    }

    /**
     * Получить информацию о товаре
     * @param $id int идентификатор товара
     * @return array
     */
    public function getProductFields($id){
        $error = '';
        $product = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM products WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $product = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных о товаре';
        }

        $result = array();
        $result['error'] = $error;
        $result['product'] = $product;
        return $result;
    }

    /**
     * Получить информацию о товаре
     * @param $url
     * @return array
     */
    public function getProductInfo($url){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT *
                                          FROM products
                                          WHERE 
                                            url = :url
                                          LIMIT 1");
            $res->bindValue(":url", $url);
            $res->execute();
            $product = $res->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $product['full_url'] = $product['url'];

                $breadcrumbs = array();
                $breadcrumbs[0]['title'] = "Главная";
                $breadcrumbs[0]['url'] = "/";

                if ($product['parent_id']) {
                    $page_link_info = $this->getPageLinkInfo($product['parent_id']);
                    $product['full_url'] = $page_link_info['url']."/".$product['url'];
                    $product['full_title'] = $page_link_info['title']." -> ".$product['title'];

                    $parent_pages_titles = explode(' -> ', $page_link_info['title']);
                    $parent_pages_urls = explode('/', $page_link_info['url']);
                    $base_link = '';
                    foreach($parent_pages_titles as $key => $parent_pages_title) {
                        $base_link .= "/".$parent_pages_urls[$key];
                        $breadcrumbs[$key+1]['title'] = $parent_pages_title;
                        $breadcrumbs[$key+1]['url'] = $base_link;
                    }
                }

                $product['breadcrumbs'] = $breadcrumbs;

                $product['photos'] = $this->getItemImages($product['id'], 'product');

                $product['image'] = $product['photos'][0]['path_middle'];

                $product['in_cart'] = (isset($_SESSION['cart'][$product['id']])) ? $_SESSION['cart'][$product['id']] : 0;

                $product['banners'] = $this->getPageBanners($product['id'], "products")['items'];

                $reserved_count_record = $this->getReservedCount($product['id']);
                if ($reserved_count_record['error']) {
                    $error = $reserved_count_record['error'];
                }
                $reserved_count = $reserved_count_record['reserved_count'];
                $product['free_qty'] = $product['qty'] - $reserved_count;
                $product['ct_name'] = CommonFunctions::declension($product['free_qty'], $this->ref_counters[$product['ct']]['names']);
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $product = array();
            $error = 'Ошибка получения информации о странице';
        }

        $result = array();
        $result['error'] = $error;
        $result['product'] = $product;
        return $result;
    }

    /**
     * Получить зарезервированное количество товара
     * @param $id
     * @return array
     */
    protected function getReservedCount($id){
        $error = '';
        $reserved_count = 0;
        try{
            $db = static::getDB();
            //1 - новый, 2 -  процессе оплаты, 3 - оплачен
            $res = $db->prepare("SELECT IFNULL(SUM(count),0) as reserved_count 
                                          FROM order_products op
                                          LEFT JOIN orders o ON o.id = op.order_id
                                          WHERE 
                                            op.product_id = :id
                                            AND o.status IN (1,2,3)");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $res = $res->fetch(PDO::FETCH_ASSOC);

            $reserved_count = $res['reserved_count'];
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных о резервах';
        }

        $result = array();
        $result['error'] = $error;
        $result['reserved_count'] = $reserved_count;
        return $result;
    }

    /**
     * Получить параметры и значения для запроса с именованными параметрами
     * @param $params
     * @return array
     */
    protected function getNamedPlaceholders($params){
        $place_holders = array();
        $place_holders_values = array();
        foreach($params as $key => $value) {
            $place_holders[] = ":param" . $key;
            $place_holders_values[] = $value;
        }
        $place_holders_str = implode(",", $place_holders);

        $result = array();
        $result['params'] = $place_holders_str;
        $result['values'] = $place_holders_values;
        return $result;
    }

    /**
     * Получить параметры и значения для запроса с неименованными параметрами
     * @param $params
     * @return array
     */
    protected function getQuestionMarkPlaceholders($params){
        $place_holders = implode(',', array_fill(0, count($params), '?'));
        $result = array();
        $result['params'] = $place_holders;
        $result['values'] = $params;
        return $result;
    }

    /**
     * Получить настройки сайта
     * @return array
     */
    public function getSettings(){
        $error = '';
        $settings = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM settings WHERE id = 1");
            $res->execute();
            $settings = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных о настройках';
        }

        $result = array();
        $result['error'] = $error;
        $result['settings'] = $settings;
        return $result;
    }

    /**FROM products
     * Получить страницы меню для шапки
     * @return array
     */
    public function getHeaderPages(){
        $error = '';
        $pages = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT 
                                            id,
                                            url, 
                                            IF(title_menu = '', title, title_menu) as title_menu
                                        FROM pages
                                        WHERE id IN (13, 14, 15, 51, 59)
                                        ORDER BY rate ASC");
            $res->execute();
            $pages = $res->fetchAll(PDO::FETCH_ASSOC);
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
     * Получить страницы каталога для главного меню
     * @return array
     */
    public function getCatalogMenuPages(){
        $error = '';
        $pages = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT 
                                          id,
                                          url, 
                                          IF(title_menu = '', title, title_menu) as title_menu
                                         FROM pages
                                         WHERE
                                          show_menu = '1'
                                          AND parent_id = '19'
                                        ORDER BY rate DESC");
            $res->execute();
            $pages = $res->fetchAll(PDO::FETCH_ASSOC);

            foreach($pages as &$page) {
                $page_link_info = $this->getPageLinkInfo($page['id']);
                $page['full_title'] = $page_link_info['title'];
                $page['full_url'] = $page_link_info['url'];
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
     * Получить дочерние страницы
     * @return array
     */
    public function getPageChilds($parent_id){
        $error = '';
        $pages = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT *
                                         FROM pages
                                         WHERE
                                          archived = '0'
                                          AND parent_id = :parent_id
                                        ORDER BY rate DESC");
            $res->bindValue(":parent_id", $parent_id, PDO::PARAM_INT);
            $res->execute();
            $pages = $res->fetchAll(PDO::FETCH_ASSOC);

            foreach($pages as &$page) {
                $page_link_info = $this->getPageLinkInfo($page['id']);
                $page['full_title'] = $page_link_info['title'];
                $page['full_url'] = $page_link_info['url'];
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
     * Получить информацию о странице
     * @param $url
     * @return array
     */
    public function getPageInfo($url){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("  SELECT *
                                          FROM pages
                                          WHERE 
                                            url = :url
                                            AND archived = 0
                                          LIMIT 1");
            $res->bindValue(":url", $url);
            $res->execute();
            $page = $res->fetch(PDO::FETCH_ASSOC);

            if ($page) {
                $page['full_url'] = $page['url'];

                $breadcrumbs = array();
                $breadcrumbs[0]['title'] = "Главная";
                $breadcrumbs[0]['url'] = "/";

                if ($page['parent_id']) {
                    $page_link_info = $this->getPageLinkInfo($page['parent_id']);
                    $page['full_url'] = $page_link_info['url']."/".$page['url'];
                    $page['full_title'] = $page_link_info['title']." -> ".$page['title'];

                    $parent_pages_titles = explode(' -> ', $page_link_info['title']);
                    $parent_pages_urls = explode('/', $page_link_info['url']);
                    $base_link = '';
                    foreach($parent_pages_titles as $key => $parent_pages_title) {
                        $base_link .= "/".$parent_pages_urls[$key];
                        $breadcrumbs[$key+1]['title'] = $parent_pages_title;
                        $breadcrumbs[$key+1]['url'] = $base_link;
                    }
                }

                $page['breadcrumbs'] = $breadcrumbs;

                $seo_data = $this->getSeoData($page['id'], "pages");
                if ($seo_data['error']) {
                    $error = $seo_data['error'];
                }
                else {
                    if (count($seo_data['seo'])) {
                        $page['seo'] = $seo_data['seo'];
                    }
                    else {
                        $seo_title = $page['title']." - интернет-магазин гигиенических товаров \"Лотос Маркет\"";
                        if ($page['parent_id'] == 44) {
                            $seo_title = "Купить товары бренда ".$page['title']." в интернет-магазине гигиенических товаров \"Лотос Маркет\"";
                        }
                        $seo_description = $seo_title;

                        $page['seo']['title'] = $seo_title;
                        $page['seo']['keywords'] = "";
                        $page['seo']['description'] = $seo_description;
                    }
                }

                $page['banners'] = $this->getPageBanners($page['id'], "pages")['items'];
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $page = array();
            $error = 'Ошибка получения информации о странице';
        }

        $result = array();
        $result['error'] = $error;
        $result['page'] = $page;
        return $result;
    }

    /**
     * Получить сео страницы
     * @param $item_id
     * @param $table_name
     * @return array
     */
    public function getSeoData($item_id, $table_name){
        $error = '';
        $seo_data = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT *
                                          FROM seo
                                          WHERE 
                                            item_id = :item_id
                                            AND table_name = :table_name
                                          LIMIT 1");
            $res->bindValue(":item_id", $item_id, PDO::PARAM_INT);
            $res->bindValue(":table_name", $table_name);
            $res->execute();
            $seo = $res->fetch(PDO::FETCH_ASSOC);

            if ($seo){
                $seo_data['id'] = $seo['id'];
                $seo_data['title'] = $seo['title'];
                $seo_data['keywords'] = $seo['keywords'];
                $seo_data['description'] = $seo['description'];
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['seo'] = $seo_data;
        return $result;
    }

    /**
     * Получить баннеры страницы
     * @param $page_id
     * @param $page_table
     * @return array
     */
    public function getPageBanners($page_id, $page_table){
        $error = '';
        $items = array();
        try{
            $parent_ids = $this->getParentIds($page_id, $page_table);

            $pages_array = array();
            foreach ($parent_ids as $page_id => $page_info) {
                $pages_array[] = "{$page_id}_{$page_info['table']}";
            }
            $params = $this->getQuestionMarkPlaceholders($pages_array);

            $db = static::getDB();
            $res = $db->prepare("SELECT *
                                          FROM banner_pages bp
                                          LEFT JOIN banners_catalog bc ON bc.id = bp.banner_id
                                          WHERE 
                                            bc.archived = '0' 
                                            AND CONCAT(bp.page_id, '_', bp.page_table) IN ({$params['params']})
                                          GROUP BY bp.banner_id");
            $res->execute($pages_array);
            $items = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['items'] = $items;
        return $result;
    }

    /**
     * Получить ссылку на страницу с учетом родителей
     * @param $id
     * @return array
     */
    public function getPageLinkInfo($id){
        $db = static::getDB();

        $url = "";
        $title = "";
        if ($id) {
            $res = $db->query("SELECT * FROM pages WHERE id = $id");
            $res->execute();
            $page = $res->fetch();
            $url = $page['url'];
            $title = $page['title'];
            if ($page['parent_id']){
                $page_info = $this->getPageLinkInfo($page['parent_id']);
                $url = $page_info['url']."/".$url;
                $title = $page_info['title']." -> ".$title;
            }
        }

        return array(
            'url' => $url,
            'title' => $title
        );
    }

    /**
     * Получить изображения продукта
     * @param $id
     * @return array
     */
    public function getItemImages($item_id, $item_type){
        $db = static::getDB();
        $res = $db->prepare("SELECT * 
                                    FROM gallery 
                                    WHERE 
                                      type = :item_type
                                      AND page_id = :item_id 
                                    ORDER BY rate DESC");
        $res->bindValue(":item_id", $item_id, PDO::PARAM_INT);
        $res->bindValue(":item_type", $item_type);
        $res->execute();
        return $res->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получить характеристики товара
     * @param $product_id
     * @return array
     */
    public function getProductChars($product_id){
        $error = '';
        $chars = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT 
                                            c.*, 
                                            rcv.value as value, 
                                            rc.title as char_title,
                                            rcv.value as char_value_title
                                          FROM characteristics c
                                          LEFT JOIN ref_chars_values rcv ON rcv.id = c.char_value_id
                                          LEFT JOIN ref_chars rc ON rc.id = rcv.char_id
                                          WHERE product_id = :product_id
                                          ORDER BY rc.rate DESC");
            $res->bindValue(':product_id', $product_id, PDO::PARAM_INT);
            $res->execute();
            while ($ref_char = $res->fetch(PDO::FETCH_ASSOC)){
                $chars[$ref_char['char_id']]['id'] = $ref_char['char_value_id'];
                $chars[$ref_char['char_id']]['value'] = $ref_char['value'];
                $chars[$ref_char['char_id']]['char_title'] = $ref_char['char_title'];
                $chars[$ref_char['char_id']]['char_value_title'] = $ref_char['char_value_title'];
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';

        }

        $result = array();
        $result['error'] = $error;
        $result['chars'] = $chars;
        return $result;
    }

    /**
     * Получить информацию о заказе
     * @param int $paymentOrderId идентификатор заказа в системе сбербанка
     * @return array
     */
    public function getOrderBySberOrderId($paymentOrderId){
        $order_info = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("  SELECT *
                                            FROM orders o
                                            WHERE o.paymentOrderId = :paymentOrderId");
            $res->bindValue(":paymentOrderId", $paymentOrderId);
            $res->execute();

            $order_info = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['order'] = $order_info;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Получить заказ вместе с товарами
     * @param int $id идентификатор заказа
     * @return array
     */
    public function getOrder($id){
        $order_info = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT 
                                              o.*, 
                                              rpt.title as payment_type_title,
                                              rr.name as region_name
                                            FROM orders o
                                            LEFT JOIN ref_payment_types rpt ON rpt.id = o.payment_type
                                            LEFT JOIN ref_regions rr ON rr.id = o.region
                                            WHERE o.id = :id");
            $res->bindValue(":id", $id, PDO::PARAM_INT);
            $res->execute();
            $order = $res->fetch(PDO::FETCH_ASSOC);
            $order['date'] = date('d.m.Y H:i', $order['date']);
            $order['address_title'] = $this->getFormatAddress($order);
            $order['pvz_title'] = ($order['pvz_id']) ? $this->getPvzItem($order['pvz_id'])['title'] : '';

            $res2 = $db->prepare("SELECT * FROM order_products WHERE order_id = :id");
            $res2->bindValue(":id", $id, PDO::PARAM_INT);
            $res2->execute();
            $products = array();
            while($product = $res2->fetch(PDO::FETCH_ASSOC)){
                $product_info = $this->getProduct($product['product_id']);
                $product['title'] = $product_info['product']['title'];
                $product['full_url'] = $product_info['product']['full_url'];
                $product['main_photo'] = $product_info['product']['main_photo'];
                $product['depot_id'] = $product_info['product']['depot_id'];
                $products[$product['id']] = $product;
            }

            $order_info['order'] = $order;
            $order_info['products'] = $products;
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['order'] = $order_info;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Получить заказ без товаров
     * @param int $id идентификатор заказа
     * @return array
     */
    public function getOrderWithoutProducts($id){
        $order = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM orders WHERE id = :id");
            $res->bindValue(":id", $id, PDO::PARAM_INT);
            $res->execute();
            $order = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['order'] = $order;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Меню каталога
     * @return array
     */
    public function getCatalogMenu($parent_id = 19, $page_url = 'katalog/'){
        $error = "";
        $parent_id = (int)$parent_id;
        $menu = array();
        try{
            $db = static::getDB();
            $parent_id = ($parent_id == 33) ? 34 : $parent_id;
            $res = $db->query("SELECT * FROM pages WHERE parent_id = $parent_id AND archived = '0' ORDER BY rate DESC");
            $res->execute();
            while ($page = $res->fetch(PDO::FETCH_ASSOC)){
                $page_link_info = $this->getPageLinkInfo($page['parent_id']);
                $menu[$page['id']]['title'] = $page['title'];
                $menu[$page['id']]['url'] = $page_link_info['url']."/".$page['url'];

                $menu[$page['id']]['categories'] = $this->getCatalogMenu($page['id'], $menu[$page['id']]['url']."/");
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['menu'] = $menu;
        return $result;
    }

    /**
     * Пользователь
     * @param $id
     * @return array
     */
    public function getSiteUser($id){
        $user = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM site_users WHERE id = :id LIMIT 1");
            $res->bindValue(":id", $id, PDO::PARAM_INT);
            $res->execute();
            $user = $res->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $error = 'Пользователь не найден';
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['user'] = $user;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Пользователь
     * @param $email
     * @return array
     */
    public function getSiteUserByMail($email){
        $user = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM site_users WHERE email = :email LIMIT 1");
            $res->bindValue(":email", $email);
            $res->execute();
            $user = $res->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $error = 'Пользователь не найден';
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['user'] = $user;
        $result['error'] = $error;
        return $result;
    }

    /**
     * Получить адреса пользователя
     * @return array
     */
    public function getSiteUserAddresses(){
        $addresses = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("  SELECT 
                                                sua.*, 
                                                rr.name as region_name
                                            FROM site_user_addresses sua
                                            LEFT JOIN ref_regions rr ON rr.id = sua.region
                                            WHERE user_id = :id");
            $res->bindValue(":id", $_SESSION['user']['id'], PDO::PARAM_INT);
            $res->execute();
            while($address = $res->fetch(PDO::FETCH_ASSOC)){
                $addresses[$address['id']] = $address;

                $address_title = $this->getFormatAddress($address);
                $addresses[$address['id']]['title'] = $address_title;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['addresses'] = $addresses;
        return $result;
    }

    /**
     * Отформатировать адрес
     * @param array $address
     * @return string
     */
    public function getFormatAddress($address) {
        $address_title = ($address['region_name'] && $address['region_name'] != $address['city']) ? $address['region_name'].", " : "";
        $address_title .= $address['city'];
        $address_title .= ", ".$address['street'];
        $address_title .= " д.".$address['house'];
        $address_title .= ($address['corpus']) ? ", корп.".$address['corpus'] : '';
        $address_title .= ($address['building']) ? ", стр.".$address['building'] : '';
        $address_title .= ", кв.".$address['flat'];
        $address_title .= ($address['entrance']) ? ", подъезд ".$address['entrance'] : '';
        $address_title .= ($address['floor']) ? ", этаж ".$address['floor'] : '';
        return $address_title;
    }

    /**
     * Получить пункта выдачи заказа
     * @param int $pvz_id
     * @return array
     */
    public function getPvzItem($pvz_id) {
        $error = '';
        $pvz_title = "";
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM pvz WHERE id = :id");
            $res->bindValue(':id', $pvz_id, PDO::PARAM_INT);
            $res->execute();
            $pvz = $res->fetch(PDO::FETCH_ASSOC);
            $pvz_title = $pvz['title'];
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['pvz'] = $pvz;
        $result['title'] = $pvz_title;
        return $result;
    }

    /**
     * Добавить адресс
     * @param $data
     * @return array
     */
    public function addAddress($data){
        $address_id = 0;
        $error = '';
        try{
            $new_formated_address = $this->getFormatAddress($data);
            $addresses_records = $this->getSiteUserAddresses();
            if ($addresses_records['error']) {
                throw new \Exception($addresses_records['error']);
            }
            else {
                foreach($addresses_records['addresses'] as $user_address_id => $user_address_info) {
                    $local_address = $this->getFormatAddress($user_address_info);
                    if ($local_address == $new_formated_address) {
                        $address_id = $user_address_id;
                        break;
                    }
                }
            }

            if (!$address_id) {
                $db = static::getDB();
                $res = $db->prepare("INSERT INTO site_user_addresses 
                                          SET
                                            user_id = :user_id,
                                            region = :region,
                                            city = :city,
                                            street = :street,
                                            house = :house,
                                            corpus = :corpus,
                                            building = :building,
                                            flat = :flat,
                                            entrance = :entrance,
                                            floor = :floor");
                $res->bindValue(":user_id", $_SESSION['user']['id']);
                $res->bindValue(":region", $data['region'], PDO::PARAM_INT);
                $res->bindValue(":city", $data['city']);
                $res->bindValue(":street", $data['street']);
                $res->bindValue(":house", $data['house']);
                $res->bindValue(":corpus", $data['corpus']);
                $res->bindValue(":building", $data['building']);
                $res->bindValue(":flat", $data['flat']);
                $res->bindValue(":entrance", $data['entrance']);
                $res->bindValue(":floor", $data['floor']);
                $res->execute();
                $address_id = $db->lastInsertId();
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['address_id'] = $address_id;
        return $result;
    }

    /**
     * Отправить письмо на почту
     * @param $mail
     * @param $title
     * @param $message
     * @return bool
     */
    public function sendMail($to, $subject, $message){
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
        $headers .= "From:  <no-reply@".$_SERVER['SERVER_NAME'].">\r\n";

        $message .= "   
            <div style='padding: 80px 0 40px 0;'>                
                <div>
                    C уважением,<br>
                    Команда Лотос Маркет<br>
                    г. Санкт-Петербург<br>
                    Тел.: +7 (812) 111-1-111<br>
                    Email: order@site.ru<br>
                    Сайт: <a href='https://site.ru'>site.ru</a><br><br>                    
                </div>
                <div>
                    <a href='https://site.ru'>
                        <img src='https://site.ru/images/logo.png' width='230' height='65'>
                    </a>                    
                </div> 
            </div>";

        return mail($to, $subject, $message, $headers);
    }

    /**
     * Отправить письмо с информацией о заказе
     * @param $to
     * @param $subject
     * @param $order_id
     * @return array
     */
    public function sendOrderMail($to, $subject, $order_id){
        $error = '';
        $order_info = $this->getOrder($order_id);
        if ($order_info['error']) {
            $error = $order_info['error'];
        }
        else {
            $order = $order_info['order']['order'];
            $order_products = $order_info['order']['products'];
            $delivery_date = ($order['delivery_date']) ? date('d.m.Y', $order['delivery_date']) : '';

            $message = "<h2>Информация о заказе №$order_id</h2>";
            $message .= "<div><strong>Дата:</strong> {$order['date']}</div>";
            $message .= "<div><strong>Имя:</strong> {$order['name']}</div>";
            $message .= "<div><strong>Телефон:</strong> {$order['phone']}</div>";
            $message .= "<div><strong>Почта:</strong> {$order['email']}</div>";

            if ($order['pvz_id']) {
                $message .= "<div><strong>Пункт выдачи заказа:</strong> {$order['pvz_title']}</div>";
                $pvz_deadline = date('d.m.Y', strtotime("+2 days", time()));
                $message .= "<div>
                                    Заказ можно будет забрать $pvz_deadline после 17:00.<br>
                                    Заказы в пунктах выдачи хранятся в течении 5 дней.
                            </div>";
            }
            else {
                $message .= "<div><strong>Адрес доставки:</strong> {$order['address_title']}</div>";
                $message .= "<div><strong>Дата доставки:</strong> $delivery_date</div>";
                $message .= "<div><strong>Время доставки:</strong> {$order['delivery_time']}</div>";
            }

            $message .= ($order['comment']) ? "<div><strong>Комментарий к заказу:</strong> {$order['comment']}</div>" : '';
            $message .= "<div><strong>Способ оплаты:</strong> {$order['payment_type_title']}</div>";
            $message .= "<div><strong>Статус заказа:</strong> {$this->order_statuses[$order['status']]['title']}</div>";

            if ($order['payment_type'] == 2) {
                $message .= "<div>
                                <strong>Ссылка на оплату:</strong>
                                <a href='{$order['paymentFormUrl']}' class='button button_link' target='_blank'>{$order['paymentFormUrl']}</a>
                                <span>(ссылка действительна до ".date('d.m.Y H:i', $order['paymentFormUrlLifeTime']).")</span>
                             </div>";
            }

            if ($order['promo']) {
                $message .= "<div><strong>Применен промокод:</strong> {$order['promo']}</div>";
            }

            $message .= "<div><strong>Стоимость заказа:</strong> {$order['cart_cost']} руб.</div>";

            if (!$order['pvz_id']) {
                $message .= "<div><strong>Стоимость доставки:</strong> {$order['delivery_cost']} руб.</div>";
            }

            $message .= "<div><strong>Итого:</strong> {$order['total_cost']} руб.</div>";

            $message .= "<table class='table' style='width: 100%; margin-top: 20px;'>
                                <thead>
                                    <tr class='table__head-row'>
                                        <th>№п/п</th>
                                        <th></th>
                                        <th>Товар</th>
                                        <th>Цена</th>
                                        <th>Количество</th>
                                        <th>Стоимость</th>
                                    </tr>
                                </thead>
                                <tbody>";
            $i = 1;
            foreach ($order_products as $product){
                if ($product['main_photo']['path_small'] != ''){
                    $image = "<img src='http://{$_SERVER['HTTP_HOST']}/images/gallery/{$product['product_id']}/{$product['main_photo']['path_small']}'
                                                 alt='{$product['main_photo']['alt']}' style='width: 100%'>";
                }
                else {
                    $image = "<img src='http://{$_SERVER['HTTP_HOST']}/images/nophoto.png' alt='{$product['title']}' style='width: 100%'>";
                }

                $message .= "<tr class='cart-item js-cart-item' data-id='{$product['id']}'>
                                    <td>$i</td>
                                    <td  class='cart__image'>
                                        <div style='padding-right: 15px; width: 80px;'>
                                            $image
                                        </div>
                                    </td>
                                    <td>
                                        <div class='cart__item-name'>
                                            <a href='http://{$_SERVER['HTTP_HOST']}/{$product['full_url']}' class='button button_link' target='_blank'>{$product['title']}</a>
                                        </div>
                                        <div class='cart__item-art'>{$product['code']}</div>
                                    </td>
                                    <td>{$product['price']} руб.</td>
                                    <td>{$product['count']} {$this->ref_counters[$product['ct']]['name']}</td>
                                    <td>{$product['cost']} руб.</td>
                                </tr>";
                $i++;
            }
            $message .= "    </tbody>
                              </table>
                              <style>
                                .table__head-row {text-align: left}
                              </style>";

            $send_mail = $this->sendMail($to, $subject, $message);
            if (!$send_mail) {
                $error = "Ошибка отправки письма на почту";
            }
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Отправить письмо с новым паролем
     * @param $to
     * @param $subject
     * @return array
     */
    public function sendResetMail($to, $subject, $password){
        $error = '';

        $message = "<div>Вы запросили восстановление пароля на нашем сайте.</div>";
        $message .= "<div>Для входа в личный кабинет используйте временный пароль: <strong>$password</strong></div>";
        $message .= "<div>Не забудьте после первого входа обязательно сменить пароль в личном кабинете.</div>";

        $send_mail = $this->sendMail($to, $subject, $message);
        if (!$send_mail) {
            $error = "Ошибка отправки письма на почту";
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Получить баннеры
     * @return bool|mixed     *
     */
    public function getBanners(){
        $error = '';
        $banners = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM banners ORDER BY rate DESC");
            $res->execute();
            while ($banner = $res->fetch(PDO::FETCH_ASSOC)){
                $banners[] = $banner;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['banners'] = $banners;
        return $result;
    }

    /**
     * Страница сайта
     * @return bool|mixed     *
     */
    public function getPage($id){
        $error = '';
        $page = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM pages WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $page = $res->fetch(PDO::FETCH_ASSOC);

            if ($page['parent_id']) {
                $page_link_info = $this->getPageLinkInfo($page['parent_id']);
                $page['full_title'] = $page_link_info['title']." -> ".$page['title'];
                $page['full_url'] = $page_link_info['url']."/".$page['url'];
            }
            else {
                $page['full_title'] = $page['title'];
                $page['full_url'] = $page['url'];
            }


            $page['photos'] = $this->getItemImages($id, 'page');
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['page'] = $page;
        return $result;
    }

    /**
     * Получить баннер каталога
     * @return bool|mixed
     */
    public function getBannerCatalog($id){
        $error = '';
        $banner = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM banners_catalog WHERE id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $banner = $res->fetch(PDO::FETCH_ASSOC);

            $res = $db->prepare("SELECT * FROM banner_pages WHERE banner_id = :id");
            $res->bindValue(':id', $id, PDO::PARAM_INT);
            $res->execute();
            $banner['pages'] = $res->fetchAll(PDO::FETCH_ASSOC);

            foreach ($banner['pages'] as $key => $page_data) {
                if ($page_data['page_table'] == 'products') {
                    $page_info = $this->getProduct($page_data['page_id'])['product'];
                }
                else {
                    $page_info = $this->getPage($page_data['page_id'])['page'];
                }
                $banner['pages'][$key]['full_title'] = $page_info['full_title'];
                $banner['pages'][$key]['full_url'] = $page_info['full_url'];
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['banner'] = $banner;
        return $result;
    }

    /**
     * Получить баннеры каталога
     * @return bool|mixed
     */
    public function getBannersCatalog(){
        $error = '';
        $banners = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM banners_catalog ORDER BY rate DESC");
            $res->execute();
            while ($banner = $res->fetch(PDO::FETCH_ASSOC)){
                $banners[$banner['id']] = $this->getBannerCatalog($banner['id'])['banner'];
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['banners'] = $banners;
        return $result;
    }

    /**
     * Получить бренды
     * @return bool|mixed     *
     */
    public function getBrands(){
        $error = '';
        $brands = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM brands ORDER BY rate DESC");
            $res->execute();
            while ($brand = $res->fetch(PDO::FETCH_ASSOC)){
                $brands[] = $brand;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['brands'] = $brands;
        return $result;
    }

    /**
     * Получить значения характеристики "Бренд"
     * @return bool|mixed
     */
    public function getBrandsCharsValues(){
        $error = '';
        $values = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT 
                                          rcv.*, 
                                          COUNT(c.id) as count_products
                                        FROM `ref_chars_values` rcv
                                        LEFT JOIN `characteristics` c ON c.char_value_id = rcv.id
                                        WHERE rcv.`char_id` = 4
                                        GROUP BY rcv.id");
            $res->execute();
            while ($value = $res->fetch(PDO::FETCH_ASSOC)){
                $values[] = $value;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['values'] = $values;
        return $result;
    }

    /**
     * Проверить промокод
     * @param $promo
     * @return array
     */
    public function checkPromo($promo):array{
        $result = array('error' => '');
        try{
            $db = static::getDB();
            $res = $db->prepare("  SELECT 
                                             count(*) as count,
                                             percent,
                                             expire,
                                             id
                                          FROM promo_codes                                     
                                          WHERE 
                                            code = :promo");
            $res->bindParam(':promo', $promo);
            $res->execute();
            $promo = $res->fetch(PDO::FETCH_ASSOC);
            if ($promo['count'] > 0) {
                if ($promo['expire'] > time()) {
                    $result['percent'] = $promo['percent'];
                    $result['id'] = $promo['id'];
                }
                else {
                    $result['error'] = "Промокод недействителен";
                }
            }
            else {
                $result['error'] = "Промокод не найден";
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $result['error'] = 'Ошибка получения данных';
        }

        return $result;
    }

    /**
     * Создать заказ
     * @param $data
     * @return array
     */
    public function createOrder($data){
        $error = '';
        $order_id = 0;
        $order_cost = 0;
        try{
            $cart_info = $this->getCartInfo($_SESSION['cart'], $data['promo']);

            //разберемся с пользователем
            $user_id = 0;
            if (isset($data['user_id'])) $user_id = $data['user_id'];
            else if (isset($_SESSION['user']['id'])) $user_id = $_SESSION['user']['id'];

            //разберемся с адресом
            if ($data['pvz_id'] > 0) {
                $data['city'] = '';
                $data['street'] = '';
                $data['house'] = '';
                $data['corpus'] = '';
                $data['building'] = '';
                $data['flat'] = '';
                $data['entrance'] = '';
                $data['floor'] = '';
                $data['entrance'] = '';
                $data['floor'] = '';
            }
            else if (isset($data['address_id'])) {
                $address_record = $this->getAddress($data['address_id']);
                if ($address_record['error']) throw new \Exception($address_record['error']);
                $address = $address_record['address'];
                $data = array_merge($data, $address);
            }
            else if ($user_id) {
                $add_address = $this->addAddress($data);
                if ($add_address['error']) throw new \Exception($add_address['error']);
            }

            if (!$error) {
                $db = static::getDB();
                //дополним обязательные поля
                $date = time();
                $order_fields = array('name', 'phone', 'email', 'comment');
                foreach($order_fields as $order_field) {
                    if (!isset($data[$order_field])) $data[$order_field] = '';
                }
                $address_fields = array('region', 'city', 'street', 'house', 'corpus', 'building', 'flat', 'entrance', 'floor');
                foreach($address_fields as $address_field) {
                    if (!isset($data[$address_field])) $data[$address_field] = '';
                }
                if (!isset($data['payment_type'])) $data['payment_type'] = 1;
                $delivery_date = ($data['delivery_date']) ? strtotime($data['delivery_date']) : strtotime("+1 day");
                if (!isset($data['delivery_time'])) $data['delivery_time'] = "12:00 - 16:00";

                //пересчитаем доставку, если выбран пункт выдачи заказа
                if ($data['pvz_id']){
                    $cart_info['total_cost'] -= $cart_info['delivery_cost'];
                    $cart_info['delivery_cost'] = 0;
                    if ($cart_info['cart_cost'] < 2000) {
                        $cart_info['delivery_cost'] = 99;
                    }
                    $cart_info['total_cost'] += $cart_info['delivery_cost'];
                }

                $res = $db->prepare("INSERT INTO orders
                                          SET 
                                            user_id = :user_id,
                                            status = :status,
                                            date = :date,
                                            name = :name,
                                            phone = :phone,
                                            email = :email,
                                            region = :region,
                                            city = :city,
                                            street = :street,
                                            house = :house,
                                            corpus = :corpus,
                                            building = :building,
                                            flat = :flat,
                                            entrance = :entrance,
                                            floor = :floor,
                                            payment_type = :payment_type,
                                            comment = :comment,
                                            cart_cost = :cart_cost,
                                            delivery_cost = :delivery_cost,
                                            total_cost = :total_cost,
                                            delivery_date = :delivery_date,
                                            delivery_time = :delivery_time,
                                            pvz_id = :pvz_id,
                                            promo = :promo");
                $res->bindValue(":user_id", $user_id, PDO::PARAM_INT);
                $res->bindValue(":status", 1, PDO::PARAM_INT);
                $res->bindValue(":date", $date, PDO::PARAM_INT);
                $res->bindValue(":name", $data['name']);
                $res->bindValue(":phone", $data['phone']);
                $res->bindValue(":email", $data['email']);
                $res->bindValue(":region", $data['region'], PDO::PARAM_INT);
                $res->bindValue(":city", $data['city']);
                $res->bindValue(":street", $data['street']);
                $res->bindValue(":house", $data['house']);
                $res->bindValue(":corpus", $data['corpus']);
                $res->bindValue(":building", $data['building']);
                $res->bindValue(":flat", $data['flat']);
                $res->bindValue(":entrance", $data['entrance']);
                $res->bindValue(":floor", $data['floor']);
                $res->bindValue(":payment_type", $data['payment_type'], PDO::PARAM_INT);
                $res->bindValue(":comment", $data['comment']);
                $res->bindValue(":cart_cost", $cart_info['cart_cost'], PDO::PARAM_INT);
                $res->bindValue(":delivery_cost", $cart_info['delivery_cost'], PDO::PARAM_INT);
                $res->bindValue(":total_cost", $cart_info['total_cost'], PDO::PARAM_INT);
                $res->bindValue(":delivery_date", $delivery_date, PDO::PARAM_INT);
                $res->bindValue(":delivery_time", $data['delivery_time']);
                $res->bindValue(":pvz_id", $data['pvz_id'], PDO::PARAM_INT);
                $res->bindValue(":promo", $cart_info['promo']);
                $res->execute();

                $order_id = $db->lastInsertId();
                $order_cost = $cart_info['total_cost'];
                if (!$order_id) {
                    $error = "Ошибка добавления заказа.";
                    $error .= " Пожалуйста, сообщите нам об этом по телефону +7 (812) 111-1-111 или напишите на эл.почту order@site.ru";
                }
                else if (count($cart_info['products'])){
                    $res = $db->prepare("INSERT INTO order_products
                                          SET 
                                            order_id = :order_id,
                                            product_id = :product_id,
                                            count = :count,
                                            ct = :ct,
                                            price = :price,
                                            cost = :cost");
                    $res->bindParam(":order_id", $order_id, PDO::PARAM_INT);
                    $res->bindParam(":product_id", $product_id, PDO::PARAM_INT);
                    $res->bindParam(":count", $count, PDO::PARAM_INT);
                    $res->bindParam(":ct", $ct, PDO::PARAM_INT);
                    $res->bindParam(":price", $price, PDO::PARAM_INT);
                    $res->bindParam(":cost", $cost, PDO::PARAM_INT);

                    foreach($cart_info['products'] as $product) {
                        $product_id = $product['id'];
                        $count = $product['cart_count'];
                        $ct = $product['ct'];
                        $price = ($product['price_sale']) ?: $product['price'];
                        $cost = $product['cost'];
                        if (!$res->execute()) {
                            $error = "Ошибка добавления товаров в заказ.";
                            $error .= " Пожалуйста, сообщите нам об этом по телефону +7 (812) 111-1-111 или напишите на эл.почту order@site.ru";
                        }
                    }
                }
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = "Ошибка создания заказа.";
            $error .= " Пожалуйста, сообщите нам об этом по телефону +7 (812) 111-1-111 или напишите на эл.почту order@site.ru";
        }
        catch (\Exception $e){
            Error::logError($e);
        }

        $result = array();
        $result['error'] = $error;
        $result['order_id'] = $order_id;
        $result['order_cost'] = $order_cost;
        return $result;
    }

    /**
     * Получить данные корзины
     * @param $cart_items
     * @param $promo
     * @return array
     */
    public function getCartInfo($cart_items, $promo = ''){
        $error = '';
        $products = array();

        $promo_percent = 0;
        $promo_id = 0;
        $promo_res = '';
        if ($promo) {
            $check = $this->checkPromo($promo);
            if ($check['error']) {
                $promo_res = $check['error'];
            }
            else {
                $promo_percent = $check['percent'];
                $promo_id = $check['id'];
                $promo_res = "Промокод применен";
            }
        }

        $discount = 0;
        $cart_cost = 0;
        $cart_discount_cost = 0;
        $discount_products = array();
        foreach($cart_items as $product_id => $product_count) {
            $product = $this->getProduct($product_id);
            if ($product['error']) {
                $error = $product['error'];
            }
            else {
                $product = $product['product'];
                $products[$product_id] = $product;
                $products[$product_id]['real_price'] = $product['price'];
                $products[$product_id]['cart_count'] = $product_count;
                $products[$product_id]['cart_price'] = $product['price_sale'] ?: $product['price'];
                $cart_cost += $products[$product_id]['cart_price'] * $product_count;

                if ($promo_percent) {
                    if (in_array($promo_id, $product['promocodes']) && !$product['price_sale']) {
                        $product_discount = round($products[$product_id]['cart_price'] * $promo_percent / 100);
                        $discount += $product_discount * $product_count;
                        $products[$product_id]['cart_price'] -= $product_discount;
                        $discount_products[$product_id] = $product_discount * $product_count;
                    }
                }
                $products[$product_id]['price'] = $products[$product_id]['cart_price'];
                if ($product['price_sale']) {
                    $products[$product_id]['price_sale'] = $products[$product_id]['cart_price'];
                }
                $products[$product_id]['cost'] = $products[$product_id]['cart_price'] * $product_count;
                $cart_discount_cost += $products[$product_id]['cost'];
            }
        }

        $delivery_cost = ($cart_cost >= $this->settings['delivery_sum_free']) ? 0 : $this->settings['delivery_cost'];
        $total_cost = $cart_discount_cost + $delivery_cost;

        $result = array();
        $result['error'] = $error;
        $result['products'] = $products;
        $result['discount'] = $discount;
        $result['cart_cost'] = $cart_discount_cost;
        $result['delivery_cost'] = $delivery_cost;
        $result['total_cost'] = $total_cost;
        $result['discount_products'] = $discount_products;
        $result['text'] = $promo_res;
        $result['promo'] = ($promo_id) ? $promo : '';
        return $result;
    }

    /**
     * Получить адреса пользователя
     * @return array
     */
    public function getAddress($id){
        $address = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM site_user_addresses WHERE id = :id");
            $res->bindValue(":id", $id, PDO::PARAM_INT);
            $res->execute();
            $address = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных адреса. Пожалуйста, сообщите нам об этом';
        }

        $result = array();
        $result['error'] = $error;
        $result['address'] = $address;
        return $result;
    }

    /**
     * Получить интервалы доставки
     * @return array
     */
    public static function getDeliveryTimeItems(){
        $delivery_time_items = array();
        $delivery_time_items[0]['time'] = '08:00 - 12:00';
        $delivery_time_items[0]['default'] = false;
        $delivery_time_items[0]['start'] = 8;
        $delivery_time_items[0]['finish'] = 12;

        $delivery_time_items[1]['time'] = '12:00 - 16:00';
        $delivery_time_items[1]['default'] = true;
        $delivery_time_items[1]['start'] = 12;
        $delivery_time_items[1]['finish'] = 16;

        $delivery_time_items[2]['time'] = '16:00 - 20:00';
        $delivery_time_items[2]['default'] = false;
        $delivery_time_items[2]['start'] = 16;
        $delivery_time_items[2]['finish'] = 20;

        $delivery_time_items[3]['time'] = '20:00 - 22:00';
        $delivery_time_items[3]['default'] = false;
        $delivery_time_items[3]['start'] = 20;
        $delivery_time_items[3]['finish'] = 22;

        return $delivery_time_items;
    }

    /**
     * Получить регионы
     * @return array
     */
    public function getRefRegions(){
        $regions = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM ref_regions");
            $res->execute();
            while ($region = $res->fetch(PDO::FETCH_ASSOC)){
                $regions[$region['id']] = $region;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['regions'] = $regions;
        return $result;
    }

    /**
     * Получить прайс-лист
     * @param $email
     * @param $phone
     * @return array
     */
    public function requestPriceList($email, $phone){
        $error = '';
        try{
            $db = static::getDB();
            $time = time();
            $res = $db->prepare("INSERT INTO price_requests
                                        SET
                                            time = :time,
                                            email = :email,
                                            phone = :phone");
            $res->bindParam(':time', $time, PDO::PARAM_INT);
            $res->bindParam(':email', $email);
            $res->bindParam(':phone', $phone);
            $res->execute();

            $mail = $this->settings['mailprice'];
            $message = "<div>Поступила заявка на получение прайс-листа:</div>";
            $message .= "<div>Эл. почта: $email</div>";
            $message .= "<div>Телефон: $phone</div>";
            $subject = "Заявка на получение прайс-листа на сайте {$_SERVER['SERVER_NAME']}";
            $send_mail = $this->sendMail($mail, $subject, $message);
            if (!$send_mail){
                $error = "Ошибка отправки сообщения";
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка отправки данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['success'] = "Запрос успешно отправлен";
        return $result;
    }

    /**
     * Оставить заявку на товар
     * @param $email
     * @param $phone
     * @return array
     */
    public function sendPreorder($data){
        $error = '';
        try{
            $db = static::getDB();
            $time = time();
            $res = $db->prepare("INSERT INTO products_requests
                                        SET
                                            time = :time,
                                            product_id = :product_id,
                                            name = :name,
                                            phone = :phone");
            $res->bindParam(':time', $time, PDO::PARAM_INT);
            $res->bindParam(':product_id', $data['product_id'], PDO::PARAM_INT);
            $res->bindParam(':name', $data['name']);
            $res->bindParam(':phone', $data['phone']);
            $res->execute();

            $product = $this->getProduct($data['product_id']);
            $product = $product['product'];

            $mail = $this->settings['mailorders'];
            $message = "<div>Поступила заявка на товар:</div>";
            $message .= "<div>Позиция: <a href='https://{$_SERVER['HTTP_HOST']}/{$product['full_url']}'>{$product['title']}</a></div>";
            $message .= "<div>Имя: {$data['name']}</div>";
            $message .= "<div>Телефон: {$data['phone']}</div>";
            $subject = "Заявка на товар на сайте {$_SERVER['SERVER_NAME']}";
            $send_mail = $this->sendMail($mail, $subject, $message);
            if (!$send_mail){
                $error = "Ошибка отправки сообщения";
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка отправки данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['success'] = "Заявка успешно отправлена";
        return $result;
    }

    /**
     * Получить пункты выдачи заказов
     * @return array
     */
    public function getPvzItems(){
        $pvz = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM pvz WHERE archived = 0 ORDER BY rate DESC");
            $pvz = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['pvz'] = $pvz;
        return $result;
    }

    /**
     * Получить промокоды
     * @return array
     */
    public function getPromocodes(){
        $promocodes = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT * FROM promo_codes WHERE expire > :expire");
            $time = time();
            $res->bindParam(':expire', $time);
            $res->execute();
            $promocodes = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['promocodes'] = $promocodes;
        return $result;
    }

    /**
     * Получить промокоды
     * @return array
     */
    public function getAllPromocodes(){
        $promocodes = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM promo_codes");
            $promocodes = $res->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['promocodes'] = $promocodes;
        return $result;
    }

    /**
     * Получить статусы заказов
     * @return array
     */
    public function getRefOrderStatuses(){
        $statuses = array();
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM ref_order_statuses");
            $res->execute();
            while ($status = $res->fetch(PDO::FETCH_ASSOC)){
                $statuses[$status['id']] = $status;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['statuses'] = $statuses;
        return $result;
    }

    /**
     * Получить ед измерения
     * @return array
     */
    public function getRefCounters(){
        $error = '';
        $counters = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM ref_counters");
            $res->execute();
            while ($ct = $res->fetch(PDO::FETCH_ASSOC)){
                $counters[$ct['id']] = $ct;
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['counters'] = $counters;
        return $result;
    }

    /**
     * Добавить логи
     * @param $data
     * @return array
     */
    public function addLog($data){
        $error = '';
        if (!$data['user_id']) $data['user_id'] = 0;
        try{
            $db = static::getDB();
            $res = $db->prepare("INSERT INTO log 
                                          SET 
                                            log_code = :log_code,
                                            user_id = :user_id,
                                            mod_id = :mod_id,
                                            time = :time,
                                            history = :history");
            $time = time();
            $res->bindValue(":log_code", $data['log_code'], PDO::PARAM_INT);
            $res->bindValue(":user_id", $data['user_id'], PDO::PARAM_INT);
            $res->bindValue(":mod_id", $data['mod_id'], PDO::PARAM_INT);
            $res->bindValue(":time", $time, PDO::PARAM_INT);
            $res->bindValue(":history", $data['history']);
            $res->execute();
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка записи логов';
        }

        $result = array();
        $result['error'] = $error;
        return $result;
    }

    /**
     * Получить параметры доставки
     * @param int $require_date dd.mm.YYYY
     * @param int $current_time unix
     * @return array
     * <pre>
     * "day" => string дата доставки (dd.mm.YYYY),
     * "times" => array доступные диапазоны
     * </pre>
     */
    public function getDeliveryParams($require_date = 0, $current_time = 0){
        $current_time = ($current_time) ?: time();
        $min_unix_time = strtotime(date( 'd.m.Y', $current_time) . " +1 day");

        if ($min_unix_time >= strtotime('01.01.2021') && $min_unix_time <= strtotime('04.01.2021')) {
            $min_unix_time = strtotime('05.01.2021');
        }

        $ref_time_items = self::getDeliveryTimeItems();
        $min_date = date('d.m.Y', $min_unix_time);
        if (strtotime($require_date) <= strtotime($min_date)) {
            $require_date = $min_date;
            $min_time_hours = date('H', $current_time);
            if ($min_time_hours >= 20) {
                unset($ref_time_items[0]);
            }
        }

        return array(
            'day' => $require_date,
            'times' => $ref_time_items
        );
    }

    /**
     * Обновить данные заказа
     * @param $order_id
     * @param $field
     * @param $value
     * @return array
     */
    public function editOrderField($order_id, $field, $value){
        //TODO вынести разрешенные к обновлению поля в массив и прописать только одно условие
        $error = '';
        $order = $this->getOrderWithoutProducts($order_id)['order'];
        try{
            $db = static::getDB();
            $history = '';
            $field_type = PDO::PARAM_INT;
            if ($field == 'name') {
                $res = $db->prepare("UPDATE orders SET name=:field WHERE id=:id");
                $field_type = PDO::PARAM_STR;
                $history = "Отредактировано имя. Было \"{$order['name']}\", стало \"$value\".";
            }
            else if ($field == 'phone') {
                $res = $db->prepare("UPDATE orders SET phone=:field WHERE id=:id");
                $field_type = PDO::PARAM_STR;
                $history = "Отредактирован телефон. Был \"{$order['phone']}\", стал \"$value\".";
            }
            else if ($field == 'email') {
                $res = $db->prepare("UPDATE orders SET email=:field WHERE id=:id");
                $field_type = PDO::PARAM_STR;
                $history = "Отредактирована почта. Была \"{$order['email']}\", стала \"$value\".";
            }
            else if ($field == 'delivery_cost') {
                $res = $db->prepare("UPDATE orders SET delivery_cost=:field WHERE id=:id");
                $history = "Отредактирована стоимость доставки. Была \"{$order['delivery_cost']}\", стала \"$value\".";
            }
            else if ($field == 'city') {
                $res = $db->prepare("UPDATE orders SET city=:field WHERE id=:id");
                $field_type = PDO::PARAM_STR;
                $history = "Отредактирован город. Был \"{$order['city']}\", стал \"$value\".";
            }
            else if ($field == 'street') {
                $res = $db->prepare("UPDATE orders SET street=:field WHERE id=:id");
                $field_type = PDO::PARAM_STR;
                $history = "Отредактирована улица. Была \"{$order['street']}\", стала \"$value\".";
            }
            else if ($field == 'house') {
                $res = $db->prepare("UPDATE orders SET house=:field WHERE id=:id");
                $history = "Отредактирован дом. Был \"{$order['house']}\", стал \"$value\".";
            }
            else if ($field == 'corpus') {
                $res = $db->prepare("UPDATE orders SET corpus=:field WHERE id=:id");
                $history = "Отредактирован корпус. Был \"{$order['corpus']}\", стал \"$value\".";
            }
            else if ($field == 'building') {
                $res = $db->prepare("UPDATE orders SET building=:field WHERE id=:id");
                $history = "Отредактировано строение. Было \"{$order['building']}\", стало \"$value\".";
            }
            else if ($field == 'flat') {
                $res = $db->prepare("UPDATE orders SET flat=:field WHERE id=:id");
                $history = "Отредактирована квартира. Была \"{$order['flat']}\", стала \"$value\".";
            }
            else if ($field == 'entrance') {
                $res = $db->prepare("UPDATE orders SET entrance=:field WHERE id=:id");
                $history = "Отредактирован подъезд. Был \"{$order['entrance']}\", стал \"$value\".";
            }
            else if ($field == 'floor') {
                $res = $db->prepare("UPDATE orders SET floor=:field WHERE id=:id");
                $history = "Отредактирован этаж. Был \"{$order['floor']}\", стал \"$value\".";
            }
            else if ($field == 'comment') {
                $field_type = PDO::PARAM_STR;
                $res = $db->prepare("UPDATE orders SET comment=:field WHERE id=:id");
                $history = "Отредактирован комментарий. Был \"{$order['comment']}\", стал \"$value\".";
            }
            else if ($field == 'delivery_date') {
                $res = $db->prepare("UPDATE orders SET delivery_date=:field WHERE id=:id");
                $value = strtotime($value);
                $history = "Отредактирована дата доставки. Была \"".date('d.m.Y', $order['delivery_date'])."\", стала \"".date('d.m.Y', $value)."\".";
            }
            else if ($field == 'delivery_time') {
                $field_type = PDO::PARAM_STR;
                $res = $db->prepare("UPDATE orders SET delivery_time=:field WHERE id=:id");
                $history = "Отредактировано время доставки. Было \"{$order['delivery_time']}\", стало \"$value\".";
            }
            else if ($field == 'region') {
                $res = $db->prepare("UPDATE orders SET region=:field WHERE id=:id");
                $history = "Отредактирован регион. Был \"{$this->regions[$order['region']]['name']}\", стал \"{$this->regions[$value]['name']}\".";
            }
            else if ($field == 'pvz') {
                $pvz_before = $this->getPvzItem($order['pvz_id'])['title'];
                $pvz_after = $this->getPvzItem($value)['title'];
                $field_type = PDO::PARAM_STR;
                $res = $db->prepare("UPDATE orders SET pvz_id=:field WHERE id=:id");
                $history = "Отредактирован пункт выдачи заказа. Был \"$pvz_before\", стал \"$pvz_after\".";
            }
            else if ($field == 'depot_id') {
                $field_type = PDO::PARAM_STR;
                $res = $db->prepare("UPDATE orders SET depot_id=:field WHERE id=:id");
                $history = "Отредактирован складской код. Был \"{$order['depot_id']}\", стал \"$value\".";
            }
            else if ($field == 'depot_number') {
                $field_type = PDO::PARAM_STR;
                $res = $db->prepare("UPDATE orders SET depot_number=:field WHERE id=:id");
                $history = "Отредактирован складской номер заказа. Был \"{$order['depot_number']}\", стал \"$value\".";
            }
            else {
                $error = 'Ошибка передачи поля';
            }

            if (!$error && $history){
                $res->bindParam(":id", $order_id, PDO::PARAM_INT);
                $res->bindParam(":field", $value, $field_type);
                $res->execute();

                $data = array();
                $data['log_code'] = 5;
                $data['user_id'] = $_SESSION['admin']['id'] ?? 1;
                $data['mod_id'] = $order_id;
                $data['history'] = $history;
                $add_log = $this->addLog($data);
                if ($add_log['error']){
                    $error = $add_log['error'];
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