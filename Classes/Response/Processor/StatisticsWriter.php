<?php
namespace ApacheSolrForTypo3\Solr\Response\Processor;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Util;
use ApacheSolrForTypo3\Solr\HtmlContentExtractor;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes statistics after searches have been conducted.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Dimitri Ebert <dimitri.ebert@dkd.de>
 */
class StatisticsWriter implements ResponseProcessor
{

    /**
     * Processes a query and its response after searching for that query.
     *
     * @param Query $query The query that has been searched for.
     * @param \Apache_Solr_Response $response The response for the last query.
     */
    public function processResponse(
        Query $query,
        \Apache_Solr_Response $response
    ) {
        $urlParameters = GeneralUtility::_GP('tx_solr');
        $keywords = $query->getKeywords();
        $filters = isset($urlParameters['filter']) ? $urlParameters['filter'] : [];

        if (empty($keywords)) {
            // do not track empty queries
            return;
        }

        $keywords = $this->sanitizeString($keywords);

        $sorting = '';
        if (!empty($urlParameters['sort'])) {
            $sorting = $this->sanitizeString($urlParameters['sort']);
        }

        $configuration = Util::getSolrConfiguration();
        if ($configuration->getSearchFrequentSearchesUseLowercaseKeywords()) {
            $keywords = strtolower($keywords);
        }

        $ipMaskLength = $configuration->getStatisticsAnonymizeIP();

        $insertFields = [
            'pid' => $GLOBALS['TSFE']->id,
            'root_pid' => $GLOBALS['TSFE']->tmpl->rootLine[0]['uid'],
            'tstamp' => $GLOBALS['EXEC_TIME'],
            'language' => $GLOBALS['TSFE']->sys_language_uid,

            'num_found' => isset($response->response->numFound) ? (int)$response->response->numFound : 0,
            'suggestions_shown' => is_object($response->spellcheck->suggestions) ? (int)get_object_vars($response->spellcheck->suggestions) : 0,
            'time_total' => isset($response->debug->timing->time) ? $response->debug->timing->time : 0,
            'time_preparation' => isset($response->debug->timing->prepare->time) ? $response->debug->timing->prepare->time : 0,
            'time_processing' => isset($response->debug->timing->process->time) ? $response->debug->timing->process->time : 0,

            'feuser_id' => (int)$GLOBALS['TSFE']->fe_user->user['uid'],
            'cookie' => $GLOBALS['TSFE']->fe_user->id,
            'ip' => $this->applyIpMask(GeneralUtility::getIndpEnv('REMOTE_ADDR'),
                $ipMaskLength),

            'page' => (int)$urlParameters['page'],
            'keywords' => $keywords,
            'filters' => serialize($filters),
            'sorting' => $sorting,
            'parameters' => serialize($response->responseHeader->params)
        ];

        $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_solr_statistics',
            $insertFields);
    }

    /**
     * Sanitizes a string
     *
     * @param $string String to sanitize
     * @return string Sanitized string
     */
    protected function sanitizeString($string)
    {
        // clean content
        $string = HtmlContentExtractor::cleanContent($string);
        $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
        $string = filter_var(strip_tags($string), FILTER_SANITIZE_STRING); // after entity decoding we might have tags again
        $string = trim($string);

        return $string;
    }

    /**
     * Internal function to mask portions of the visitor IP address
     *
     * @param string $ip IP address in network address format
     * @param int $maskLength Number of octets to reset
     * @return string
     */
    protected function applyIpMask($ip, $maskLength)
    {
        // IPv4 or mapped IPv4 in IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $i = strlen($ip);
            if ($maskLength > $i) {
                $maskLength = $i;
            }

            while ($maskLength-- > 0) {
                $ip[--$i] = chr(0);
            }
        } else {
            $masks = [
                'ffff:ffff:ffff:ffff:ffff:ffff:ffff:ffff',
                'ffff:ffff:ffff:ffff::',
                'ffff:ffff:ffff:0000::',
                'ffff:ff00:0000:0000::'
            ];
            return $ip & pack('a16', inet_pton($masks[$maskLength]));
        }

        return $ip;
    }
}
