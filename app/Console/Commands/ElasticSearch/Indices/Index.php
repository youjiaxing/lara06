<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2020/6/29 9:49
 */

namespace App\Console\Commands\ElasticSearch\Indices;

interface Index
{
    /**
     * @return string
     */
    public function getAliasName();

    /**
     * @return array
     */
    public function getProperties();

    /**
     * @return array
     */
    public function getSettings();

    public function rebuild(string $indexName);
}