<?php

namespace LuceneSearchBundle\Modifier;

use LuceneSearchBundle\Event\DocumentModificationEvent;
use LuceneSearchBundle\LuceneSearchEvents;
use Pimcore\Model\Tool\TmpStore;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class QueuedDocumentModifier
{
    /**
     * @var DocumentModifier
     */
    protected $documentModifier;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var \Zend_Search_Lucene_Interface
     */
    protected $index;

    /**
     * DocumentModifierCommand constructor.
     *
     * @param DocumentModifier         $documentModifier
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(DocumentModifier $documentModifier, EventDispatcherInterface $eventDispatcher)
    {
        $this->documentModifier = $documentModifier;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function resolveQueue()
    {
        $processIds = TmpStore::getIdsByTag('lucene_search_modifier');

        // modifier is running, wait for next cycle.
        if (TmpStore::get('lucene_search_modifier_flag') instanceof TmpStore) {
            return;
        }

        TmpStore::add('lucene_search_modifier_flag', ['running' => true]);

        /** @var TmpStore[] $sortedProcesses */
        $sortedProcesses = [];

        foreach ($processIds as $processId) {
            $process = TmpStore::get($processId);

            if (!$process instanceof TmpStore) {
                continue;
            }

            $sortedProcesses[] = $process;

        }

        if (count($sortedProcesses) === 0) {
            TmpStore::delete('lucene_search_modifier_flag');
            return;
        }

        usort($sortedProcesses, function ($a, $b) {
            /**
             * @var $a TmpStore
             * @var $b TmpStore
             */
            return strtotime($a->getDate()) - strtotime($b->getDate());
        });

        $this->index = $this->documentModifier->getIndex();

        foreach ($sortedProcesses as $process) {

            /** @var array $data */
            $data = $process->getData();
            $type = $data['type'];
            $marking = $data['marking'];

            $documentIds = $type === 'query' ? $this->getDocumentIdsByQuery($data['query']) : $this->getDocumentIdsByTerm($data['term']);

            try {
                if ($marking === DocumentModifier::MARK_AVAILABLE || $marking === DocumentModifier::MARK_UNAVAILABLE) {
                    $this->changeDocumentsAvailability($documentIds, $marking);
                } elseif ($marking === DocumentModifier::MARK_DELETED) {
                    $this->deleteDocuments($documentIds);
                }
            } catch (\Exception $e) {
                \Pimcore\Logger::error('LuceneSearch: Document Modifier Error: ' . $e->getMessage(), $e->getTrace());
            }

            TmpStore::delete($process->getId());

        }

        $this->index->optimize();

        TmpStore::delete('lucene_search_modifier_flag');
    }

    /**
     * @param array $documentIds
     * @param       $marking
     *
     * @throws \Zend_Search_Lucene_Exception
     */
    protected function changeDocumentsAvailability(array $documentIds, $marking)
    {
        if (count($documentIds) === 0) {
            return;
        }

        foreach ($documentIds as $documentId) {

            $newDocument = new \Zend_Search_Lucene_Document();
            $currentDocument = $this->index->getDocument($documentId);

            // document is already marked as deleted: skip check.
            if ($this->index->isDeleted($documentId)) {
                continue;
            }

            //check if state is same. if so, skip modification.
            $currentInternalValue = null;
            if (in_array('internalAvailability', $currentDocument->getFieldNames())) {
                $currentInternalValue = $currentDocument->getField('internalAvailability')->value;
            }

            if ($currentInternalValue === $marking) {
                continue;
            }

            foreach ($currentDocument->getFieldNames() as $name) {

                if ($name === 'internalAvailability') {
                    continue;
                }

                $newDocument->addField($currentDocument->getField($name));
            }

            $newDocument->addField(\Zend_Search_Lucene_Field::keyword('internalAvailability', $marking));

            $modificationEvent = new DocumentModificationEvent($newDocument, $marking);
            $this->eventDispatcher->dispatch(
                LuceneSearchEvents::LUCENE_SEARCH_DOCUMENT_MODIFICATION,
                $modificationEvent
            );

            $this->index->delete($documentId);
            $this->index->addDocument($modificationEvent->getDocument());
            $this->index->commit();
        }
    }

    /**
     * @param array $documentIds
     *
     * @throws \Zend_Search_Lucene_Exception
     */
    protected function deleteDocuments(array $documentIds)
    {
        if (count($documentIds) === 0) {
            return;
        }

        foreach ($documentIds as $documentId) {
            $this->index->delete($documentId);
            $this->index->commit();
        }
    }

    /**
     * @param \Zend_Search_Lucene_Index_Term $term
     *
     * @return array
     */
    protected function getDocumentIdsByTerm(\Zend_Search_Lucene_Index_Term $term)
    {
        try {
            $documentIds = $this->index->termDocs($term);
        } catch (\Exception $e) {
            return [];
        }

        return $documentIds;
    }

    /**
     * @param \Zend_Search_Lucene_Search_Query_Term $query
     *
     * @return array
     */
    protected function getDocumentIdsByQuery(\Zend_Search_Lucene_Search_Query_Term $query)
    {
        try {
            $hits = $this->index->find($query);
        } catch (\Exception $e) {
            return [];
        }

        if (!is_array($hits) || count($hits) === 0) {
            return [];
        }

        $documentIds = [];
        foreach ($hits as $hit) {

            if (!$hit instanceof \Zend_Search_Lucene_Search_QueryHit) {
                continue;
            }

            $documentIds[] = $hit->id;
        }

        return $documentIds;
    }
}
