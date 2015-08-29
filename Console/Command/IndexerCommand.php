<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\ParallelIndexer\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Framework\Console\Cli;
use Magento\Framework\App;
use Magento\Framework\Event\ManagerInterface;

/**
 * Command for executing cron jobs
 */
class IndexerCommand extends Command
{
    /**
     * Name of input option
     */
    const INPUT_KEY_INDEXER = 'indexer';
    const INPUT_KEY_MAX_PRODUCTS = 'max-products';
    const INPUT_KEY_MAX_CATEGORIES = 'max-categories';
    const INPUT_KEY_MAX_CUSTOMERS = 'max-customers';
    const INPUT_KEY_MAX_RULES = 'max-rules';
    const INPUT_KEY_REINDEX_ID = 'reindex-id';



    /**
     * @var \Magento\Catalog\Model\Resource\Product\Collection
     */
    private $productCollection;

    /**
     * @var \Magento\Catalog\Model\Resource\Category\Collection
     */
    private $categoryCollection;

    /**
     * @var \Magento\Framework\Indexer\IndexerRegistry
     */
    private $indexRegistry;

    /**
     * @var \Magento\Indexer\Model\Indexer\Collection
     */
    private $indexerCollection;

    /**
     * @var \Magento\TargetRule\Model\Resource\Rule\Collection
     */
    private $ruleCollection;

    /**
     * @var \Magento\Customer\Model\Resource\Customer\Collection
     */
    private $customerCollection;

    /**
     * @param App\State $state
     * @param \Magento\Catalog\Model\Resource\Product\Collection $productCollection
     * @param \Magento\Catalog\Model\Resource\Category\Collection $categoryCollection
     * @param \Magento\Customer\Model\Resource\Customer\Collection $customerCollection
     * @param \Magento\TargetRule\Model\Resource\Rule\Collection $ruleCollection
     * @param \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry
     * @param \Magento\Indexer\Model\Indexer\Collection $indexerCollection
     * @param array $parameters
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Catalog\Model\Resource\Product\Collection $productCollection,
        \Magento\Catalog\Model\Resource\Category\Collection $categoryCollection,
        \Magento\Customer\Model\Resource\Customer\Collection $customerCollection,
        \Magento\TargetRule\Model\Resource\Rule\Collection $ruleCollection,
        \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
        \Magento\Indexer\Model\Indexer\Collection $indexerCollection,
        array $parameters = array()
    ) {
        $this->_state = $state;
        $this->productCollection = $productCollection;
        $this->categoryCollection = $categoryCollection;
        $this->indexRegistry = $indexerRegistry;
        $this->indexerCollection = $indexerCollection;
        $this->customerCollection = $customerCollection;
        $this->ruleCollection = $ruleCollection;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $options = [
            new InputOption(
                self::INPUT_KEY_INDEXER,
                null,
                InputOption::VALUE_OPTIONAL,
                'Run just indexer(s) defined by Id',
                'all'
            ),
            new InputOption(
                self::INPUT_KEY_MAX_CATEGORIES,
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of Categories in system'
            ),
            new InputOption(
                self::INPUT_KEY_MAX_PRODUCTS,
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of Products in system'
            ),
            new InputOption(
                self::INPUT_KEY_MAX_CUSTOMERS,
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of Customers in system'
            ),
            new InputOption(
                self::INPUT_KEY_MAX_RULES,
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of Rules in system'
            ),
            new InputOption(
                self::INPUT_KEY_REINDEX_ID,
                null,
                InputOption::VALUE_OPTIONAL,
                'Single ID which will be used for all indexers to run reindexRow(id). ' .
                'This will ensure that indexers can run in parallel for single entity.'
            ),
        ];
        $this->setName('dev:single-row-indexer')
            ->setDescription('Run Single Row Indexer')
            ->setDefinition($options);
        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_state->setAreaCode('crontab');

        $indexersData = $this->indexerCollection->getItems();
        if (!is_null($input->getOption(self::INPUT_KEY_MAX_PRODUCTS))) {
            $productsNum = $input->getOption(self::INPUT_KEY_MAX_PRODUCTS);
        } else {
            $productsNum = $this->productCollection->getSize();
        }

        if (!is_null($input->getOption(self::INPUT_KEY_MAX_CATEGORIES))) {
            $categoriesNum = $input->getOption(self::INPUT_KEY_MAX_CATEGORIES);
        } else {
            $categoriesNum = $this->categoryCollection->getSize();
        }

        if (!is_null($input->getOption(self::INPUT_KEY_MAX_CUSTOMERS))) {
            $customersNum = $input->getOption(self::INPUT_KEY_MAX_CUSTOMERS);
        } else {
            $customersNum = $this->customerCollection->getSize();
        }

        if (!is_null($input->getOption(self::INPUT_KEY_MAX_RULES))) {
            $rulesNum = $input->getOption(self::INPUT_KEY_MAX_RULES);
        } else {
            $rulesNum = $this->ruleCollection->getSize();
        }

        //These indexers are not ready to run in single-row mode
        $excludeIndexers = ['catalogrule_rule', 'catalogsearch_fulltext'];

        $productIndexers = ['catalog_product_flat', 'catalog_product_price',
            'catalog_product_attribute', 'cataloginventory_stock', 'catalogrule_product', 'targetrule_product_rule',
            'catalogpermissions_product', 'catalogrule_rule', 'catalogsearch_fulltext'];
        $categoryIndexers = ['catalog_category_flat', 'catalog_category_product', 'catalog_product_category',
            'catalogpermissions_category'];
        $ruleIndexers = ['targetrule_rule_product'];
        $customerIndexres = ['customer_grid'];

        $requestedIndexer = $input->getOption('indexer');

        if ($requestedIndexer == 'all') {
            $runThese = [];
        } else {
            $runThese = explode(',', $input->getOption('indexer'));
        }

        foreach ($indexersData as $indexer) {

            if (in_array($indexer->getIndexerId(), $excludeIndexers))
                continue;
            if (count($runThese) > 0 && !in_array($indexer->getIndexerId(), $runThese)) {
                continue;
            }
            $max = 0;
            $entity = '';
            if (in_array($indexer->getIndexerId(), $productIndexers)) {
                $max = $productsNum;
                $entity = 'Product';
            } else if (in_array($indexer->getIndexerId(), $categoryIndexers)) {
                $max = $categoriesNum;
                $entity = 'Category';
            } else if (in_array($indexer->getIndexerId(), $customerIndexres)) {
                $max = $customersNum;
                $entity = 'Customer';
            } else if (in_array($indexer->getIndexerId(), $ruleIndexers)) {
                $max = $rulesNum;
                $entity = 'Rule';
            }

            if (!is_null($input->getOption(self::INPUT_KEY_REINDEX_ID))) {
                $id = $input->getOption(self::INPUT_KEY_REINDEX_ID);
            } else {
                $id = mt_rand(1, $max);
            }

            $output->writeln('<info>' . $entity . '# id '
                . $id . ' (out of ' . $max . ') for ' . $indexer->getIndexerId() . ' </info>');
            $this->indexRegistry->get($indexer->getIndexerId())->reindexRow($id);
            $output->writeln('<info>' . $indexer->getDescription() . ' completed.</info>');
        }
    }
}
