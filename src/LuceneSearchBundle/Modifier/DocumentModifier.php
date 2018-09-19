<?php

namespace LuceneSearchBundle\Modifier;

use LuceneSearchBundle\Configuration\Configuration;

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
        $hits = $this->getHits($query);

        if (count($hits) === 0) {
            return;
        }

        $documentIds = [];
        foreach ($hits as $hit) {

            if (!$hit instanceof \Zend_Search_Lucene_Search_QueryHit) {
                continue;
            }

            $documentIds[] = $hit->id;
        }

        // trigger command to run heavy processes in background
        $this->triggerCommand($marking, $documentIds);

    }

    /**
     * @param \Zend_Search_Lucene_Index_Term $term
     * @param string                         $marking
     */
    public function markDocumentsViaTerm(\Zend_Search_Lucene_Index_Term $term, $marking = self::MARK_AVAILABLE)
    {
        try {
            $docIds = $this->getIndex()->termDocs($term);
        } catch (\Exception $e) {
            $docIds = [];
        }

        $documentIds = [];
        foreach ($docIds as $id) {
            $documentIds[] = $id;
        }

        // trigger command to run heavy processes in background
        $this->triggerCommand($marking, $documentIds);
    }

    /**
     * @return \Zend_Search_Lucene_Interface
     */
    public function getIndex()
    {
        return \Zend_Search_Lucene::open(Configuration::INDEX_DIR_PATH_STABLE);
    }

    /**
     * @param       $marking
     * @param array $documentIds
     */
    private function triggerCommand($marking, array $documentIds)
    {
        if (count($documentIds) === 0) {
            return;
        }

        \Pimcore\Tool\Console::runPhpScriptInBackground(
            realpath(PIMCORE_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'console'),
            'lucenesearch:modifier:resolve ' . escapeshellarg($marking) . ' ' . escapeshellarg(implode(',', $documentIds)),
            PIMCORE_LOG_DIRECTORY . DIRECTORY_SEPARATOR . 'lucene-search-modifier-output.log'
        );
    }

    /**
     * @param \Zend_Search_Lucene_Search_Query_Term $query
     *
     * @return array
     */
    private function getHits(\Zend_Search_Lucene_Search_Query_Term $query)
    {
        try {
            $hits = $this->getIndex()->find($query);
        } catch (\Exception $e) {
            return [];
        }

        if (!is_array($hits) || count($hits) === 0) {
            return [];
        }

        return $hits;
    }
}
