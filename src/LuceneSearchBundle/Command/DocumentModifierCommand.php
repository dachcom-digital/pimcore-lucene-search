<?php

namespace LuceneSearchBundle\Command;

use LuceneSearchBundle\Event\DocumentModificationEvent;
use LuceneSearchBundle\LuceneSearchEvents;
use LuceneSearchBundle\Modifier\DocumentModifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DocumentModifierCommand extends Command
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
     * DocumentModifierCommand constructor.
     *
     * @param DocumentModifier         $documentModifier
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(DocumentModifier $documentModifier, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();
        $this->documentModifier = $documentModifier;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setHidden(true)
            ->setName('lucenesearch:modifier:resolve')
            ->setDescription('For internal use only')
            ->addArgument('marking')
            ->addArgument('documentIds');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Zend_Search_Lucene_Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $marking = $input->getArgument('marking');
        $documentIdsString = $input->getArgument('documentIds');

        $documentIds = explode(',', $documentIdsString);

        if ($marking === DocumentModifier::MARK_AVAILABLE || $marking === DocumentModifier::MARK_UNAVAILABLE) {
            $this->changeDocumentsAvailability($documentIds, $marking);
        } elseif ($marking === DocumentModifier::MARK_DELETED) {
            $this->deleteDocuments($documentIds);
        }
    }

    /**
     * @param array $documentIds
     * @param       $marking
     *
     * @throws \Zend_Search_Lucene_Exception
     */
    public function changeDocumentsAvailability(array $documentIds, $marking)
    {
        if (count($documentIds) === 0) {
            return;
        }

        $index = $this->documentModifier->getIndex();
        $indexModified = false;

        foreach ($documentIds as $documentId) {

            $newDocument = new \Zend_Search_Lucene_Document();
            $currentDocument = $index->getDocument($documentId);

            //check if state is same. if so, skip modification.
            $currentInternalValue = null;
            if (in_array('internalAvailability', $currentDocument->getFieldNames())) {
                $currentInternalValue = $currentDocument->getField('internalAvailability')->value;
            }

            if ($currentInternalValue === $marking) {
                continue;
            }

            $indexModified = true;

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

            $index->delete($documentId);
            $index->commit();
            $index->addDocument($modificationEvent->getDocument());

        }

        // no data has been modified, no need to optimize index.
        if ($indexModified === false) {
            return;
        }

        $index->optimize();
    }

    /**
     * @param array $documentIds
     *
     * @throws \Zend_Search_Lucene_Exception
     */
    public function deleteDocuments(array $documentIds)
    {
        if (count($documentIds) === 0) {
            return;
        }

        $index = $this->documentModifier->getIndex();

        foreach ($documentIds as $documentId) {
            $index->delete($documentId);
            $index->commit();
        }

        $index->optimize();

    }
}