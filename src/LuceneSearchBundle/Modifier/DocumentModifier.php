<?php

namespace LuceneSearchBundle\Modifier;

use LuceneSearchBundle\Configuration\Configuration;
use Pimcore\Model\Tool\TmpStore;

final class DocumentModifier
{
    const TEMP_STORE_TAG = 'lucene_search_modifier';

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
        $this->addJob(['marking' => $marking, 'query' => $query, 'type' => 'query']);
    }

    /**
     * @param \Zend_Search_Lucene_Index_Term $term
     * @param string                         $marking
     */
    public function markDocumentsViaTerm(\Zend_Search_Lucene_Index_Term $term, $marking = self::MARK_AVAILABLE)
    {
        // trigger command to run heavy processes in background
        $this->addJob(['marking' => $marking, 'term' => $term, 'type' => 'term']);
    }

    /**
     * @return \Zend_Search_Lucene_Interface
     */
    public function getIndex()
    {
        return \Zend_Search_Lucene::open(Configuration::INDEX_DIR_PATH_STABLE);
    }

    /**
     * @return bool
     */
    public function hasActiveJobs()
    {
        $activeJobs = $this->getActiveJobs();
        return count($activeJobs) > 0;
    }

    /**
     * @param bool $populateWithData
     *
     * @return array
     */
    public function getActiveJobs($populateWithData = false)
    {
        $activeJobs = TmpStore::getIdsByTag(DocumentModifier::TEMP_STORE_TAG);

        if ($populateWithData === false) {
            return is_array($activeJobs) ? $activeJobs : [];
        }

        if (!is_array($activeJobs)) {
            return [];
        }

        $jobs = [];
        foreach ($activeJobs as $processId) {

            $process = $this->getJob($processId);
            if (!$process instanceof TmpStore) {
                continue;
            }

            $jobs[] = $process;
        }

        return $jobs;
    }

    /**
     * Remove all existing Modifier Jobs in Queue.
     */
    public function clearActiveJobs()
    {
        $activeJobs = $this->getActiveJobs();
        foreach ($activeJobs as $activeJobId) {
            TmpStore::delete($activeJobId);
        }
    }

    /**
     * Add a modifier Job to the Queue.
     *
     * @param array $options
     */
    public function addJob(array $options)
    {
        $jobId = $this->getJobId();

        try {
            TmpStore::add($this->getJobId(), $options, self::TEMP_STORE_TAG);
        } catch (\Exception $e) {
            \Pimcore\Logger::error(sprintf('LuceneSearch: Could not add job (%s) to queue.', $jobId), $e->getTrace());
        }
    }

    /**
     * @param $processId
     *
     * @return null|TmpStore
     */
    public function getJob($processId)
    {
        $job = null;
        try {
            $job = TmpStore::get($processId);
        } catch (\Exception $e) {
            return null;
        }

        return $job;
    }

    /**
     * @param $processId
     */
    public function deleteJob($processId)
    {
        try {
            TmpStore::delete($processId);
        } catch (\Exception $e) {
            \Pimcore\Logger::error(sprintf('LuceneSearch: Could not delete queued job with id %s', $processId));
        }
    }

    /**
     * @return string
     */
    private function getJobId()
    {
        return uniqid('lucene_modifier-job-');
    }
}
