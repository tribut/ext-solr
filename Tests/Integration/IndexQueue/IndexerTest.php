<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Timo Schmidt <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;
use ApacheSolrForTypo3\Solr\IndexQueue\Item;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\Tests\Integration\IntegrationTest;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Lang\LanguageService;

/**
 * Testcase for the record indexer
 *
 * @author Timo Schmidt
 */
class IndexerTest extends IntegrationTest
{

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @var Indexer
     */
    protected $indexer;

    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->indexer = GeneralUtility::makeInstance(Indexer::class);

        /** @var $beUser  \TYPO3\CMS\Core\Authentication\BackendUserAuthentication */
        $beUser = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $GLOBALS['BE_USER'] = $beUser;

        /** @var $languageService  \TYPO3\CMS\Lang\LanguageService */
        $languageService = GeneralUtility::makeInstance(LanguageService::class);
        $languageService->csConvObj = GeneralUtility::makeInstance(CharsetConverter::class);
        $GLOBALS['LANG'] = $languageService;
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations.
     *
     * @test
     */
    public function canIndexItemWithMMRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_mm_relation.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);

        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["the tag"]', $solrContent, 'Did not find MM related tag');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations.
     *
     * @test
     */
    public function canIndexTranslatedItemWithMMRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_translated_record_with_mm_relation.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["translated tag"]', $solrContent, 'Did not find MM related tag');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"translation"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * This testcase should check if we can queue an custom record with MM relations and respect the additionalWhere clause.
     *
     * @test
     */
    public function canIndexItemWithMMRelationAndAdditionalWhere()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_mm_relationAndAdditionalWhere.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 88);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["another tag"]', $solrContent, 'Did not find MM related tag');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * This testcase is used to check if direct relations can be resolved with the RELATION configuration
     *
     * @test
     */
    public function canIndexItemWithDirectRelation()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePathByName('fake_extension2_directrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_direct_relation.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["the category"]', $solrContent, 'Did not find direct related category');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"sysCategoryId_stringM":["1"]', $solrContent, 'Uid of related sys_category couldn\'t be resolved by using "foreignLabelField"');
        $this->assertContains('"sysCategory_stringM":["sys_category"]', $solrContent, 'Label of related sys_category couldn\'t be resolved by using "foreignLabelField" and "enableRecursiveValueResolution"');
        $this->assertContains('"sysCategoryDescription_stringM":["sys_category description"]', $solrContent, 'Description of related sys_category couldn\'t be resolved by using "foreignLabelField" and "enableRecursiveValueResolution"');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * This testcase is used to check if direct relations can be resolved with the RELATION configuration
     * and could be limited with an additionalWhere clause at the same time
     *
     * @test
     */
    public function canIndexItemWithDirectRelationAndAdditionalWhere()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePathByName('fake_extension2_directrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_direct_relationAndAdditionalWhere.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');

        $this->assertContains('"category_stringM":["another category"]', $solrContent, 'Did not find direct related category');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * @test
     */
    public function canUseConfigurationFromTemplateInRootLine()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_directrelated'] = include($this->getFixturePathByName('fake_extension2_directrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_with_configuration_in_rootline.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);
        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');

        $this->assertContains('"fieldFromRootLine_stringS":"TESTNEWS"', $solrContent, 'Did not find field configured in rootline');
        $this->assertContains('"title":"testnews"', $solrContent, 'Could not index document into solr');
        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * @test
     */
    public function canGetAdditionalDocumentsInterfaceOnly()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'][] = \ApacheSolrForTypo3\Solr\IndexQueue\AdditionalIndexQueueItemIndexer::class;
        $document = new \Apache_Solr_Document;
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = GeneralUtility::makeInstance(Item::class, $metaData, $record);
        $this->callInaccessibleMethod($this->indexer,'getAdditionalDocuments', $item, 0, $document);
    }

    /**
     * @test
     */
    public function canGetAdditionalDocumentsNotImplementingInterface()
    {
        $this->setExpectedException(\UnexpectedValueException::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'][] = \ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\Helpers\DummyIndexer::class;
        $document = new \Apache_Solr_Document;
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = GeneralUtility::makeInstance(Item::class, $metaData, $record);
        $this->callInaccessibleMethod($this->indexer, 'getAdditionalDocuments', $item, 0, $document);
    }

    /**
     * @test
     */
    public function canGetAdditionalDocumentsNonExistingClass()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'][] = 'NonExistingClass';
        $document = new \Apache_Solr_Document;
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = GeneralUtility::makeInstance(Item::class, $metaData, $record);

        $result = $this->callInaccessibleMethod($this->indexer,'getAdditionalDocuments', $item, 0, $document);
    }

    /**
     * @test
     */
    public function canGetAdditionalDocuments()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments'][] = \ApacheSolrForTypo3\Solr\Tests\Integration\IndexQueue\Helpers\DummyAdditionalIndexQueueItemIndexer::class;
        $document = new \Apache_Solr_Document;
        $metaData = ['item_type' => 'pages'];
        $record = [];
        $item = GeneralUtility::makeInstance(Item::class, $metaData, $record);

        $result = $this->callInaccessibleMethod($this->indexer,'getAdditionalDocuments', $item, 0, $document);
        $this->assertSame([], $result);
    }


    /**
     * @test
     */
    public function testCanIndexCustomRecordOutsideOfSiteRoot()
    {
        $this->cleanUpSolrServerAndAssertEmpty();

        // create fake extension database table and TCA
        $this->importDumpFromFixture('fake_extension2_table.sql');
        $GLOBALS['TCA']['tx_fakeextension_domain_model_bar'] = include($this->getFixturePathByName('fake_extension2_bar_tca.php'));
        $GLOBALS['TCA']['tx_fakeextension_domain_model_mmrelated'] = include($this->getFixturePathByName('fake_extension2_mmrelated_tca.php'));
        $this->importDataSetFromFixture('can_index_custom_record_outside_site_root.xml');

        $result = $this->addToQueueAndIndexRecord('tx_fakeextension_domain_model_bar', 111);

        $this->assertTrue($result, 'Indexing was not indicated to be successful');

        // do we have the record in the index with the value from the mm relation?
        $this->waitToBeVisibleInSolr();
        $solrContent = file_get_contents('http://localhost:8999/solr/core_en/select?q=*:*');

        $this->assertContains('"numFound":1', $solrContent, 'Could not index document into solr');
        $this->assertContains('"title":"external testnews"', $solrContent, 'Could not index document into solr');
        $this->cleanUpSolrServerAndAssertEmpty();
    }

    /**
     * @param string $table
     * @param int $uid
     * @return \Apache_Solr_Response
     */
    protected function addToQueueAndIndexRecord($table, $uid)
    {
        // write an index queue item
        $this->indexQueue->updateItem($table, $uid);

        // run the indexer
        $items = $this->indexQueue->getItems($table, $uid);
        foreach ($items as $item) {
            $result = $this->indexer->index($item);
        }

        return $result;
    }
}
