<?php
namespace App\Controllers;

use Core\Controller as CoreController;
use Core\View as CoreView;
use App\Models\Index as IndexModel;

class Index extends CoreController{
    private $model;

    public function __construct(){
        parent::__construct();
        $this->model = new IndexModel();
    }

    /**
     * Главная страница
     * @return void
     */
    public function main(){
        $page = array();
        $popular_products_records = $this->model->getPopularProducts();
        if ($popular_products_records['error']) {
            $this->error = $popular_products_records['error'];
        }
        $popular_products = $popular_products_records['products'];
        $popular_products_count = $popular_products_records['products_count'];

        $get_main_categories = $this->model->getMainCategories();
        if ($get_main_categories['error']) {
            $this->error = $get_main_categories['error'];
        }
        $main_categories = $get_main_categories['pages'];

        $compens_info = $this->model->getCompensationInfo();
        if ($compens_info['error']) {
            $this->error = $compens_info['error'];
        }
        $compens_info = $compens_info['page'];

        $product_blocks = $this->model->getIndexProductsBlocks();
        $product_blocks = $product_blocks['pages'];

        $banners = $this->model->getBanners();
        if ($banners['error']) {
            $this->error = $banners['error'];
        }
        else {
            $banners = $banners['banners'];
            foreach ($banners as $key => $banner) {
                if ($banner['archived'] == 1){
                  unset($banners[$key]);
                }
            }
        }

        $seo_data = $this->model->getSeoData("53", "pages");
        if ($seo_data['error']) {
            $this->error = $seo_data['error'];
        }
        else {
            if (count($seo_data['seo'])) {
                $page['seo'] = $seo_data['seo'];
            }
            else {
                $page['seo']['title'] = "Лотос Маркет — Интернет-магазин гигиенических товаров";
                $page['seo']['keywords'] = "";
                $page['seo']['description'] = "Лотос Маркет — Интернет-магазин гигиенических товаров";
            }
        }

        CoreView::renderTemplate('Index/index.html', [
            'settings' => $this->settings,
            'header_pages' => $this->header_pages,
            'catalog_menu_pages' => $this->catalog_menu_pages,
            'popular_products' => $popular_products,
            'popular_products_count' => $popular_products_count,
            'main_categories' => $main_categories,
            'compens_info' => $compens_info,
            'product_blocks' => $product_blocks,
            'catalog_menu' => $this->catalog_menu,
            'params' => $this->params,
            'banners' => $banners,
            'page' => $page
        ]);
    }

    public function ajax(){
        $act = $_POST['act'];
        if ($act == 'show_move_products') {
            $category = (int)$_POST['category'];
            $block = (int)$_POST['block'];
            $goods = $_POST['goods'];

            $html = '';
            $error = '';

            if ($goods) {
                $goods_info = $this->model->getGoods($goods, $block);
                if ($goods_info['error']){
                    $error = $goods_info['error'];
                }
                $products = $goods_info['items'];
            }
            else {
                $get_popular_products = $this->model->getPopularProducts($category, $block);
                if ($get_popular_products['error']){
                    $error = $get_popular_products['error'];
                }
                $products = $get_popular_products['products'];
            }

            foreach($products as $product){
                $html .= CoreView::returnTemplate('/inc/card.html', [
                    "product" => $product
                ]);
            }

            $result = array();
            $result['error'] = $error;
            $result['html'] = $html;
            $result['category'] = $category;
            $result['block'] = $block;
            echo json_encode($result);
            exit();
        }

        if ($act == 'accept_policy'){
            setcookie('accept_policy', true, time()+ 3600*24*30*365, '/');
            $result = array();
            echo json_encode($result);
            exit();
        }

        if ($act == 'get_contacts'){
            $result = $this->model->getPvzItems()['pvz'];
            echo json_encode($result);
            exit();
        }
    }
}