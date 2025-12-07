<?php

declare(strict_types=1);

/**
 * This file is part of FssPHP Framework.
 *
 * @link     https://github.com/xuey490/project
 * @license  https://github.com/xuey490/project/blob/main/LICENSE
 *
 * @Filename: %filename%
 * @Date: 2025-11-24
 * @Developer: xuey863toy
 * @Email: xuey863toy@gmail.com
 */

namespace Framework\Database;

//use Psr\Log\LoggerInterface;
use InvalidArgumentException;

final class DatabaseFactory implements DatabaseInterface
{
    private DatabaseInterface $driver;
	
    /** @var array<string, mixed> 模型实例缓存池 */
    private array $modelCache = [];
	
    /**
     * @param array                $config  数据库配置
     * @param string               $ormType ORM类型 ('laravelORM', 'thinkORM')
     * @param LoggerInterface|null $logger  自定义log类，PSR-3 日志记录器
     */
    public function __construct(
        array $config, 
        string $ormType = 'thinkORM', 
		protected ?object $logger = null
        //?LoggerInterface $logger = null
    ) {

        $this->driver = match ($ormType) {
            'laravelORM', 'laravel' 	=> new EloquentFactory($config, $logger),
            'thinkORM'               	=> new ThinkORMFactory($config, $logger),
            default               		=> throw new InvalidArgumentException("Unsupported ORM type: {$ormType}"),
        };
    }

    /**
     * 快速获取 QueryBuilder（工厂应实现 builder()）
     * 如果底层不支持 builder()，退回 make()
     */
    public function builder(string $modelClass): mixed
    {
        if (method_exists($this->driver, 'builder')) {
            return $this->driver->builder($modelClass);
        }
        return $this->make($modelClass);
    }

    /**
     * 直接获取“新模型”实例（非 builder）
     */
    public function newModel(string $modelClass): mixed
    {
        if (method_exists($this->driver, 'newModel')) {
            return $this->driver->newModel($modelClass);
        }
        return $this->make($modelClass);
    }

    /**
     * 驱动判定，Repository 使用此 API 而不是 instanceof
     */
    public function isEloquent(): bool
    {
        return $this->driver instanceof EloquentFactory;
    }

    public function isThink(): bool
    {
        return $this->driver instanceof ThinkORMFactory;
    }

    /**
     * 判断给定 modelClass 是否为 ORM 模型（由 driver 识别）
     */
    public function isModel(string $modelClass): bool
    {
        if (method_exists($this->driver, 'isModel')) {
            return $this->driver->isModel($modelClass);
        }
        // 兜底：检查类是否为常见 ORM 基类子类
        if (class_exists($modelClass)) {
            return is_subclass_of($modelClass, '\Illuminate\Database\Eloquent\Model')
                || is_subclass_of($modelClass, '\think\Model');
        }
        return false;
    }


    public function __invoke(string $modelClass): mixed
    {
        return $this->driver->make($modelClass);
    }

    /**
     * 缓存模型实例，避免重复 new Model()
     */
    public function make(string $modelClass): mixed
    {
        // 表名模式（不是 class），直接跳缓存
        if (!class_exists($modelClass)) {
            return $this->driver->make($modelClass);
        }

        // 模型缓存
        if (!isset($this->modelCache[$modelClass])) {
            $this->modelCache[$modelClass] = $this->driver->make($modelClass);
        }

        // 每次返回 clone，避免污染 query builder
        return $this->modelCache[$modelClass];
    }
}