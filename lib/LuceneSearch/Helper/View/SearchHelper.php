<?php

namespace LuceneSearch\Helper\View;

class SearchHelper extends \Zend_View_Helper_Abstract {

    public function searchHelper()
    {
        return $this;
    }

    /**
     * @param array $customParams
     *
     */
    public function getPagination( $customParams = [] )
    {
        $defaults = [
            'paginationElements'    => 5,
            'viewTemplate'          => 'default',
            'paginationClass'       => 'paginator'
        ];

        $params = array_merge( $defaults, $customParams );

        $pageStart = 1;
        $searchCurrentPage = (int) $this->view->searchCurrentPage;
        $searchAllPages = (int) $this->view->searchAllPages;

        if($searchCurrentPage > ceil( $params['paginationElements'] / 2 ))
        {
            $pageStart = $searchCurrentPage - 2;
        }

        $pageEnd = $pageStart + $params['paginationElements'];

        if($pageEnd > $searchAllPages)
        {
            $pageEnd = $searchAllPages;
        }

        $viewParams = [
            'currentSearchPage' => $searchCurrentPage,
            'searchAllPages'    => $searchAllPages,
            'searchPageStart'   => $pageStart,
            'searchPageEnd'     => $pageEnd,
            'searchUrlData'     => $this->createPaginationUrl(),
            'class'             => $params['paginationClass']
        ];

        return $this->view->partial('/search/helper/pagination/' . $params['viewTemplate'] . '.php', $viewParams);

    }

    public function createPaginationUrl( $query = '', $returnAsArray = FALSE )
    {
        $params = [
            'language'  => !empty( $this->view->searchLanguage ) ? $this->view->searchLanguage : NULL,
            'country'   => !empty( $this->view->searchCountry ) ? $this->view->searchCountry : NULL,
            'category'  => !empty( $this->view->searchCategory ) ? $this->view->searchCategory : NULL,
            'q'         => !empty( $query ) ? $query : $this->view->searchQuery
        ];

        return $returnAsArray ? $params : http_build_query( $params );
    }
}