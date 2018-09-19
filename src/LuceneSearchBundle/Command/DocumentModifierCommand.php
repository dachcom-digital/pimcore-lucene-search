<?php

namespace LuceneSearchBundle\Command;

use LuceneSearchBundle\Modifier\QueuedDocumentModifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DocumentModifierCommand extends Command
{
    /**
     * @var QueuedDocumentModifier
     */
    protected $queuedDocumentModifier;

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
     * @param QueuedDocumentModifier $queuedDocumentModifier
     */
    public function __construct(QueuedDocumentModifier $queuedDocumentModifier)
    {
        parent::__construct();
        $this->queuedDocumentModifier = $queuedDocumentModifier;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setHidden(true)
            ->setName('lucenesearch:modifier:resolve')
            ->setDescription('For internal use only');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->queuedDocumentModifier->resolveQueue();
    }
}