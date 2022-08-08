<?php
namespace App\Models;

use Core\Model as CoreModel;
use PDO;
use Core\Error;
use Core\CommonFunctions;


class Brand extends CoreModel{
    /**
     * Получить товары бренда
     * @param $url
     * @return array
     */
    public function getBrandProducts($url){
        $error = '';
        $products = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("  SELECT 
                                              p.id,
                                              p.parent_id,
                                              p.url,
                                              pages.title as parent_title
                                            FROM `products` p
                                            LEFT JOIN `brands` b ON b.url = :url AND b.char_value_id != 0
                                            LEFT JOIN `characteristics` c ON c.char_value_id = b.char_value_id AND c.product_id = p.id
                                            LEFT JOIN `pages` ON pages.id = p.parent_id
                                            WHERE p.archived = 0 AND c.char_value_id IS NOT NULL
                                            GROUP BY p.id
                                            ORDER BY p.parent_id DESC, pages.rate DESC");
            $res->bindValue(":url", $url);
            $res->execute();
            while($product = $res->fetch(PDO::FETCH_ASSOC)){
                $products[$product['parent_id']]['title'] = $product['parent_title'];
                $product_info = $this->getProductInfo($product['url']);
                $products[$product['parent_id']]['products'][$product['id']] = $product_info['product'];
            }
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения информации';
        }

        $result = array();
        $result['error'] = $error;
        $result['products'] = $products;
        return $result;
    }
}