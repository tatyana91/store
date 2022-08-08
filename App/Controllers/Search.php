<?php
namespace App\Controllers;

use Core\Controller as CoreController;
use Core\View as CoreView;
use App\Models\Search as SearchModel;

class Search extends CoreController{
    private $model;
    public function __construct(){
        parent::__construct();
        $this->model = new SearchModel();
    }

    /**
     * Поиск
     */
    public function main(){
        $query = $_GET['q'];
        $items = $this->model->search($query);

        $page = $this->model->getPageInfo('poisk');

        CoreView::renderTemplate('Search/index.html', [
            'settings' => $this->settings,
            'header_pages' => $this->header_pages,
            'catalog_menu_pages' => $this->catalog_menu_pages,
            'results' => $items['search_items'],
            'page' => $page['page'],
            'catalog_menu' => $this->catalog_menu,
            'query' => $query,
            'params' => $this->params
        ]);
    }
}