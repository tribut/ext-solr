<?php
namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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

use ApacheSolrForTypo3\Solr\Api;
use ApacheSolrForTypo3\Solr\Domain\Search\Statistics\StatisticsRepository;
use ApacheSolrForTypo3\Solr\System\Validator\Path;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Info Module
 */
class InfoModuleController extends AbstractModuleController
{
    /**
     * @var \ApacheSolrForTypo3\Solr\ConnectionManager
     * @inject
     */
    protected $solrConnectionManager;

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);

        /* @var ModuleTemplate $module */ // holds the state of chosen tab
        $module = GeneralUtility::makeInstance(ModuleTemplate::class);
        $coreOptimizationTabs = $module->getDynamicTabMenu([], 'coreOptimization');
        $this->view->assign('tabs', $coreOptimizationTabs);
    }

    /**
     * Index action, shows an overview of the state of the Solr index
     *
     * @return void
     */
    public function indexAction()
    {
        if ($this->selectedSite === null) {
            $this->view->assign('can_not_proceed', true);
            return;
        }

        $this->collectConnectionInfos();
        $this->collectStatistics();
        $this->collectIndexFieldsInfo();
    }

    /**
     * Checks whether the configured Solr server can be reached and provides a
     * flash message according to the result of the check.
     *
     * @return void
     */
    protected function collectConnectionInfos()
    {
        $connectedHosts = [];
        $missingHosts = [];
        $invalidPaths = [];

        /* @var Path $path */
        $path = GeneralUtility::makeInstance(Path::class);
        $connections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);

        if (empty($connections)) {
            $this->view->assign('can_not_proceed', true);
            return;
        }

        foreach ($connections as $connection) {
            $coreUrl = $connection->getScheme() . '://' . $connection->getHost() . ':' . $connection->getPort() . $connection->getPath();

            if ($connection->ping()) {
                $connectedHosts[] = $coreUrl;
            } else {
                $missingHosts[] = $coreUrl;
            }

            if (!$path->isValidSolrPath($connection->getPath())) {
                $invalidPaths[] = $connection->getPath();
            }
        }

        $this->view->assignMultiple([
            'site' => $this->selectedSite,
            'apiKey' => Api::getApiKey(),
            'connectedHosts' => $connectedHosts,
            'missingHosts' => $missingHosts,
            'invalidPaths' => $invalidPaths
        ]);
    }

    /**
     * Index action, shows an overview of the state of the Solr index
     *
     * @return void
     */
    protected function collectStatistics()
    {
        // TODO make time frame user adjustable, for now it's last 30 days

        $siteRootPageId = $this->selectedSite->getRootPageId();
        /* @var StatisticsRepository $statisticsRepository */
        $statisticsRepository = GeneralUtility::makeInstance(StatisticsRepository::class);

        // @TODO: Do we want Typoscript constants to restrict the results?
        $this->view->assign(
            'top_search_phrases',
            $statisticsRepository->getTopKeyWordsWithHits($siteRootPageId, 30, 5)
        );
        $this->view->assign(
            'top_search_phrases_without_hits',
            $statisticsRepository->getTopKeyWordsWithoutHits($siteRootPageId, 30, 5)
        );
        $this->view->assign(
            'search_phrases_statistics',
            $statisticsRepository->getSearchStatistics($siteRootPageId, 30, 100)
        );

        $labels = [];
        $data = [];
        $chartData = $statisticsRepository->getQueriesOverTime($siteRootPageId, 30, 86400);
        foreach ($chartData as $bucket) {
            $labels[] = strftime('%x', $bucket['timestamp']);
            $data[] = (int)$bucket['numQueries'];
        }

        $this->view->assign('queriesChartLabels', json_encode($labels));
        $this->view->assign('queriesChartData', json_encode($data));
    }

    /**
     * Gets Luke meta data for the currently selected core and provides a list
     * of that data.
     *
     * @return void
     */
    protected function collectIndexFieldsInfo()
    {
        $indexFieldsInfoByCorePaths = [];

        $solrCoreConnections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);
        foreach ($solrCoreConnections as $solrCoreConnection) {
            $indexFieldsInfo = [
                'corePath' => $solrCoreConnection->getPath()
            ];
            if ($solrCoreConnection->ping()) {
                $lukeData = $solrCoreConnection->getLukeMetaData();

                /* @var Registry $registry */
                $registry = GeneralUtility::makeInstance(Registry::class);
                $limit = $registry->get('tx_solr', 'luke.limit', 20000);
                $limitNote = '';

                if (isset($lukeData->index->numDocs) && $lukeData->index->numDocs > $limit) {
                    $limitNote = '<em>Too many terms</em>';
                } elseif (isset($lukeData->index->numDocs)) {
                    $limitNote = 'Nothing indexed';
                    // below limit, so we can get more data
                    // Note: we use 2 since 1 fails on Ubuntu Hardy.
                    $lukeData = $solrCoreConnection->getLukeMetaData(2);
                }

                $fields = $this->getFields($lukeData, $limitNote);
                $coreMetrics = $this->getCoreMetrics($lukeData, $fields);

                $indexFieldsInfo['noError'] = 'OK';
                $indexFieldsInfo['fields'] = $fields;
                $indexFieldsInfo['coreMetrics'] = $coreMetrics;
            } else {
                $indexFieldsInfo['noError'] = null;

                $this->addFlashMessage(
                    '',
                    'Unable to contact Apache Solr server: ' . $this->selectedSite->getLabel() . ' ' . $solrCoreConnection->getPath(),
                    FlashMessage::ERROR
                );
            }
            $indexFieldsInfoByCorePaths[$solrCoreConnection->getPath()] = $indexFieldsInfo;
        }
        $this->view->assign('indexFieldsInfoByCorePaths', $indexFieldsInfoByCorePaths);
    }

    /**
     * Gets field metrics.
     *
     * @param \Apache_Solr_Response $lukeData Luke index data
     * @param string $limitNote Note to display if there are too many documents in the index to show number of terms for a field
     *
     * @return array An array of field metrics
     */
    protected function getFields(\Apache_Solr_Response $lukeData, $limitNote)
    {
        $rows = [];

        $fields = (array)$lukeData->fields;
        foreach ($fields as $name => $field) {
            $rows[$name] = [
                'name' => $name,
                'type' => $field->type,
                'docs' => isset($field->docs) ? $field->docs : 0,
                'terms' => isset($field->distinct) ? $field->distinct : $limitNote
            ];
        }
        ksort($rows);

        return $rows;
    }

    /**
     * Gets general core metrics.
     *
     * @param \Apache_Solr_Response $lukeData Luke index data
     * @param array $fields Fields metrics
     *
     * @return array An array of core metrics
     */
    protected function getCoreMetrics(\Apache_Solr_Response $lukeData, array $fields)
    {
        $coreMetrics = [
            'numberOfDocuments' => $lukeData->index->numDocs,
            'numberOfDeletedDocuments' => $lukeData->index->deletedDocs,
            'numberOfTerms' => $lukeData->index->numTerms,
            'numberOfFields' => count($fields)
        ];

        return $coreMetrics;
    }
}
