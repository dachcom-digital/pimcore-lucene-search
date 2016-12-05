<?php

namespace LuceneSearch\Controller\Plugin;

use Pimcore\Model\Document;
use LuceneSearch\Tool;

class Frontend extends \Zend_Controller_Plugin_Abstract {

    public function preDispatch(\Zend_Controller_Request_Abstract $request)
    {
        parent::preDispatch($request);

        /** @var \Pimcore\Controller\Action\Helper\ViewRenderer $renderer */
        $renderer = \Zend_Controller_Action_HelperBroker::getExistingHelper('ViewRenderer');
        $renderer->initView();

        /** @var \Pimcore\View $view */
        $view = $renderer->view;

        $view->addHelperPath(PIMCORE_PLUGINS_PATH . '/LuceneSearch/lib/LuceneSearch/Helper/View', 'LuceneSearch\Helper\View');

    }

    public function postDispatch(\Zend_Controller_Request_Abstract $request)
    {
        parent::postDispatch($request);

        /** @var \Pimcore\Controller\Action\Helper\ViewRenderer $renderer */
        $renderer = \Zend_Controller_Action_HelperBroker::getExistingHelper('ViewRenderer');

        /** @var \Pimcore\View $view */
        $view = $renderer->view;

        if( $view->document instanceof Document\Page)
        {
            $metaData = $view->document->getMetaData();

            if( !empty( $metaData ))
            {
                foreach( $metaData as $metaKey => $meta )
                {
                    if( $meta['idValue'] === 'lucene-search:meta' && !Tool\Request::isLuceneSearchCrawler())
                    {
                        //It's impossible to remove meta fields, if once triggered. But we can remove its content.
                        $view->headMeta()->setName('lucene-search:meta', NULL, []);
                        break;
                    }
                }
            }
        }
    }
}
