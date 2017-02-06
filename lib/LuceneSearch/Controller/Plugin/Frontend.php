<?php

namespace LuceneSearch\Controller\Plugin;

use Pimcore\Model\Document;
use LuceneSearch\Tool;

class Frontend extends \Zend_Controller_Plugin_Abstract
{
    /**
     * @param \Zend_Controller_Request_Abstract $request
     */
    public function preDispatch(\Zend_Controller_Request_Abstract $request)
    {
        parent::preDispatch($request);

        /** @var \Pimcore\Controller\Action\Helper\ViewRenderer $renderer */
        $renderer = \Zend_Controller_Action_HelperBroker::getExistingHelper('ViewRenderer');

        if ($renderer->view === NULL) {
            $renderer->initView();
        }

        /** @var \Pimcore\View $view */
        $view = $renderer->view;

        $view->addHelperPath(PIMCORE_PLUGINS_PATH . '/LuceneSearch/lib/LuceneSearch/Helper/View', 'LuceneSearch\Helper\View');
    }
}
