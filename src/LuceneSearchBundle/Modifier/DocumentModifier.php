<?php

namespace LuceneSearchBundle\Modifier;

use LuceneSearchBundle\Configuration\Configuration;
use Pimcore\Model\Tool\TmpStore;

final class DocumentModifier
{
    const MARK_AVAILABLE = 'available';

    const MARK_UNAVAILABLE = 'unavailable';

    const MARK_DELETED = 'deleted';

    /**
     * @param \Zend_Search_Lucene_Search_Query_Term $query
     * @param string                                $marking
     */
    public function markDocumentsViaQuery(\Zend_Search_Lucene_Search_Query_Term $query, $marking = self::MARK_AVAILABLE)
    {
        // trigger command to run heavy processes in background
        TmpStore::add($this->getJobId(), ['marking' => $marking, 'query' => $query, 'type' => 'query'], 'lucene_search_modifier');
    }

    /**
     * @param \Zend_Search_Lucene_Index_Term $term
     * @param string                         $marking
     */
    public function markDocumentsViaTerm(\Zend_Search_Lucene_Index_Term $term, $marking = self::MARK_AVAILABLE)
    {
        // trigger command to run heavy processes in background
        TmpStore::add($this->getJobId(), ['marking' => $marking, 'term' => $term, 'type' => 'term'], 'lucene_search_modifier');
    }

    /**
     * @return \Zend_Search_Lucene_Interface
     */
    public function getIndex()
    {
        return \Zend_Search_Lucene::open(Configuration::INDEX_DIR_PATH_STABLE);
    }

    /**
     * @return string
     */
    private function getJobId()
    {
        return uniqid('lucene_modifier-job-');
    }
}
