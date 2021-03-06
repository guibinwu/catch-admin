<?php
namespace catcher\generate;


use catcher\exceptions\FailedException;
use catcher\generate\factory\Controller;
use catcher\generate\factory\Migration;
use catcher\generate\factory\Model;
use catcher\generate\factory\Route;
use catcher\generate\factory\SQL;
use think\facade\Db;

class Generator
{
    /**
     * generate
     *
     * @time 2020年04月29日
     * @param $params
     * @return array
     */
    public function done($params)
    {
        $params = \json_decode($params['data'], true);

        [$controller, $model] = $this->parseParams($params);

        $message = [];

        $files = [];
        $migration = '';
        $table = null;

        try {
            if ($params['create_controller']) {
                $files[] = (new Controller)->done($controller);
                array_push($message, 'controller created successfully');
            }

            if ($params['create_table']) {
                $table = (new SQL)->done($model);
                array_push($message, 'table created successfully');
            }

            if ($params['create_model']) {
                $files[] = (new Model)->done($model);
                array_push($message, 'model created successfully');
            }

            if ($params['create_migration']) {
                $migration = (new Migration)->done([$controller['module'], $model['table']]);
                array_push($message, 'migration created successfully');
            }
        } catch (\Exception $exception) {
            $this->rollback($files, $migration, $table);
            throw new FailedException($exception->getMessage());
        }

        // 只有最后成功才写入 route
        (new Route())->controller($params['controller'])
            ->restful($params['restful'])
            ->methods((new Controller())->parseOtherMethods($params['other_function']))
            ->done();

        return $message;
    }

    /**
     * preview
     *
     * @time 2020年04月29日
     * @param $params
     * @return bool|string|string[]
     */
    public function preview($params)
    {
        $params = \json_decode($params['data'], true);

        $type = $params['type'];

        [$controller, $model] = $this->parseParams($params);

        switch ($type) {
            case 'controller':
                return (new Controller())->getContent($controller);
            case 'model':
                return (new Model())->getContent($model);
            default:
                break;
        }
    }


    /**
     * parse params
     *
     * @time 2020年04月28日
     * @param $params
     * @return array[]
     */
    protected function parseParams($params)
    {
        $module = $params['controller']['module'] ?? false;

        if (!$module) {
            throw new FailedException('请设置模块');
        }

        $controller = [
            'module' => $module,
            'model'  => $params['controller']['model'] ?? '',
            'controller' => $params['controller']['controller'] ?? '',
            'restful' => $params['controller']['restful'],
            'other_function' => $params['controller']['other_function'],
        ];

        $table = $params['controller']['table'] ?? '';
        if ($table) {
            $table =  \config('database.connections.mysql.prefix') . $table;

        }
        $model = [
            'table' => $table,
            'model' => $params['controller']['model'] ?? '',
            'sql'   => $params['model']['data'],
            'extra' => $params['model']['extra'],
        ];


        return [$controller, $model];
    }


    /**
     * 回滚
     *
     * @param $files
     * @param $migration
     * @param $table
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author JaguarJack <njphper@gmail.com>
     * @date 2020/7/11
     */
    protected function rollback($files, $migration, $table)
    {
        if ((new SQL())->hasTableExists($table)) {
            Db::query(sprintf('drop table %s', $table));
        }

        foreach ($files as $file) {
            unlink($file);
        }

        if ($migration && unlink($migration)) {
            $model = new class extends \think\Model {
                protected $name = 'migrations';
            };

            $model->order('version', 'desc')->find()->delete();
        }
    }
}
