<?php

namespace LuceneSearchBundle\Twig\Extension;

class PaginationExtension extends \Twig_Extension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_Function('lucene_search_pagination', [$this, 'getPagination'], [
                'needs_environment' => TRUE,
                'needs_context'     => TRUE,
                'is_safe'           => ['html']
            ]),
            new \Twig_Function('lucene_search_pagination_url', [$this, 'getPaginationUrl'], [
                'needs_context' => TRUE,
                'is_safe'       => ['html']
            ]),
        ];
    }

    /**
     * @param \Twig_Environment $environment
     * @param array             $context
     * @param null              $options
     *
     * @return string
     */
    public function getPagination(\Twig_Environment $environment, $context = [], $options = NULL)
    {
        $defaults = [
            'paginationUrl'      => '',
            'paginationElements' => 5,
            'viewTemplate'       => 'default',
            'paginationClass'    => 'paginator'
        ];

        $params = array_merge($defaults, $options);

        $pageStart = 1;
        $searchCurrentPage = (int)$context['searchCurrentPage'];
        $searchAllPages = (int)$context['searchAllPages'];

        if ($searchCurrentPage > ceil($params['paginationElements'] / 2)) {
            $pageStart = $searchCurrentPage - 2;
        }

        $pageEnd = $pageStart + $params['paginationElements'];

        if ($pageEnd > $searchAllPages) {
            $pageEnd = $searchAllPages;
        }

        $paginationUrlInfo = parse_url($params['paginationUrl']);

        $path = '';
        $scheme = '';
        $host = '';

        if (isset($paginationUrlInfo['query']) && !empty($paginationUrlInfo['query'])) {
            $q = $paginationUrlInfo['query'];
            $paginationUrl = '?' . $q . (substr($q, -1) === '&' ? '' : '&');
        } else {
            $paginationUrl = '?';
        }

        if (isset($paginationUrlInfo['path']) && !empty($paginationUrlInfo['path'])) {
            $path = $paginationUrlInfo['path'];
        }

        if (isset($paginationUrlInfo['scheme']) && !empty($paginationUrlInfo['scheme'])) {
            $scheme = $paginationUrlInfo['scheme'] . '://';
        }

        if (isset($paginationUrlInfo['host']) && !empty($paginationUrlInfo['host'])) {
            $host = $paginationUrlInfo['host'];
        }

        $viewParams = [
            'searchUrl'         => $scheme . $host . $path . $paginationUrl,
            'currentSearchPage' => $searchCurrentPage,
            'searchAllPages'    => $searchAllPages,
            'searchPageStart'   => $pageStart,
            'searchPageEnd'     => $pageEnd,
            'searchUrlData'     => $this->getPaginationUrl($context),
            'class'             => $params['paginationClass']
        ];

        return $environment->render(
            '@LuceneSearch/List/Partial/Pagination/' . $params['viewTemplate'] . '.html.twig',
            $viewParams
        );
    }

    /**
     * @param array $context
     * @param null  $query
     *
     * @return string
     */
    public function getPaginationUrl($context = [], $query = NULL)
    {
        $params = [
            'language' => !empty($context['searchLanguage']) ? $context['searchLanguage'] : NULL,
            'country'  => !empty($context['searchCountry']) ? $context['searchCountry'] : NULL,
            'category' => !empty($context['searchCategory']) ? $context['searchCategory'] : NULL,
            'q'        => !empty($query) ? $query : $context['searchQuery']
        ];

        return http_build_query($params);
    }
}