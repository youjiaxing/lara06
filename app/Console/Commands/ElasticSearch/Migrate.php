<?php

namespace App\Console\Commands\ElasticSearch;

use App\Console\Commands\ElasticSearch\Indices\Index;
use App\Console\Commands\ElasticSearch\Indices\ProductIndex;
use Elasticsearch\Client;
use Illuminate\Console\Command;

class Migrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ElasticSearch 索引结构迁移';

    /**
     * @var Client
     */
    protected $es;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Client $es)
    {
        $this->es = $es;

        /**
         * @var Index[] $indices
         */
        $indices = [
            new ProductIndex(),
        ];

        foreach ($indices as $index) {
            $aliasName = $index->getAliasName();
            if ($this->es->indices()->exists(['index' => $aliasName])) {
                $this->info(sprintf('索引(%s)已存在, 准备更新', $aliasName));
                try {
                    $this->updateIndex($aliasName, $index);
                } catch (\Throwable $e) {
                    $this->warn('更新失败, 准备重建');
                    $this->recreateIndex($aliasName, $index);
                }
                $this->info('操作成功');
            } else {
                $this->info(sprintf('索引(%s)不存在, 准备创建', $aliasName));
                $this->createIndex($aliasName, $index);
                $this->info('创建成功, 准备初始化数据');
                $index->rebuild($aliasName);
                $this->info('操作成功');
            }
        }
    }

    protected function createIndex(string $aliasName, Index $index)
    {
        $this->es->indices()->create(
            [
                // 第一个版本的索引名后缀为 _0
                'index' => $aliasName . '_0',
                'body' => [
                    'settings' => $index->getSettings(),
                    'mappings' => [
                        'properties' => $index->getProperties()
                    ],
                    'aliases' => [
                        // 同时创建别名, php 中需使用 new \stdClass 来表示空对象
                        $aliasName => new \stdClass(),
                    ]
                ]
            ]
        );
    }

    protected function updateIndex(string $aliasName, Index $index)
    {
        $exception = null;
        // 暂时关闭索引
        $this->es->indices()->close(['index' => $aliasName]);
        try {
            // 更新索引设置
            $putSettingResult = $this->es->indices()->putSettings(
                [
                    'index' => $aliasName,
                    'body' => [
                        'settings' => $index->getSettings()
                    ]
                ]
            );

            $putMappingResult = $this->es->indices()->putMapping(
                [
                    'index' => $aliasName,
                    'body' => [
                        'properties' => $index->getProperties(),
                    ]
                ]
            );
        } catch (\Throwable $e) {
            $exception = $e;
        } finally {
            // 重新打开索引
            $this->es->indices()->open(['index' => $aliasName]);
        }

        if ($exception) {
            throw $exception;
        }
    }

    protected function recreateIndex(string $aliasName, Index $index)
    {
        // 获取索引信息，返回结构的 key 为索引名称，value 为别名
        $indexInfo = $this->es->indices()->getAlias(['index' => $aliasName]);
        dump(compact('aliasName', 'indexInfo'));

        // 取出第一个 key 即为索引名称
        $indexName = array_keys($indexInfo)[0];
        // 正则判断是否以 "_数字" 为结尾
        if (!preg_match('/_(\d+)$/', $indexName, $matches)) {
            $msg = "索引名称不正确: " . $indexName;
            $this->error($msg);
            throw new \Exception($msg);
        }

        // 新的索引名称
        $seq = (int)$matches[1] + 1;
        $newIndexName = $aliasName . '_' . $seq;
        $this->info('正在创建索引');
        $this->es->indices()->create(
            [
                'index' => $newIndexName,
                'body' => [
                    'settings' => $index->getSettings(),
                    'mappings' => [
                        'properties' => $index->getProperties()
                    ],
                ]
            ]
        );
        $this->info('创建成功, 准备重建数据');
        $index->rebuild($newIndexName);
        $this->info('重建成功, 准备修改别名');
        $this->es->indices()->putAlias(['index' => $newIndexName, 'name' => $aliasName]);
        $this->info('修改成功，准备删除旧索引');
        $this->es->indices()->delete(['index' => $indexName]);
        $this->info('删除成功');
    }
}
