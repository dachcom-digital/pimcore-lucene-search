<?php

namespace LuceneSearchBundle\Event;

use Symfony\Component\EventDispatcher\Event;

class DocumentModificationEvent extends Event
{
    /**
     * @var \Zend_Search_Lucene_Document
     */
    private $document;

    /**
     * @var string
     */
    private $marking;

    /**
     * DocumentModificationEvent constructor.
     *
     * @param \Zend_Search_Lucene_Document $document
     * @param  string                      $marking
     */
    public function __construct(\Zend_Search_Lucene_Document $document, $marking)
    {
        $this->document = $document;
        $this->marking = $marking;
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
    public function getMarking()
    {
        return $this->marking;
    }
}