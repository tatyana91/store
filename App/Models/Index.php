<?php
namespace App\Models;

use Core\Model as CoreModel;
use PDO;
use Core\Error;
use Core\CommonFunctions;


class Index extends CoreModel{
    /**
     * Получить популярные товары
     * @param int $parent_id - идентификатор родителя, популярные товары которого нам интересны
     * @param int $block - номер блока (по кнопке загрузить еще)
     * @return array
     */
    public function getPopularProducts($parent_id = 0, $block = 0){
        $error = '';
        $products = array();
        try{
            $db = static::getDB();
            if ($parent_id) {
                $parent_ids = $this->getChildIds($parent_id);
                $params = $this->getQuestionMarkPlaceholders($parent_ids);

                $res = $db->prepare("SELECT 
                                                  id,
                                                  parent_id,
                                                  url, 
                                                  title,
                                                  price,
                                                  price_sale,
                                                  new,
                                                  popular,
                                                  sales,
                                                  qty
                                              FROM products
                                              WHERE 
                                                products.popular = '1'
                                                AND archived = 0
                                                AND parent_id IN ({$params['params']})
                                              ORDER BY rate DESC");
                $res->execute($parent_ids);
            }
            else {
                $res = $db->query("  SELECT 
                                                  id,
                                                  parent_id,
                                                  url, 
                                                  title,
                                                  price,
                                                  price_sale,
                                                  new,
                                                  popular,
                                                  sales,
                                                  qty
                                              FROM products
                                              WHERE 
                                                products.popular = '1'
                                                AND archived = 0
                                              ORDER BY rate DESC");
                $res->execute();
            }

            $products = $res->fetchAll(PDO::FETCH_ASSOC);
            $products_count = count($products);
            $products = array_slice($products, ($block*5), 5);
            foreach($products as &$product) {
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
        $result['products_count'] = $products_count;
        $result['products'] = $products;
        return $result;
    }

    /**
     * Получить главные категории
     * @return array
     */
    public function getMainCategories(){
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
     * Информация о компенсации для главной страницы
     * @return array
     */
    public function getCompensationInfo(){
        $error = '';
        $page = array();
        try{
            $db = static::getDB();
            $res = $db->query("SELECT * FROM pages WHERE id = 13");
            $res->execute();
            $page = $res->fetch(PDO::FETCH_ASSOC);

            $page['full_url'] = $page['url'];
            if ($page['parent_id']) {
                $page_link_info = $this->getPageLinkInfo($page['parent_id']);
                $page['full_url'] = $page_link_info['url']."/".$page['url'];
            }
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
     * Блоки категорий с галочкой "показывать на главной" с популярными товарами
     * @return array
     */
    public function getIndexProductsBlocks(){
        $error = '';
        try{
            $db = static::getDB();
            $res = $db->query("  SELECT *
                                          FROM pages
                                          WHERE 
                                            show_index_block = '1'
                                            AND archived = 0
                                          ORDER BY rate DESC");
            $res->execute();
            $pages = $res->fetchAll(PDO::FETCH_ASSOC);
            foreach($pages as $key => $page) {
                $page_link_info = $this->getPageLinkInfo($page['parent_id']);

                $pages[$key]['full_url'] = $page_link_info['url']."/".$page['url'];

                $popular_products_records = $this->getPopularProducts($page['id']);
                if ($popular_products_records['error']){
                    $error = $popular_products_records['error'];
                }
                $pages[$key]['products'] = $popular_products_records['products'];
                $pages[$key]['products_count'] = $popular_products_records['products_count'];
            }

        }
        catch (\PDOException $e){
            Error::logError($e);
            $pages = array();
            $error = 'Ошибка получения данных';
        }

        $result = array();
        $result['error'] = $error;
        $result['pages'] = $pages;
        return $result;
    }
}