<?php

namespace LuceneSearchBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class HtmlParserEvent extends Event
{
    /**
     * @var \Zend_Search_Lucene_Document
     */
    private $document;

    /**
     * @var string
     */
    private $html;

    /**
     * @var array
     */
    private $params;

    /**
     * HtmlParserEvent constructor.
     *
     * @param \Zend_Search_Lucene_Document $document
     * @param                              $html
     * @param                              $params
     */
    public function __construct(\Zend_Search_Lucene_Document $document, $html, $params)
    {
        $this->document = $document;
        $this->html = $html;
        $this->params = $params;
    }

    /**
     * @param \Zend_Search_Lucene_Document $document
     *
     * @return \Zend_Search_Lucene_Document
     */
    public function setDocument(\Zend_Search_Lucene_Document $document)
    {
        return $this->document = $document;
    }

    /**
     * @return \Zend_Search_Lucene_Document
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }
}