<?php
namespace App\Models;

use Core\Model as CoreModel;
use PDO;
use Core\Error;
use Core\CommonFunctions;


class Page extends CoreModel{
    /**
     * Получить баннер акции
     * @param $url
     * @return array
     */
    public function getSaleBanner($url){
        $error = '';
        $banner = array();
        try{
            $db = static::getDB();
            $res = $db->prepare("SELECT path2
                                         FROM banners
                                         WHERE url = :url 
                                        ORDER BY rate DESC");
            $url = "/$url";
            $res->bindValue(":url", $url);
            $res->execute();
            $banner = $res->fetch(PDO::FETCH_ASSOC);
        }
        catch (\PDOException $e){
            Error::logError($e);
            $error = 'Ошибка получения данных';
        }
        $result = array();
        $result['error'] = $error;
        $result['path'] = $banner['path2'];
        return $result;
    }
}