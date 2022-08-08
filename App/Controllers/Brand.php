<?php
namespace App\Controllers;

use Core\Controller as CoreController;
use Core\View as CoreView;
use App\Models\Brand as BrandModel;

class Brand extends CoreController{
    private $model;
    public function __construct(){
        parent::__construct();
        $this->model = new BrandModel();
    }

    /**
     * Бренды
     */
    public function index($url){
        $url_params = explode('/', $url);
        $page_url = $url_params[count($url_params) - 1];
        $page = $this->model->getPageInfo($page_url);
        if ($page['error']){
            $this->error = $page['error'];
        }

        if ($url == 'brendy'){
            CoreView::renderTemplate('Brands/index.html', [
                'settings' => $this->settings,
                'header_pages' => $this->header_pages,
                'catalog_menu_pages' => $this->catalog_menu_pages,
                'page' => $page['page'],
                'catalog_menu' => $this->catalog_menu,
                'params' => $this->params
            ]);
        }
        else {
            $products = $this->model->getBrandProducts($page_url);
            if ($products['error']) {
                $this->error = $products['error'];
            }
            $products = $products['products'];
            CoreView::renderTemplate('Brands/brand.html', [
                'settings' => $this->settings,
                'header_pages' => $this->header_pages,
                'catalog_menu_pages' => $this->catalog_menu_pages,
                'page' => $page['page'],
                'catalog_menu' => $this->catalog_menu,
                'params' => $this->params,
                'products' => $products
            ]);
        }
    }
}