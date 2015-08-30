# Indexers Run In Parallel

This is a Magento 2 module, which provides CLI command to run indexers in single row mode. This is a mode,
when you update reindex data for the single row os specific entity rather than all the entites. 
For example, reindexing of the single Product.

Besides, It contains bash script which allow to run multiple indexers processes in parallel. This is needed in order
to test how well indexers behave when run on the single entity simultaneously. Can be helpful for the asynchronous reindexing.

## Usage

1. Install Magento 2
2. Ensure that that minimum stability is "dev" in composer.json (add/update this line "minimum-stability": "dev")
3. Invoke > composer require vrann/magento-parallel-indexer
4. Invoke > bin/magento module:enable Magento_ParallelIndexer
5. Invoke > bin/magento setup:upgrade

Now CLI command is ready to be used. In order to test it run:
```php
bin/magento dev:single-row-indexer
```

With default parameters it will run all the indexers for the random row in Products, Customers, Categories and 
Target Rules entities. It will load collection of entities in order to get the maximum allowed number for the row id.
That's why it is not optimal way. To optimize it, use parameters

```
bin/magento dev:single-row-indexer
    --indexer=catalogpermissions_product,catalogpermissions_category  //specify indexers to run
    --max-categories=100 //amount of Products. Id will be generated in range from 1 to this number
    --max-products=200 //amount of Categories. Id will be generated in range from 1 to this number
    --max-customers=300 //amount of Customers. Id will be generated in range from 1 to this number
    --max-rules=10 //amount of Rules. Id will be generated in range from 1 to this number
    --reindex-id=1 //avoid generation of random id and use this id for all the entities
```

Additionally, bash script is provided to run the command in parallel.

```
vendor/bin/./parallel-run.sh 2 2 0
```
First argument is number of parallel processes to run. Second number is number of iterations to run in parallel process.
Third argument is starting counter for the processes.

After that, in order to pass arguments to dev:single-row-indexer CLI command, use this order:
4th: --indexer
5th: --reindex-id
6th: --max-categories
7th: --max-products
8th: --max-customers
9th: --max-rules

```
vendor/bin/./parallel-run.sh 2 2 0 catalogpermissions_product,catalogpermissions_category 1 100 200 300 10
```