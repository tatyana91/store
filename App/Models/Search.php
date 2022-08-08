<?php
namespace App\Models;

use Core\Model as CoreModel;
use PDO;
use Core\Error;
use Core\CommonFunctions;


class Search extends CoreModel{
    /**
     * Поиск
     * @param $query
     * @return array
     */
    public function search($query){
        $search_items = array();
        $error = '';
        if ($query != ''){
            $query = "%$query%";
            try{
                $db = static::getDB();
                $res = $db->prepare("SELECT * 
                                              FROM pages 
                                              WHERE 
                                                (title LIKE (:query) OR text LIKE (:query)) 
                                                AND archived = '0'");
                $res->bindValue(":query", $query);
                $res->execute();
                while ($item = $res->fetch(PDO::FETCH_ASSOC)){
                    $page_info = $this->getPageLinkInfo($item['id']);

                    $search_items['pages'][$item['id']]['title'] = $item['title'];
                    $search_items['pages'][$item['id']]['url'] = $page_info['url'];
                }
            }
            catch (\PDOException $e){
                Error::logError($e);
                $error = 'Ошибка получения данных';
            }

            try{
                $db = static::getDB();
                $res = $db->prepare("SELECT * 
                                              FROM products 
                                              WHERE 
                                                (title LIKE (:query) 
                                                OR text LIKE (:query) 
                                                OR description LIKE (:query)) 
                                                AND archived = '0'
                                               ORDER BY rate DESC");
                $res->bindValue(":query", $query);
                $res->execute();
                while ($item = $res->fetch(PDO::FETCH_ASSOC)){
                    $product = $item;

                    $product_images = $this->getItemImages($item['id'], 'product');
                    $product['image'] = $product_images[0]['path_middle'];

                    $page_link_info = $this->getPageLinkInfo($item['parent_id']);
                    $product['full_title'] = $page_link_info['title']." -> ".$item['title'];
                    $product['full_url'] = $page_link_info['url']."/".$item['url'];

                    $product['in_cart'] = (isset($_SESSION['cart'][$item['id']])) ? $_SESSION['cart'][$item['id']] : 0;

                    $reserved_count_record = $this->getReservedCount($product['id']);
                    if ($reserved_count_record['error']) {
                        $error = $reserved_count_record['error'];
                    }
                    $reserved_count = $reserved_count_record['reserved_count'];
                    $product['free_qty'] = $product['qty'] - $reserved_count;

                    $search_items['products'][$item['id']] = $product;
                }
            }
            catch (\PDOException $e){
                Error::logError($e);
                $error = 'Ошибка получения данных 2';
            }
        }

        $result = array();
        $result['search_items'] = $search_items;
        $result['error'] = $error;
        return $result;
    }
}